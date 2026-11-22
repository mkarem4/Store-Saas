<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Authorized = 'authorized';
    case Captured = 'captured';
    case Voided = 'voided';
    case Refunded = 'refunded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Awaiting payment'),
            self::Authorized => __('Payment held'),
            self::Captured => __('Paid'),
            self::Voided => __('Hold released'),
            self::Refunded => __('Refunded'),
            self::Failed => __('Payment failed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Authorized => 'info',
            self::Captured => 'success',
            self::Voided, self::Failed => 'danger',
            self::Refunded => 'gray',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return $this->color();
    }
}
