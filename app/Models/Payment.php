<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'business_id',
        'payable_type',
        'payable_id',
        'method',
        'gateway',
        'gateway_ref',
        'status',
        'amount_cents',
        'fee_cents',
        'paid_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'paid_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }
}
