<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PayType: string implements HasLabel
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

    public function getLabel(): string
    {
        return $this->label();
    }
}
