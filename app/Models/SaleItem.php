<?php

namespace App\Models;

use App\Enums\ProductUnit;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleItem extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'business_id',
        'sale_id',
        'product_id',
        'product_variant_id',
        'name',
        'qty',
        'unit',
        'unit_price_cents',
        'unit_cost_cents',
        'discount_cents',
        'tax_cents',
        'total_cents',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'unit' => ProductUnit::class,
            'qty' => 'decimal:3',
            'meta' => 'array',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function modifiers(): HasMany
    {
        return $this->hasMany(SaleItemModifier::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
