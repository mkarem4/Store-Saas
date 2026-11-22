<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Enums\FulfillmentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Modifier;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemModifier;
use App\Models\OrderStatusHistory;
use App\Models\ProductVariant;
use App\Support\BusinessContext;
use App\Support\TaxCalculator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OrderService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly StockService $stock,
        private readonly SaleService $sales,
    ) {
    }

    /**
     * @param  array<int, array{variant_id: int, qty: float|int, modifier_ids?: array<int, int>}>  $items
     * @return array<string, mixed>
     */
    public function quote(
        Business $business,
        array $items,
        FulfillmentType $fulfillment,
        ?array $address = null,
    ): array {
        if (! $business->acceptsOrders()) {
            throw new InvalidArgumentException(__('This store is not accepting orders right now.'));
        }

        if ($fulfillment === FulfillmentType::Pickup && ! $business->settings('pickup_enabled', true)) {
            throw new InvalidArgumentException(__('Pickup is not available for this store.'));
        }

        if ($fulfillment === FulfillmentType::Delivery && ! $business->settings('delivery_enabled', false)) {
            throw new InvalidArgumentException(__('Delivery is not available for this store.'));
        }

        if ($fulfillment === FulfillmentType::Delivery && blank($address)) {
            throw new InvalidArgumentException(__('A delivery address is required.'));
        }

        if ($items === []) {
            throw new InvalidArgumentException(__('Your cart is empty.'));
        }

        $lines = [];
        $subtotal = 0;
        $cogs = 0;

        foreach ($items as $item) {
            /** @var ProductVariant $variant */
            $variant = ProductVariant::query()
                ->withoutGlobalScope('business')
                ->where('business_id', $business->id)
                ->with(['product', 'jewelryAttribute'])
                ->findOrFail($item['variant_id']);

            $qty = (float) $item['qty'];

            if ($qty <= 0) {
                throw new InvalidArgumentException(__('Quantity must be positive.'));
            }

            if (! $variant->product?->is_published || ! $variant->product?->is_available) {
                throw new InvalidArgumentException(__(':name is not available.', ['name' => $variant->displayName()]));
            }

            if ($variant->product->track_stock && $variant->availableQty() < $qty) {
                throw new InvalidArgumentException(__('Not enough stock for :name.', ['name' => $variant->displayName()]));
            }

            $modifierIds = $item['modifier_ids'] ?? [];
            $modifiers = $modifierIds === []
                ? collect()
                : Modifier::query()
                    ->withoutGlobalScope('business')
                    ->where('business_id', $business->id)
                    ->with('group')
                    ->whereIn('id', $modifierIds)
                    ->get();

            $unitPrice = $variant->priceFor();
            $modifierDelta = (int) $modifiers->sum('price_delta_cents');
            $lineTotal = (int) round(($unitPrice + $modifierDelta) * $qty);

            $subtotal += $lineTotal;
            $cogs += (int) round($variant->cost_price_cents * $qty);

            $lines[] = [
                'variant' => $variant,
                'qty' => $qty,
                'unit_price_cents' => $unitPrice,
                'unit_cost_cents' => (int) $variant->cost_price_cents,
                'total_cents' => $lineTotal,
                'modifiers' => $modifiers,
            ];
        }

        $minimum = (int) $business->settings('minimum_order_cents', 0);

        if ($minimum > 0 && $subtotal < $minimum) {
            throw new InvalidArgumentException(__('The minimum order is :amount.', [
                'amount' => \App\Support\Money::format($minimum),
            ]));
        }

        $deliveryFee = $fulfillment === FulfillmentType::Delivery
            ? (int) $business->settings('delivery_fee_cents', 0)
            : 0;

        $tax = TaxCalculator::taxCents($subtotal, $business->id);
        $total = $subtotal + $deliveryFee + $tax;

        return [
            'items' => array_map(fn (array $line): array => [
                'variant_id' => $line['variant']->id,
                'name' => $line['variant']->displayName(),
                'qty' => $line['qty'],
                'unit_price_cents' => $line['unit_price_cents'],
                'modifiers' => $line['modifiers']->map(fn (Modifier $modifier): array => [
                    'id' => $modifier->id,
                    'name' => $modifier->translated('name'),
                    'price_delta_cents' => $modifier->price_delta_cents,
                ])->all(),
                'total_cents' => $line['total_cents'],
            ], $lines),
            'lines' => $lines,
            'subtotal_cents' => $subtotal,
            'delivery_fee_cents' => $deliveryFee,
            'discount_cents' => 0,
            'tax_cents' => $tax,
            'tax_rate' => TaxCalculator::rateFor($business->id),
            'total_cents' => $total,
            'cogs_cents' => $cogs,
        ];
    }

    /**
     * @param  array<int, array{variant_id: int, qty: float|int, modifier_ids?: array<int, int>}>  $items
     */
    public function place(
        Customer $customer,
        Business $business,
        array $items,
        FulfillmentType $fulfillment,
        ?array $address = null,
        ?string $notes = null,
        ?string $idempotencyKey = null,
        ?string $paymentMethodId = null,
    ): Order {
        if ($idempotencyKey !== null) {
            $existing = Order::query()
                ->withoutGlobalScope('business')
                ->where('business_id', $business->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing !== null) {
                return $existing->load('items.modifiers');
            }
        }

        $quote = $this->quote($business, $items, $fulfillment, $address);
        $timeout = (int) $business->settings('accept_timeout_minutes', 10);

        return BusinessContext::run($business->id, function () use ($customer, $business, $quote, $fulfillment, $address, $notes, $idempotencyKey, $paymentMethodId, $timeout): Order {
            $order = DB::transaction(function () use ($customer, $business, $quote, $fulfillment, $address, $notes, $idempotencyKey, $paymentMethodId, $timeout): Order {
                $order = Order::query()->create([
                    'business_id' => $business->id,
                    'customer_id' => $customer->id,
                    'number' => $this->nextNumber($business),
                    'fulfillment' => $fulfillment,
                    'status' => OrderStatus::Pending,
                    'payment_status' => PaymentStatus::Pending,
                    'subtotal_cents' => $quote['subtotal_cents'],
                    'delivery_fee_cents' => $quote['delivery_fee_cents'],
                    'discount_cents' => $quote['discount_cents'],
                    'tax_cents' => $quote['tax_cents'],
                    'total_cents' => $quote['total_cents'],
                    'cogs_cents' => $quote['cogs_cents'],
                    'idempotency_key' => $idempotencyKey ?? (string) \Illuminate\Support\Str::uuid(),
                    'delivery_address' => $address,
                    'notes' => $notes,
                    'placed_at' => now(),
                    'expires_at' => now()->addMinutes($timeout),
                ]);

                foreach ($quote['lines'] as $line) {
                    /** @var ProductVariant $variant */
                    $variant = $line['variant'];

                    $orderItem = OrderItem::query()->create([
                        'business_id' => $business->id,
                        'order_id' => $order->id,
                        'product_id' => $variant->product_id,
                        'product_variant_id' => $variant->id,
                        'name' => $variant->displayName(),
                        'qty' => $line['qty'],
                        'unit' => $variant->unit,
                        'unit_price_cents' => $line['unit_price_cents'],
                        'unit_cost_cents' => $line['unit_cost_cents'],
                        'total_cents' => $line['total_cents'],
                    ]);

                    foreach ($line['modifiers'] as $modifier) {
                        OrderItemModifier::query()->create([
                            'business_id' => $business->id,
                            'order_item_id' => $orderItem->id,
                            'modifier_id' => $modifier->id,
                            'group_name' => $modifier->group?->translated('name'),
                            'name' => $modifier->translated('name'),
                            'price_delta_cents' => $modifier->price_delta_cents,
                        ]);
                    }

                    if ($variant->product->track_stock) {
                        $this->stock->reserve($variant, $line['qty']);
                    }
                }

                $this->history($order, OrderStatus::Pending, $customer, __('Order placed'));

                return $order;
            });

            try {
                $authorization = $this->gateway->authorize($order->load('business'), $paymentMethodId);
            } catch (\Throwable $exception) {
                foreach ($order->items as $item) {
                    if ($item->product_variant_id === null) {
                        continue;
                    }

                    $variant = ProductVariant::query()
                        ->withoutGlobalScope('business')
                        ->with('product')
                        ->find($item->product_variant_id);

                    if ($variant !== null && $variant->product?->track_stock) {
                        $this->stock->release($variant, (float) $item->qty);
                    }
                }

                $order->update([
                    'status' => OrderStatus::Cancelled,
                    'payment_status' => PaymentStatus::Failed,
                    'cancel_reason' => __('Payment authorization failed.'),
                ]);

                throw $exception;
            }

            $order->update([
                'payment_intent_id' => $authorization['intent_id'],
                'payment_status' => PaymentStatus::Authorized,
            ]);

            $order->paymentClientSecret = $authorization['client_secret'];

            return $order->refresh()->load('items.modifiers');
        });
    }

    public function accept(Order $order, ?int $userId = null): Order
    {
        if (! $order->isPending()) {
            throw new InvalidArgumentException(__('Only pending orders can be accepted.'));
        }

        if ($order->isExpired()) {
            $this->expire($order);

            throw new InvalidArgumentException(__('This order has expired.'));
        }

        return DB::transaction(function () use ($order, $userId): Order {
            $this->gateway->capture($order);

            return $this->markCaptured($order, $userId);
        });
    }

    /**
     * Commit stock reservations and record the sale once payment is captured.
     *
     * Called after the gateway confirms the capture — either by the business
     * accepting the order or by a Stripe webhook for an auto-captured intent.
     */
    public function markCaptured(Order $order, ?int $userId = null): Order
    {
        if ($order->status !== OrderStatus::Pending) {
            return $order;
        }

        if ($order->isExpired()) {
            return $this->expire($order);
        }

        foreach ($order->items as $item) {
            if ($item->product_variant_id === null) {
                continue;
            }

            $variant = ProductVariant::query()
                ->withoutGlobalScope('business')
                ->with('product')
                ->find($item->product_variant_id);

            if ($variant !== null && $variant->product?->track_stock) {
                $this->stock->commitReservation($variant, (float) $item->qty, $order, $userId);
            }
        }

        $this->sales->recordOrderSale($order);

        $order->update([
            'status' => OrderStatus::Accepted,
            'payment_status' => PaymentStatus::Captured,
            'accepted_at' => now(),
        ]);

        $this->history($order, OrderStatus::Accepted, null, __('Order accepted'), $userId);

        return $order->refresh();
    }

    /**
     * The payment succeeded after the acceptance window closed.
     * Refunds the full amount, releases stock, and expires the order.
     */
    public function expireWithRefund(Order $order): Order
    {
        if (! $order->isPending() || ! $order->isExpired()) {
            return $order;
        }

        $reason = __('Acceptance window expired; payment refunded.');

        return DB::transaction(function () use ($order, $reason): Order {
            $this->gateway->refund($order, (int) $order->total_cents);

            foreach ($order->items as $item) {
                if ($item->product_variant_id === null) {
                    continue;
                }

                $variant = ProductVariant::query()
                    ->withoutGlobalScope('business')
                    ->with('product')
                    ->find($item->product_variant_id);

                if ($variant !== null && $variant->product?->track_stock) {
                    $this->stock->release($variant, (float) $item->qty);
                }
            }

            $order->update([
                'status' => OrderStatus::Expired,
                'payment_status' => PaymentStatus::Refunded,
                'cancel_reason' => $reason,
            ]);

            $this->history($order, OrderStatus::Expired, null, $reason);

            return $order->refresh();
        });
    }

    public function reject(Order $order, ?string $reason = null, ?int $userId = null): Order
    {
        if (! $order->isPending()) {
            throw new InvalidArgumentException(__('Only pending orders can be rejected.'));
        }

        return $this->close($order, OrderStatus::Rejected, $reason, $userId);
    }

    public function markPreparing(Order $order, ?int $userId = null): Order
    {
        if ($order->status !== OrderStatus::Accepted) {
            throw new InvalidArgumentException(__('Only accepted orders can be prepared.'));
        }

        $order->update(['status' => OrderStatus::Preparing]);
        $this->history($order, OrderStatus::Preparing, null, __('Order is being prepared'), $userId);

        return $order->refresh();
    }

    public function markReady(Order $order, ?int $userId = null): Order
    {
        if ($order->status !== OrderStatus::Preparing) {
            throw new InvalidArgumentException(__('Only preparing orders can be marked ready.'));
        }

        $status = $order->fulfillment === FulfillmentType::Delivery
            ? OrderStatus::OutForDelivery
            : OrderStatus::ReadyForPickup;

        $order->update(['status' => $status]);
        $this->history($order, $status, null, $status->label(), $userId);

        return $order->refresh();
    }

    public function complete(Order $order, ?int $userId = null): Order
    {
        if (! in_array($order->status, [OrderStatus::ReadyForPickup, OrderStatus::OutForDelivery], true)) {
            throw new InvalidArgumentException(__('Only ready orders can be completed.'));
        }

        $order->update([
            'status' => OrderStatus::Completed,
            'completed_at' => now(),
        ]);

        $this->history($order, OrderStatus::Completed, null, __('Order completed'), $userId);

        return $order->refresh();
    }

    public function cancelByCustomer(Order $order): Order
    {
        if (! $order->status->canBeCancelledByCustomer()) {
            throw new InvalidArgumentException(__('This order can no longer be cancelled.'));
        }

        return $this->close($order, OrderStatus::Cancelled, __('Cancelled by customer'));
    }

    public function expire(Order $order): Order
    {
        if (! $order->isPending() || ! $order->isExpired()) {
            return $order;
        }

        return $this->close($order, OrderStatus::Expired, __('Acceptance window expired'));
    }

    private function close(Order $order, OrderStatus $status, ?string $reason = null, ?int $userId = null): Order
    {
        return DB::transaction(function () use ($order, $status, $reason, $userId): Order {
            $this->gateway->void($order);

            foreach ($order->items as $item) {
                if ($item->product_variant_id === null) {
                    continue;
                }

                $variant = ProductVariant::query()
                    ->withoutGlobalScope('business')
                    ->with('product')
                    ->find($item->product_variant_id);

                if ($variant !== null && $variant->product?->track_stock) {
                    $this->stock->release($variant, (float) $item->qty);
                }
            }

            $order->update([
                'status' => $status,
                'payment_status' => PaymentStatus::Voided,
                'cancel_reason' => $reason,
            ]);

            $this->history($order, $status, null, $reason, $userId);

            return $order->refresh();
        });
    }

    private function history(Order $order, OrderStatus $status, ?Customer $customer = null, ?string $note = null, ?int $userId = null): void
    {
        OrderStatusHistory::query()->create([
            'business_id' => $order->business_id,
            'order_id' => $order->id,
            'status' => $status,
            'note' => $note,
            'changed_by_type' => $userId !== null ? \App\Models\User::class : ($customer !== null ? Customer::class : null),
            'changed_by_id' => $userId ?? $customer?->id,
        ]);
    }

    private function nextNumber(Business $business): string
    {
        $prefix = 'ORD-'.now()->format('Ymd').'-';

        $count = Order::query()
            ->withoutGlobalScope('business')
            ->where('business_id', $business->id)
            ->where('number', 'like', $prefix.'%')
            ->count();

        return $prefix.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
