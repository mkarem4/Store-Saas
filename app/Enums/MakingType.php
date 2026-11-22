<?php

namespace App\Enums;

enum MakingType: string
{
    case Fixed = 'fixed';
    case Percentage = 'percentage';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => __('Fixed amount'),
            self::Percentage => __('Percentage'),
        };
    }
}
