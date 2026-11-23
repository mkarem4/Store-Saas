<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Enums\ExpenseCategoryKey;
use App\Enums\ExpenseSource;
use App\Enums\FulfillmentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderStatusChanged;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Modifier;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemModifier;
use App\Models\OrderStatusHistory;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\BusinessContext;
use App\Support\Money;
use App\Support\PlatformConfig;
use App\Support\TaxCalculator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * App order lifecycle (spec §6.1 / §6.2):
 *
 *   place    → stock reserved, card authorized (manual capture)
 *   accept   → payment captured
 *   complete → reservation committed, sale recorded, commission + Stripe fee
 *              booked, store's net credited to its wallet (pending)
 *   reject / expire / customer cancel (pending) → void + release
 *   store cancel after accept → full refund + release
 *
 * Stock is reserved at placement rather than at acceptance so two customers
 * cannot both be authorized for the last unit.
 */
class OrderService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly StockService $stock,
        private readonly SaleService $sales,
        private readonly DeliveryFeeCalculator $delivery,
        private readonly WalletService $wallet,
        private readonly SystemExpenseRecorder $expenses,
    ) {}

    /**
     * @param  array<int, array{variant_id: int, qty: float|int, modifier_ids?: array<int, int>}>  $items
     * @param  array<string, mixed>|null  $address  line1, line2, city, phone, notes, latitude, longitude
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

        if (! $business->isOpenNow()) {
            throw new InvalidArgumentException(__('This store is closed right now.'));
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

            if (! $variant->unit->allowsDecimalQuantity() && floor($qty) !== $qty) {
                throw new InvalidArgumentException(__(':name is sold in whole units.', ['name' => $variant->displayName()]));
            }

            if (! $variant->product?->is_published || ! $variant->product?->is_available) {
                throw new InvalidArgumentException(__(':name is not available.', ['name' => $variant->displayName()]));
            }

            if ($variant->product->track_stock && $variant->availableQty() < $qty) {
                throw new InvalidArgumentException(__('Not enough stock for :name.', ['name' => $variant->displayName()]));
            }

            $modifiers = $this->modifiersFor($business, $variant, $item['modifier_ids'] ?? []);

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
                'amount' => Money::format($minimum),
            ]));
        }

        $deliveryFee = 0;
        $distanceKm = null;

        if ($fulfillment === FulfillmentType::Delivery) {
            $delivery = $this->delivery->quote($business, $address['latitude'] ?? null, $address['longitude'] ?? null);
            $deliveryFee = $delivery['fee_cents'];
            $distanceKm = $delivery['distance_km'];
        }

        $taxRate = TaxCalculator::rateFor($business);
        $tax = TaxCalculator::taxCents($subtotal, $taxRate);
        $total = $subtotal + $deliveryFee + $tax;

        return [
            'items' => array_map(fn (array $line): array => [
                'variant_id' => $line['variant']->id,
                'name' => $line['variant']->displayName(),
                'qty' => $line['qty'],
                'unit' => $line['variant']->unit->value,
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
            'delivery_distance_km' => $distanceKm,
            'discount_cents' => 0,
            'tax_cents' => $tax,
            'tax_rate' => $taxRate,
            'total_cents' => $total,
            'cogs_cents' => $cogs,
            'currency' => $business->currency,
        ];
    }

    /**
     * @param  array<int, array{variant_id: int, qty: float|int, modifier_ids?: array<int, int>}>  $items
     * @param  array<string, mixed>|null  $address
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
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing !== null) {
                if ((int) $existing->customer_id !== (int) $customer->id || (int) $existing->business_id !== (int) $business->id) {
                    throw new InvalidArgumentException(__('This idempotency key is unavailable.'));
                }

                return $existing->load('items.modifiers');
            }
        }

        $quote = $this->quote($business, $items, $fulfillment, $address);
        $timeout = (int) $business->settings('accept_timeout_minutes', 10);

        return BusinessContext::run($business->id, function () use ($customer, $business, $quote, $fulfillment, $address, $notes, $idempotencyKey, $paymentMethodId, $timeout): Order {
            $order = DB::transaction(function () use ($customer, $business, $quote, $fulfillment, $address, $notes, $idempotencyKey, $timeout): Order {
                $order = Order::query()->create([
                    'business_id' => $business->id,
                    'customer_id' => $customer->id,
                    'number' => $this->nextNumber($business),
                    'fulfillment' => $fulfillment,
                    'status' => OrderStatus::Pending,
                    'payment_status' => PaymentStatus::Pending,
                    'subtotal_cents' => $quote['subtotal_cents'],
                    'delivery_fee_cents' => $quote['delivery_fee_cents'],
                    'delivery_distance_km' => $quote['delivery_distance_km'],
                    'discount_cents' => $quote['discount_cents'],
                    'tax_rate' => $quote['tax_rate'],
                    'tax_cents' => $quote['tax_cents'],
                    'total_cents' => $quote['total_cents'],
                    'cogs_cents' => $quote['cogs_cents'],
                    'platform_fee_cents' => PlatformConfig::commissionCents($quote['subtotal_cents'] - $quote['discount_cents']),
                    'idempotency_key' => $idempotencyKey ?? (string) Str::uuid(),
                    'delivery_address' => $fulfillment === FulfillmentType::Delivery ? $address : null,
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
                // Payment failure = no order (§6.1). Keep the row for audit but
                // release everything it held.
                $this->releaseStock($order);

                $order->update([
                    'status' => OrderStatus::Cancelled,
                    'payment_status' => PaymentStatus::Failed,
                    'cancel_reason' => __('Payment authorization failed.'),
                    'cancelled_at' => now(),
                ]);

                throw $exception;
            }

            // With the client-secret flow the card is confirmed in the app; the
            // webhook (amount_capturable_updated) flips the order to authorized.
            $order->update([
                'payment_intent_id' => $authorization['intent_id'],
                'payment_status' => $authorization['status'] === 'requires_capture'
                    ? PaymentStatus::Authorized
                    : PaymentStatus::Pending,
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

        if ($order->payment_status !== PaymentStatus::Authorized) {
            throw new InvalidArgumentException(__('The customer has not completed the card payment yet.'));
        }

        $this->gateway->capture($order);

        return $this->markCaptured($order, $userId);
    }

    /**
     * Record that the payment was captured. Called by accept() and by the
     * Stripe webhook; idempotent for orders that already moved on.
     */
    public function markCaptured(Order $order, ?int $userId = null): Order
    {
        if ($order->status !== OrderStatus::Pending) {
            return $order;
        }

        if ($order->isExpired()) {
            return $this->expire($order);
        }

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
            $this->releaseStock($order);

            $order->update([
                'status' => OrderStatus::Expired,
                'payment_status' => PaymentStatus::Refunded,
                'refunded_cents' => $order->total_cents,
                'cancel_reason' => $reason,
                'cancelled_at' => now(),
            ]);

            $this->history($order, OrderStatus::Expired, null, $reason);

            return $order->refresh();
        });
    }

    /**
     * Webhook: the card was confirmed and the amount is held (requires_capture).
     */
    public function markAuthorized(Order $order): Order
    {
        if ($order->isPending() && in_array($order->payment_status, [PaymentStatus::Pending, PaymentStatus::Failed], true)) {
            $order->update(['payment_status' => PaymentStatus::Authorized]);
        }

        return $order->refresh();
    }

    /**
     * Webhook: the card was declined. The order stays pending so the customer
     * can retry until the acceptance window closes; the store cannot accept it.
     */
    public function markPaymentFailed(Order $order): Order
    {
        if ($order->isPending() && $order->payment_status !== PaymentStatus::Authorized) {
            $order->update(['payment_status' => PaymentStatus::Failed]);
        }

        return $order->refresh();
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

    /**
     * Hand-over: stock leaves for good, the sale hits the reports, and the
     * store's net (paid − 3% − actual Stripe fee) is credited to its wallet.
     */
    public function complete(Order $order, ?int $userId = null): Order
    {
        if (! in_array($order->status, [OrderStatus::ReadyForPickup, OrderStatus::OutForDelivery], true)) {
            throw new InvalidArgumentException(__('Only ready orders can be completed.'));
        }

        $gatewayFee = $this->gatewayFee($order);

        $order = DB::transaction(function () use ($order, $userId, $gatewayFee): Order {
            /** @var Order $locked */
            $locked = Order::query()->withoutGlobalScope('business')->lockForUpdate()->findOrFail($order->id);

            if ($locked->status === OrderStatus::Completed) {
                return $locked;
            }

            foreach ($locked->items as $item) {
                $variant = $this->variantFor($item);

                if ($variant !== null && $variant->product?->track_stock) {
                    $this->stock->commitReservation($variant, (float) $item->qty, $locked, $userId);
                }
            }

            $platformFee = PlatformConfig::commissionCents($locked->commissionableCents());

            $locked->update([
                'status' => OrderStatus::Completed,
                'completed_at' => now(),
                'platform_fee_cents' => $platformFee,
                'gateway_fee_cents' => $gatewayFee,
                'store_payout_cents' => (int) $locked->total_cents - $platformFee - $gatewayFee,
            ]);

            $sale = $this->sales->recordOrderSale($locked);

            $this->expenses->record($locked->business_id, ExpenseCategoryKey::PlatformCommission->value, ExpenseSource::PlatformFee, $platformFee, __('Platform commission on order :number', ['number' => $locked->number]), $locked);
            $this->expenses->record($locked->business_id, ExpenseCategoryKey::PaymentFees->value, ExpenseSource::GatewayFee, $gatewayFee, __('Payment fee on order :number', ['number' => $locked->number]), $locked);

            $this->wallet->creditCompletedOrder($locked->refresh());

            $this->history($locked, OrderStatus::Completed, null, __('Order completed'), $userId);

            return $locked->setRelation('sale', $sale);
        });

        return $order->refresh();
    }

    public function cancelByCustomer(Order $order): Order
    {
        if (! $order->status->canBeCancelledByCustomer()) {
            throw new InvalidArgumentException(__('This order can no longer be cancelled.'));
        }

        return $this->close($order, OrderStatus::Cancelled, __('Cancelled by customer'));
    }

    /**
     * Store cancels an accepted order (M7.6): full refund to the card.
     */
    public function cancelByBusiness(Order $order, string $reason, ?int $userId = null): Order
    {
        if ($order->isPending()) {
            return $this->reject($order, $reason, $userId);
        }

        if (! in_array($order->status, [OrderStatus::Accepted, OrderStatus::Preparing, OrderStatus::ReadyForPickup, OrderStatus::OutForDelivery], true)) {
            throw new InvalidArgumentException(__('This order can no longer be cancelled.'));
        }

        if (blank($reason)) {
            throw new InvalidArgumentException(__('Enter a reason for cancelling.'));
        }

        return DB::transaction(function () use ($order, $reason, $userId): Order {
            $this->gateway->refund($order, (int) $order->total_cents);
            $this->releaseStock($order);

            $order->update([
                'status' => OrderStatus::Cancelled,
                'payment_status' => PaymentStatus::Refunded,
                'refunded_cents' => $order->total_cents,
                'cancel_reason' => $reason,
                'cancelled_at' => now(),
            ]);

            $this->history($order, OrderStatus::Cancelled, null, $reason, $userId);

            return $order->refresh();
        });
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
            $this->releaseStock($order);

            $order->update([
                'status' => $status,
                'payment_status' => PaymentStatus::Voided,
                'cancel_reason' => $reason,
                'cancelled_at' => now(),
            ]);

            $this->history($order, $status, null, $reason, $userId);

            return $order->refresh();
        });
    }

    private function gatewayFee(Order $order): int
    {
        try {
            return (int) ($this->gateway->feeFor($order) ?? 0);
        } catch (\Throwable $exception) {
            // Never block hand-over on the fee lookup; it is logged and can be
            // corrected with a wallet adjustment.
            Log::warning('Could not fetch the payment fee.', [
                'order_id' => $order->id,
                'message' => $exception->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * @param  array<int, int>  $modifierIds
     * @return Collection<int, Modifier>
     */
    private function modifiersFor(Business $business, ProductVariant $variant, array $modifierIds): Collection
    {
        if ($modifierIds === []) {
            return collect();
        }

        $modifiers = Modifier::query()
            ->withoutGlobalScope('business')
            ->where('business_id', $business->id)
            ->whereHas('group', fn ($query) => $query->where('product_id', $variant->product_id))
            ->with('group')
            ->whereIn('id', $modifierIds)
            ->get();

        if ($modifiers->count() !== count(array_unique($modifierIds))) {
            throw new InvalidArgumentException(__('Some options are not available for :name.', ['name' => $variant->displayName()]));
        }

        return $modifiers;
    }

    private function releaseStock(Order $order): void
    {
        foreach ($order->items as $item) {
            $variant = $this->variantFor($item);

            if ($variant !== null && $variant->product?->track_stock) {
                $this->stock->release($variant, (float) $item->qty);
            }
        }
    }

    private function variantFor(OrderItem $item): ?ProductVariant
    {
        if ($item->product_variant_id === null) {
            return null;
        }

        return ProductVariant::query()
            ->withoutGlobalScope('business')
            ->with('product')
            ->find($item->product_variant_id);
    }

    private function history(Order $order, OrderStatus $status, ?Customer $customer = null, ?string $note = null, ?int $userId = null): void
    {
        OrderStatusHistory::query()->create([
            'business_id' => $order->business_id,
            'order_id' => $order->id,
            'status' => $status,
            'note' => $note,
            'changed_by_type' => $userId !== null ? User::class : ($customer !== null ? Customer::class : null),
            'changed_by_id' => $userId ?? $customer?->id,
        ]);

        OrderStatusChanged::dispatch($order, $status, $note);
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
