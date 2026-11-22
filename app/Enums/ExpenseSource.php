<?php

namespace App\Enums;

enum ExpenseSource: string
{
    case Manual = 'manual';
    case Payroll = 'payroll';
    case Recurring = 'recurring';
    case GatewayFee = 'gateway_fee';

    public function label(): string
    {
        return match ($this) {
            self::Manual => __('Manual'),
            self::Payroll => __('Payroll'),
            self::Recurring => __('Recurring'),
            self::GatewayFee => __('Payment gateway fee'),
        };
    }
}
