<?php

namespace App\Models;

use App\Enums\JewelryPricingMode;
use App\Enums\ProductUnit;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProductVariant extends Model
{
    use BelongsToBusiness;
    use HasFactory;
    use HasTranslations;

    protected $fillable = [
        'business_id',
        'product_id',
        'name',
        'sku',
        'barcode',
        'unit',
        'cost_price_cents',
        'wholesale_price_cents',
        'retail_price_cents',
        'stock_qty',
        'reserved_qty',
        'low_stock_threshold',
        'expiry_date',
        'is_default',
        'attributes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'unit' => ProductUnit::class,
            'stock_qty' => 'decimal:3',
            'reserved_qty' => 'decimal:3',
            'low_stock_threshold' => 'decimal:3',
            'expiry_date' => 'date',
            'is_default' => 'boolean',
            'attributes' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function jewelryAttribute(): HasOne
    {
        return $this->hasOne(JewelryAttribute::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function inventoryUnits(): HasMany
    {
        return $this->hasMany(InventoryUnit::class);
    }

    public function availableQty(): float
    {
        return max(0, (float) $this->stock_qty - (float) $this->reserved_qty);
    }

    public function isLowStock(): bool
    {
        if ($this->low_stock_threshold === null) {
            return false;
        }

        return (float) $this->stock_qty <= (float) $this->low_stock_threshold;
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        return $this->expiry_date !== null
            && $this->expiry_date->isFuture()
            && $this->expiry_date->lessThanOrEqualTo(now()->addDays($days));
    }

    public function priceFor(bool $wholesale = false): int
    {
        if ($wholesale && $this->wholesale_price_cents > 0) {
            return $this->wholesale_price_cents;
        }

        $jewelry = $this->jewelryAttribute;

        if ($jewelry?->pricing_mode === JewelryPricingMode::ByWeight) {
            return $jewelry->calculatedPriceCents($this->business_id);
        }

        return $this->retail_price_cents;
    }

    public function displayName(): string
    {
        $name = $this->translated('name');

        return $name !== '' ? $name : $this->product->translatedName();
    }
}
