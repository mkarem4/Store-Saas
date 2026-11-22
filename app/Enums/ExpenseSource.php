<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ExpenseSource: string implements HasLabel
{
    case Manual = 'manual';
    case Payroll = 'payroll';
    case Recurring = 'recurring';
    case GatewayFee = 'gateway_fee';
    case PlatformFee = 'platform_fee';

    public function label(): string
    {
        return match ($this) {
            self::Manual => __('Manual'),
            self::Payroll => __('Payroll'),
            self::Recurring => __('Recurring'),
            self::GatewayFee => __('Payment gateway fee'),
            self::PlatformFee => __('Platform commission'),
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
