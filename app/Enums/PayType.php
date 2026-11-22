<?php

namespace App\Enums;

enum PayType: string
{
    case Monthly = 'monthly';
    case Hourly = 'hourly';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => __('Monthly salary'),
            self::Hourly => __('Hourly rate'),
        };
    }
}
