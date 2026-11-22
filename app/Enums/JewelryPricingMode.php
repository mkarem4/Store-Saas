<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum JewelryPricingMode: string implements HasLabel
{
    case Fixed = 'fixed';
    case ByWeight = 'by_weight';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => __('Fixed price'),
            self::ByWeight => __('By weight'),
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
