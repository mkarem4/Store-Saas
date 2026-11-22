<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'business_id',
        'sale_id',
        'order_id',
        'amount_cents',
        'tax_cents',
        'cogs_cents',
        'reason',
        'user_id',
        'refunded_at',
        'gateway_ref',
        'status',
        'items',
    ];

    protected function casts(): array
    {
        return [
            'refunded_at' => 'datetime',
            'items' => 'array',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
