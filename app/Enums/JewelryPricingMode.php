<?php

namespace App\Enums;

enum JewelryPricingMode: string
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
}
