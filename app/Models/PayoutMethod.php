<?php

namespace App\Models;

use App\Enums\PayoutMethodType;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class PayoutMethod extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'business_id',
        'type',
        'label',
        'details',
        'is_default',
    ];

    protected $hidden = ['details'];

    protected function casts(): array
    {
        return [
            'type' => PayoutMethodType::class,
            'details' => 'encrypted:array',
            'is_default' => 'boolean',
        ];
    }

    /**
     * Masked summary safe to snapshot on a payout request and show in lists.
     */
    public function summary(): string
    {
        $details = $this->details ?? [];
        $account = (string) ($details['account_number'] ?? $details['iban'] ?? '');
        $masked = $account !== '' ? '••••'.substr($account, -4) : '';

        return trim($this->label.' '.$masked);
    }
}
