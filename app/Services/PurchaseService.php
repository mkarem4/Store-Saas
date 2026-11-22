<?php

namespace App\Services;

use App\Models\Business;
use App\Models\ProductVariant;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Goods receiving (M2.3): unit cost is mandatory (D9) and updates the
 * variant's weighted-average cost through StockService::receive().
 */
class PurchaseService
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * @param  array<int, array{variant_id: int, qty: float|int|string, unit_cost_cents: int}>  $lines
     */
    public function receive(
        Business $business,
        array $lines,
        ?int $supplierId = null,
        ?string $reference = null,
        ?Carbon $receivedAt = null,
        ?string $notes = null,
        ?int $userId = null,
    ): PurchaseReceipt {
        if ($lines === []) {
            throw new InvalidArgumentException(__('Add at least one item.'));
        }

        return DB::transaction(function () use ($business, $lines, $supplierId, $reference, $receivedAt, $notes, $userId): PurchaseReceipt {
            $receipt = PurchaseReceipt::query()->create([
                'business_id' => $business->id,
                'supplier_id' => $supplierId,
                'user_id' => $userId,
                'reference' => $reference,
                'received_at' => $receivedAt ?? now(),
                'notes' => $notes,
            ]);

            $total = 0;

            foreach ($lines as $line) {
                $variant = ProductVariant::query()
                    ->withoutGlobalScope('business')
                    ->where('business_id', $business->id)
                    ->findOrFail($line['variant_id']);

                $qty = (float) $line['qty'];
                $unitCost = (int) $line['unit_cost_cents'];

                if ($qty <= 0) {
                    throw new InvalidArgumentException(__('Quantity must be positive.'));
                }

                if (! $variant->unit->allowsDecimalQuantity() && floor($qty) !== $qty) {
                    throw new InvalidArgumentException(__(':name is sold in whole units.', ['name' => $variant->displayName()]));
                }

                if ($unitCost <= 0) {
                    throw new InvalidArgumentException(__('Enter the purchase cost for :name.', ['name' => $variant->displayName()]));
                }

                $lineTotal = (int) round($qty * $unitCost);
                $total += $lineTotal;

                PurchaseReceiptItem::query()->create([
                    'business_id' => $business->id,
                    'purchase_receipt_id' => $receipt->id,
                    'product_variant_id' => $variant->id,
                    'qty' => $qty,
                    'unit_cost_cents' => $unitCost,
                    'total_cost_cents' => $lineTotal,
                ]);

                $this->stock->receive($variant, $qty, $unitCost, $receipt, $userId);
            }

            $receipt->update(['total_cost_cents' => $total]);

            return $receipt->refresh();
        });
    }
}
