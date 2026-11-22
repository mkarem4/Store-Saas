<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MakingType: string implements HasLabel
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

    public function getLabel(): string
    {
        return $this->label();
    }
}
