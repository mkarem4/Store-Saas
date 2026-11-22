<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PayoutMethodType: string implements HasLabel
{
    case BankTransfer = 'bank_transfer';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => __('Bank transfer'),
            self::Other => __('Other'),
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
