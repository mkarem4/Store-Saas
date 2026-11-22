<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class BusinessWallet extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'business_id',
        'pending_cents',
        'available_cents',
        'lifetime_earnings_cents',
        'lifetime_payouts_cents',
    ];

    protected function casts(): array
    {
        return [
            'pending_cents' => 'integer',
            'available_cents' => 'integer',
            'lifetime_earnings_cents' => 'integer',
            'lifetime_payouts_cents' => 'integer',
        ];
    }

    public function balanceCents(): int
    {
        return $this->pending_cents + $this->available_cents;
    }
}
