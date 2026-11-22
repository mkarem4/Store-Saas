<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum GoldPriceSource: string implements HasLabel
{
    case Auto = 'auto';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Auto => __('Automatic'),
            self::Manual => __('Manual'),
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
