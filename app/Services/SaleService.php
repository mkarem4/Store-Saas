<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SaleChannel;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Models\Business;
use App\Models\Modifier;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleItemModifier;
use App\Support\PlatformConfig;
use App\Support\TaxCalculator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SaleService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly PaymentGateway $gateway,
        private readonly WalletService $wallet,
    ) {}

    /**
     * POS sale. Tax is added on top by the store's region (D6/M3.12).
     *
     * @param  array<int, array{variant_id: int, qty: float|int, unit_price_cents?: int|null, discount_cents?: int, modifier_ids?: array<int, int>, wholesale?: bool}>  $items
     * @param  array<int, array{method: string, amount_cents: int, gateway_ref?: string|null, meta?: array<string, mixed>|null}>  $payments
     * @param  bool  $canOverridePrice  only owners may change prices (rule 6)
     * @param  bool  $canSellWholesale  `sell_wholesale` permission (D8)
     */
    public function createPosSale(
        Business $business,
        array $items,
        array $payments,
        ?int $employeeId = null,
        ?int $clientId = null,
        ?int $userId = null,
        ?string $notes = null,
        bool $canOverridePrice = false,
        bool $canSellWholesale = false,
    ): Sale {
        if ($items === []) {
            throw new InvalidArgumentException(__('Add at least one item to the sale.'));
        }

        return DB::transaction(function () use ($business, $items, $payments, $employeeId, $clientId, $userId, $notes, $canOverridePrice, $canSellWholesale): Sale {
            $allowNegative = (bool) $business->settings('allow_negative_stock', false);

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

                if (! $variant->unit->allowsDecimalQuantity() && floor($qty) !== $qty) {
                    throw new InvalidArgumentException(__(':name is sold in whole units.', ['name' => $variant->displayName()]));
                }

                $wholesale = (bool) ($item['wholesale'] ?? false);

                if ($wholesale && ! $canSellWholesale) {
                    throw new InvalidArgumentException(__('You are not allowed to sell at wholesale prices.'));
                }

                $listPrice = $variant->priceFor($wholesale);
                $unitPrice = $item['unit_price_cents'] ?? $listPrice;

                if ((int) $unitPrice !== $listPrice && ! $canOverridePrice) {
                    throw new InvalidArgumentException(__('You are not allowed to change prices.'));
                }

                $lineDiscount = (int) ($item['discount_cents'] ?? 0);

                $modifierIds = $item['modifier_ids'] ?? [];
                $modifiers = $modifierIds === []
                    ? collect()
                    : Modifier::query()
                        ->where('business_id', $business->id)
                        ->whereHas('group', fn ($query) => $query->where('product_id', $variant->product_id))
                        ->with('group')
                        ->whereIn('id', $modifierIds)
                        ->get();

                $modifierDelta = (int) $modifiers->sum('price_delta_cents');
                $lineSubtotal = (int) round(((int) $unitPrice + $modifierDelta) * $qty);
                $lineDiscount = min($lineDiscount, $lineSubtotal);
                $lineTotal = $lineSubtotal - $lineDiscount;

                $subtotal += $lineSubtotal;
                $discount += $lineDiscount;
                $cogs += (int) round($variant->cost_price_cents * $qty);

                $lines[] = [
                    'variant' => $variant,
                    'qty' => $qty,
                    'unit_price_cents' => (int) $unitPrice,
                    'unit_cost_cents' => (int) $variant->cost_price_cents,
                    'discount_cents' => $lineDiscount,
                    'total_cents' => $lineTotal,
                    'modifiers' => $modifiers,
                    'wholesale' => $wholesale,
                ];
            }

            $taxable = $subtotal - $discount;
            $taxRate = TaxCalculator::appliesToPos() ? TaxCalculator::rateFor($business) : 0.0;
            $tax = TaxCalculator::taxCents($taxable, $taxRate);
            $lineTaxes = TaxCalculator::allocate($tax, array_column($lines, 'total_cents'));
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
                'tax_rate' => $taxRate,
                'tax_cents' => $tax,
                'total_cents' => $total,
                'cogs_cents' => $cogs,
                'status' => SaleStatus::Completed,
                'sold_at' => now(),
                'notes' => $notes,
            ]);

            foreach ($lines as $index => $line) {
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
                    'tax_cents' => $lineTaxes[$index],
                    'total_cents' => $line['total_cents'],
                    'meta' => $line['wholesale'] ? ['wholesale' => true] : null,
                ]);

                $this->copyModifiers($saleItem, $line['modifiers']->map(fn (Modifier $modifier): array => [
                    'modifier_id' => $modifier->id,
                    'group_name' => $modifier->group?->translated('name'),
                    'name' => $modifier->translated('name'),
                    'price_delta_cents' => $modifier->price_delta_cents,
                ])->all());

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
                    'meta' => $payment['meta'] ?? null,
                ]);
            }

            return $sale->refresh();
        });
    }

    /**
     * Turn a completed app order into a sale (M7.4). Delivery fee stays on the
     * sale total as store revenue; tax is allocated to the product lines.
     */
    public function recordOrderSale(Order $order): Sale
    {
        return DB::transaction(function () use ($order): Sale {
            $existing = Sale::query()->withoutGlobalScope('business')->where('order_id', $order->id)->first();

            if ($existing !== null) {
                return $existing;
            }

            $items = $order->items()->with('modifiers')->get();
            $lineTaxes = TaxCalculator::allocate((int) $order->tax_cents, $items->pluck('total_cents', 'id')->map(fn ($value): int => (int) $value)->all());

            $sale = Sale::query()->create([
                'business_id' => $order->business_id,
                'number' => $this->nextNumber($order->business),
                'channel' => SaleChannel::App,
                'customer_id' => $order->customer_id,
                'order_id' => $order->id,
                'subtotal_cents' => $order->subtotal_cents,
                'discount_cents' => $order->discount_cents,
                'tax_rate' => $order->tax_rate,
                'tax_cents' => $order->tax_cents,
                'total_cents' => $order->total_cents,
                'cogs_cents' => $order->cogs_cents,
                'status' => SaleStatus::Completed,
                'sold_at' => $order->completed_at ?? now(),
            ]);

            foreach ($items as $item) {
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
                    'tax_cents' => $lineTaxes[$item->id] ?? 0,
                    'total_cents' => $item->total_cents,
                ]);

                $this->copyModifiers($saleItem, $item->modifiers->map(fn ($modifier): array => [
                    'modifier_id' => $modifier->modifier_id,
                    'group_name' => $modifier->group_name,
                    'name' => $modifier->name,
                    'price_delta_cents' => $modifier->price_delta_cents,
                ])->all());
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
                'fee_cents' => $order->gateway_fee_cents,
                'paid_at' => $order->completed_at ?? now(),
            ]);

            $order->update(['sale_id' => $sale->id]);

            return $sale;
        });
    }

    /**
     * Partial or full return (owner only, D24). The refunded amount includes
     * the line's share of tax. App-order returns are refunded to the original
     * card and debited from the store's wallet with the commission reversed.
     *
     * @param  array<int, array{sale_item_id: int, qty: float|int}>  $lines
     */
    public function refund(Sale $sale, array $lines, ?string $reason = null, ?int $userId = null, bool $restock = true): Refund
    {
        if (! $sale->isRefundable()) {
            throw new InvalidArgumentException(__('This sale cannot be refunded.'));
        }

        if ($lines === []) {
            throw new InvalidArgumentException(__('Select at least one item to refund.'));
        }

        return DB::transaction(function () use ($sale, $lines, $reason, $userId, $restock): Refund {
            /** @var Sale $locked */
            $locked = Sale::query()->withoutGlobalScope('business')->lockForUpdate()->findOrFail($sale->id);

            $alreadyRefunded = [];

            foreach (Refund::query()->withoutGlobalScope('business')->where('sale_id', $locked->id)->get() as $existingRefund) {
                foreach ((array) $existingRefund->items as $refundedLine) {
                    $itemId = (int) ($refundedLine['sale_item_id'] ?? 0);
                    $alreadyRefunded[$itemId] = ($alreadyRefunded[$itemId] ?? 0) + (float) ($refundedLine['qty'] ?? 0);
                }
            }

            $net = 0;
            $tax = 0;
            $cogs = 0;
            $restockLines = [];

            foreach ($lines as $line) {
                /** @var SaleItem $item */
                $item = SaleItem::query()
                    ->withoutGlobalScope('business')
                    ->where('sale_id', $locked->id)
                    ->findOrFail($line['sale_item_id']);

                $qty = (float) $line['qty'];
                $soldQty = (float) $item->qty;
                $remaining = $soldQty - ($alreadyRefunded[$item->id] ?? 0);

                if ($qty <= 0 || $qty > $remaining + 0.0005) {
                    throw new InvalidArgumentException(__('Invalid refund quantity.'));
                }

                $share = $qty / $soldQty;
                $net += (int) round($item->total_cents * $share);
                $tax += (int) round($item->tax_cents * $share);
                $cogs += (int) round($item->unit_cost_cents * $qty);
                $restockLines[] = [$item, $qty];
            }

            $amount = $net + $tax;
            $order = $locked->order_id !== null
                ? Order::query()->withoutGlobalScope('business')->find($locked->order_id)
                : null;

            $gatewayRef = $order !== null ? $this->gateway->refund($order, $amount) : null;

            $refund = Refund::query()->create([
                'business_id' => $locked->business_id,
                'sale_id' => $locked->id,
                'order_id' => $locked->order_id,
                'amount_cents' => $amount,
                'tax_cents' => $tax,
                'cogs_cents' => $cogs,
                'reason' => $reason,
                'user_id' => $userId,
                'refunded_at' => now(),
                'gateway_ref' => $gatewayRef,
                'status' => 'completed',
                'items' => $lines,
            ]);

            if ($restock) {
                foreach ($restockLines as [$item, $qty]) {
                    $variant = ProductVariant::query()
                        ->withoutGlobalScope('business')
                        ->with('product')
                        ->find($item->product_variant_id);

                    if ($variant !== null && $variant->product?->track_stock) {
                        $this->stock->restock($variant, $qty, $refund, $userId);
                    }
                }
            }

            if ($order !== null) {
                $commissionReversal = PlatformConfig::commissionCents($net);
                $this->wallet->debitRefund($order, $amount, min($commissionReversal, (int) $order->platform_fee_cents));

                $refundedTotal = (int) $order->refunded_cents + $amount;
                $order->update([
                    'refunded_cents' => $refundedTotal,
                    'payment_status' => $refundedTotal >= (int) $order->total_cents ? PaymentStatus::Refunded : $order->payment_status,
                ]);
            }

            $refundedTotal = (int) Refund::query()->withoutGlobalScope('business')->where('sale_id', $locked->id)->sum('amount_cents');
            $fullyReturned = collect($locked->items()->withoutGlobalScope('business')->get())
                ->every(function (SaleItem $item) use ($alreadyRefunded, $lines): bool {
                    $now = collect($lines)->where('sale_item_id', $item->id)->sum('qty');

                    return ($alreadyRefunded[$item->id] ?? 0) + $now >= (float) $item->qty - 0.0005;
                });

            $locked->update([
                'status' => $fullyReturned || $refundedTotal >= (int) $locked->total_cents
                    ? SaleStatus::Refunded
                    : SaleStatus::PartiallyRefunded,
            ]);

            return $refund;
        });
    }

    /**
     * @param  array<int, array{modifier_id: int|null, group_name: string|null, name: string, price_delta_cents: int}>  $modifiers
     */
    private function copyModifiers(SaleItem $saleItem, array $modifiers): void
    {
        foreach ($modifiers as $modifier) {
            SaleItemModifier::query()->create([
                'business_id' => $saleItem->business_id,
                'sale_item_id' => $saleItem->id,
                ...$modifier,
            ]);
        }
    }

    private function nextNumber(Business $business): string
    {
        return app(SaleNumber::class)->next($business->id);
    }
}
