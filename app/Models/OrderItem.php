<?php

namespace App\Models;

use App\Enums\ProductUnit;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'business_id',
        'order_id',
        'product_id',
        'product_variant_id',
        'name',
        'qty',
        'unit',
        'unit_price_cents',
        'unit_cost_cents',
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function modifiers(): HasMany
    {
        return $this->hasMany(OrderItemModifier::class);
    }
}
