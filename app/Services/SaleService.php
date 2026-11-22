<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SaleChannel;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Models\Business;
use App\Models\Modifier;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleItemModifier;
use App\Support\TaxCalculator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SaleService
{
    public function __construct(private readonly StockService $stock)
    {
    }

    /**
     * @param  array<int, array{variant_id: int, qty: float|int, unit_price_cents?: int|null, discount_cents?: int, modifier_ids?: array<int, int>}>  $items
     * @param  array<int, array{method: string, amount_cents: int, gateway_ref?: string|null}>  $payments
     */
    public function createPosSale(
        Business $business,
        array $items,
        array $payments,
        ?int $employeeId = null,
        ?int $clientId = null,
        ?int $userId = null,
        ?string $notes = null,
    ): Sale {
        if ($items === []) {
            throw new InvalidArgumentException(__('Add at least one item to the sale.'));
        }

        return DB::transaction(function () use ($business, $items, $payments, $employeeId, $clientId, $userId, $notes): Sale {
            $allowNegative = (bool) $business->settings('allow_negative_stock', false);
            $taxScope = \App\Models\PlatformSetting::get('tax_scope', 'app_only');

            $subtotal = 0;
            $discount = 0;
            $cogs = 0;
            $lines = [];

            foreach ($items as $item) {
                /** @var ProductVariant $variant */
                $variant = ProductVariant::query()
                    ->where('business_id', $business->id)
                    ->with(['product', 'jewelryAttribute'])
                    ->findOrFail($item['variant_id']);

                $qty = (float) $item['qty'];

                if ($qty <= 0) {
                    throw new InvalidArgumentException(__('Quantity must be positive.'));
                }

                $unitPrice = $item['unit_price_cents'] ?? $variant->priceFor();
                $lineDiscount = (int) ($item['discount_cents'] ?? 0);

                $modifierIds = $item['modifier_ids'] ?? [];
                $modifiers = $modifierIds === []
                    ? collect()
                    : Modifier::query()
                        ->where('business_id', $business->id)
                        ->with('group')
                        ->whereIn('id', $modifierIds)
                        ->get();

                $modifierDelta = (int) $modifiers->sum('price_delta_cents');
                $lineSubtotal = (int) round(($unitPrice + $modifierDelta) * $qty);
                $lineTotal = max(0, $lineSubtotal - $lineDiscount);

                $subtotal += $lineSubtotal;
                $discount += $lineDiscount;
                $cogs += (int) round($variant->cost_price_cents * $qty);

                $lines[] = [
                    'variant' => $variant,
                    'qty' => $qty,
                    'unit_price_cents' => $unitPrice,
                    'unit_cost_cents' => (int) $variant->cost_price_cents,
                    'discount_cents' => $lineDiscount,
                    'total_cents' => $lineTotal,
                    'modifiers' => $modifiers,
                ];
            }

            $taxable = max(0, $subtotal - $discount);
            $tax = $taxScope === 'all' ? TaxCalculator::taxCents($taxable, $business->id) : 0;
            $total = $taxable + $tax;

            $paid = (int) array_sum(array_map(fn (array $payment): int => (int) $payment['amount_cents'], $payments));

            if ($paid !== $total) {
                throw new InvalidArgumentException(__('Payments must equal the sale total.'));
            }

            $sale = Sale::query()->create([
                'business_id' => $business->id,
                'number' => $this->nextNumber($business),
                'channel' => SaleChannel::Pos,
                'employee_id' => $employeeId,
                'client_id' => $clientId,
                'subtotal_cents' => $subtotal,
                'discount_cents' => $discount,
                'tax_cents' => $tax,
                'total_cents' => $total,
                'cogs_cents' => $cogs,
                'status' => SaleStatus::Completed,
                'sold_at' => now(),
                'notes' => $notes,
            ]);

            foreach ($lines as $line) {
                /** @var ProductVariant $variant */
                $variant = $line['variant'];

                $saleItem = SaleItem::query()->create([
                    'business_id' => $business->id,
                    'sale_id' => $sale->id,
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'name' => $variant->displayName(),
                    'qty' => $line['qty'],
                    'unit' => $variant->unit,
                    'unit_price_cents' => $line['unit_price_cents'],
                    'unit_cost_cents' => $line['unit_cost_cents'],
                    'discount_cents' => $line['discount_cents'],
                    'tax_cents' => 0,
                    'total_cents' => $line['total_cents'],
                ]);

                foreach ($line['modifiers'] as $modifier) {
                    SaleItemModifier::query()->create([
                        'business_id' => $business->id,
                        'sale_item_id' => $saleItem->id,
                        'modifier_id' => $modifier->id,
                        'group_name' => $modifier->group?->translated('name'),
                        'name' => $modifier->translated('name'),
                        'price_delta_cents' => $modifier->price_delta_cents,
                    ]);
                }

                if ($variant->product->track_stock) {
                    $this->stock->deduct(
                        $variant,
                        $line['qty'],
                        StockMovementType::Sale,
                        $sale,
                        $userId,
                        allowNegative: $allowNegative,
                    );
                }
            }

            foreach ($payments as $payment) {
                Payment::query()->create([
                    'business_id' => $business->id,
                    'payable_type' => $sale->getMorphClass(),
                    'payable_id' => $sale->id,
                    'method' => PaymentMethod::from($payment['method']),
                    'gateway' => $payment['method'] === 'card' ? 'manual' : null,
                    'gateway_ref' => $payment['gateway_ref'] ?? null,
                    'status' => PaymentStatus::Captured,
                    'amount_cents' => $payment['amount_cents'],
                    'paid_at' => now(),
                ]);
            }

            return $sale->refresh();
        });
    }

    public function recordOrderSale(\App\Models\Order $order): Sale
    {
        return DB::transaction(function () use ($order): Sale {
            $sale = Sale::query()->create([
                'business_id' => $order->business_id,
                'number' => $this->nextNumber($order->business),
                'channel' => SaleChannel::App,
                'customer_id' => $order->customer_id,
                'order_id' => $order->id,
                'subtotal_cents' => $order->subtotal_cents,
                'discount_cents' => $order->discount_cents,
                'tax_cents' => $order->tax_cents,
                'total_cents' => $order->total_cents,
                'cogs_cents' => $order->cogs_cents,
                'status' => SaleStatus::Completed,
                'sold_at' => now(),
            ]);

            foreach ($order->items as $item) {
                $saleItem = SaleItem::query()->create([
                    'business_id' => $order->business_id,
                    'sale_id' => $sale->id,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'name' => $item->name,
                    'qty' => $item->qty,
                    'unit' => $item->unit,
                    'unit_price_cents' => $item->unit_price_cents,
                    'unit_cost_cents' => $item->unit_cost_cents,
                    'discount_cents' => 0,
                    'tax_cents' => 0,
                    'total_cents' => $item->total_cents,
                ]);

                foreach ($item->modifiers as $modifier) {
                    SaleItemModifier::query()->create([
                        'business_id' => $order->business_id,
                        'sale_item_id' => $saleItem->id,
                        'modifier_id' => $modifier->modifier_id,
                        'group_name' => $modifier->group_name,
                        'name' => $modifier->name,
                        'price_delta_cents' => $modifier->price_delta_cents,
                    ]);
                }
            }

            Payment::query()->create([
                'business_id' => $order->business_id,
                'payable_type' => $sale->getMorphClass(),
                'payable_id' => $sale->id,
                'method' => PaymentMethod::Online,
                'gateway' => 'stripe',
                'gateway_ref' => $order->payment_intent_id,
                'status' => PaymentStatus::Captured,
                'amount_cents' => $order->total_cents,
                'paid_at' => now(),
            ]);

            $order->update(['sale_id' => $sale->id]);

            return $sale;
        });
    }

    /**
     * @param  array<int, array{sale_item_id: int, qty: float|int}>  $lines
     */
    public function refund(Sale $sale, array $lines, ?string $reason = null, ?int $userId = null): Refund    {
        if (! $sale->isRefundable()) {
            throw new InvalidArgumentException(__('This sale cannot be refunded.'));
        }

        return DB::transaction(function () use ($sale, $lines, $reason, $userId): Refund {
            /** @var Sale $locked */
            $locked = Sale::query()->lockForUpdate()->findOrFail($sale->id);

            $amount = 0;
            $restock = [];

            $alreadyRefunded = [];

            foreach (Refund::query()->where('sale_id', $locked->id)->get() as $existingRefund) {
                foreach ((array) $existingRefund->items as $refundedLine) {
                    $itemId = (int) ($refundedLine['sale_item_id'] ?? 0);
                    $alreadyRefunded[$itemId] = ($alreadyRefunded[$itemId] ?? 0) + (float) ($refundedLine['qty'] ?? 0);
                }
            }

            foreach ($lines as $line) {
                /** @var SaleItem $item */
                $item = SaleItem::query()
                    ->where('sale_id', $locked->id)
                    ->findOrFail($line['sale_item_id']);

                $qty = (float) $line['qty'];
                $remaining = (float) $item->qty - ($alreadyRefunded[$item->id] ?? 0);

                if ($qty <= 0 || $qty > $remaining) {
                    throw new InvalidArgumentException(__('Invalid refund quantity.'));
                }

                $amount += (int) round($item->unit_price_cents * $qty);
                $restock[] = [$item, $qty];
            }

            $refund = Refund::query()->create([
                'business_id' => $locked->business_id,
                'sale_id' => $locked->id,
                'amount_cents' => $amount,
                'reason' => $reason,
                'user_id' => $userId,
                'refunded_at' => now(),
                'status' => 'completed',
                'items' => $lines,
            ]);

            foreach ($restock as [$item, $qty]) {
                $variant = ProductVariant::query()->find($item->product_variant_id);

                if ($variant !== null && $variant->product?->track_stock) {
                    $this->stock->restock($variant, $qty, $refund, $userId);
                }
            }

            $refundedTotal = (int) Refund::query()->where('sale_id', $locked->id)->sum('amount_cents');
            $locked->update([
                'status' => $refundedTotal >= (int) $locked->total_cents
                    ? SaleStatus::Refunded
                    : SaleStatus::PartiallyRefunded,
            ]);

            return $refund;
        });
    }

    private function nextNumber(Business $business): string
    {
        $prefix = 'S-'.now()->format('Ymd').'-';

        $count = Sale::query()
            ->withoutGlobalScope('business')
            ->where('business_id', $business->id)
            ->where('number', 'like', $prefix.'%')
            ->count();

        return $prefix.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
