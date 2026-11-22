<?php

namespace App\Models;

use App\Enums\FulfillmentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    public ?string $paymentClientSecret = null;

    protected $fillable = [
        'business_id',
        'customer_id',
        'number',
        'fulfillment',
        'status',
        'payment_status',
        'subtotal_cents',
        'delivery_fee_cents',
        'discount_cents',
        'tax_cents',
        'total_cents',
        'cogs_cents',
        'payment_intent_id',
        'idempotency_key',
        'delivery_address',
        'notes',
        'cancel_reason',
        'sale_id',
        'driver_id',
        'placed_at',
        'expires_at',
        'accepted_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'fulfillment' => FulfillmentType::class,
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'delivery_address' => 'array',
            'placed_at' => 'datetime',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->latest();
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function isPending(): bool
    {
        return $this->status === OrderStatus::Pending;
    }

    public function isExpired(): bool
    {
        return $this->isPending() && $this->expires_at->isPast();
    }
}
