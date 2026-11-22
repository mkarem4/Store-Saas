<?php

namespace App\Models;

use App\Enums\SaleChannel;
use App\Enums\SaleStatus;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Sale extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'business_id',
        'number',
        'channel',
        'employee_id',
        'client_id',
        'customer_id',
        'order_id',
        'subtotal_cents',
        'discount_cents',
        'tax_rate',
        'tax_cents',
        'total_cents',
        'cogs_cents',
        'status',
        'sold_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'channel' => SaleChannel::class,
            'status' => SaleStatus::class,
            'sold_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isRefundable(): bool
    {
        return in_array($this->status, [SaleStatus::Completed, SaleStatus::PartiallyRefunded], true);
    }
}
