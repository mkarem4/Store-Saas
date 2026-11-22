<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StockService
{
    public function receive(
        ProductVariant $variant,
        float $qty,
        int $unitCostCents,
        ?Model $reference = null,
        ?int $userId = null,
        ?string $notes = null,
    ): StockMovement {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Quantity must be positive.');
        }

        return DB::transaction(function () use ($variant, $qty, $unitCostCents, $reference, $userId, $notes): StockMovement {
            /** @var ProductVariant $locked */
            $locked = ProductVariant::query()->lockForUpdate()->findOrFail($variant->getKey());

            $oldQty = (float) $locked->stock_qty;
            $oldCost = (int) $locked->cost_price_cents;
            $newQty = $oldQty + $qty;

            $weightedCost = $newQty > 0
                ? (int) round((($oldQty * $oldCost) + ($qty * $unitCostCents)) / $newQty)
                : $unitCostCents;

            $locked->update([
                'stock_qty' => $newQty,
                'cost_price_cents' => $weightedCost,
            ]);

            return $this->record(
                businessId: (int) $locked->business_id,
                variantId: (int) $locked->getKey(),
                type: StockMovementType::Purchase,
                qty: $qty,
                unitCostCents: $unitCostCents,
                reference: $reference,
                userId: $userId,
                notes: $notes,
            );
        });
    }

    public function deduct(
        ProductVariant $variant,
        float $qty,
        StockMovementType $type,
        ?Model $reference = null,
        ?int $userId = null,
        ?string $notes = null,
        bool $allowNegative = false,
    ): StockMovement {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Quantity must be positive.');
        }

        return DB::transaction(function () use ($variant, $qty, $type, $reference, $userId, $notes, $allowNegative): StockMovement {
            /** @var ProductVariant $locked */
            $locked = ProductVariant::query()->lockForUpdate()->findOrFail($variant->getKey());

            $available = (float) $locked->stock_qty - (float) $locked->reserved_qty;

            if (! $allowNegative && $available < $qty) {
                throw new InvalidArgumentException(__('Not enough stock for :name.', ['name' => $locked->displayName()]));
            }

            $locked->update([
                'stock_qty' => (float) $locked->stock_qty - $qty,
            ]);

            return $this->record(
                businessId: (int) $locked->business_id,
                variantId: (int) $locked->getKey(),
                type: $type,
                qty: -$qty,
                unitCostCents: (int) $locked->cost_price_cents,
                reference: $reference,
                userId: $userId,
                notes: $notes,
            );
        });
    }

    public function restock(
        ProductVariant $variant,
        float $qty,
        ?Model $reference = null,
        ?int $userId = null,
        ?string $notes = null,
    ): StockMovement {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Quantity must be positive.');
        }

        return DB::transaction(function () use ($variant, $qty, $reference, $userId, $notes): StockMovement {
            /** @var ProductVariant $locked */
            $locked = ProductVariant::query()->lockForUpdate()->findOrFail($variant->getKey());

            $locked->update([
                'stock_qty' => (float) $locked->stock_qty + $qty,
            ]);

            return $this->record(
                businessId: (int) $locked->business_id,
                variantId: (int) $locked->getKey(),
                type: StockMovementType::Return,
                qty: $qty,
                unitCostCents: (int) $locked->cost_price_cents,
                reference: $reference,
                userId: $userId,
                notes: $notes,
            );
        });
    }

    public function adjust(
        ProductVariant $variant,
        float $newQty,
        ?int $userId = null,
        ?string $notes = null,
    ): StockMovement {
        return DB::transaction(function () use ($variant, $newQty, $userId, $notes): StockMovement {
            /** @var ProductVariant $locked */
            $locked = ProductVariant::query()->lockForUpdate()->findOrFail($variant->getKey());
            $delta = $newQty - (float) $locked->stock_qty;

            $locked->update(['stock_qty' => $newQty]);

            return $this->record(
                businessId: (int) $locked->business_id,
                variantId: (int) $locked->getKey(),
                type: StockMovementType::Adjustment,
                qty: $delta,
                unitCostCents: (int) $locked->cost_price_cents,
                reference: null,
                userId: $userId,
                notes: $notes,
            );
        });
    }

    public function reserve(ProductVariant $variant, float $qty): void
    {
        DB::transaction(function () use ($variant, $qty): void {
            /** @var ProductVariant $locked */
            $locked = ProductVariant::query()->lockForUpdate()->findOrFail($variant->getKey());

            if ($locked->availableQty() < $qty) {
                throw new InvalidArgumentException(__('Not enough stock for :name.', ['name' => $locked->displayName()]));
            }

            $locked->increment('reserved_qty', $qty);
        });
    }

    public function release(ProductVariant $variant, float $qty): void
    {
        DB::transaction(function () use ($variant, $qty): void {
            /** @var ProductVariant $locked */
            $locked = ProductVariant::query()->lockForUpdate()->findOrFail($variant->getKey());

            $locked->update([
                'reserved_qty' => max(0, (float) $locked->reserved_qty - $qty),
            ]);
        });
    }

    public function commitReservation(
        ProductVariant $variant,
        float $qty,
        ?Model $reference = null,
        ?int $userId = null,
    ): StockMovement {
        return DB::transaction(function () use ($variant, $qty, $reference, $userId): StockMovement {
            /** @var ProductVariant $locked */
            $locked = ProductVariant::query()->lockForUpdate()->findOrFail($variant->getKey());

            $locked->update([
                'stock_qty' => (float) $locked->stock_qty - $qty,
                'reserved_qty' => max(0, (float) $locked->reserved_qty - $qty),
            ]);

            return $this->record(
                businessId: (int) $locked->business_id,
                variantId: (int) $locked->getKey(),
                type: StockMovementType::Sale,
                qty: -$qty,
                unitCostCents: (int) $locked->cost_price_cents,
                reference: $reference,
                userId: $userId,
                notes: null,
            );
        });
    }

    private function record(
        int $businessId,
        int $variantId,
        StockMovementType $type,
        float $qty,
        int $unitCostCents,
        ?Model $reference,
        ?int $userId,
        ?string $notes,
    ): StockMovement {
        return StockMovement::query()->create([
            'business_id' => $businessId,
            'product_variant_id' => $variantId,
            'type' => $type,
            'qty' => $qty,
            'unit_cost_cents' => $unitCostCents,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'user_id' => $userId,
            'notes' => $notes,
            'occurred_at' => now(),
        ]);
    }
}
