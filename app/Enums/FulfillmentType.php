<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FulfillmentType: string implements HasLabel
{
    case Pickup = 'pickup';
    case Delivery = 'delivery';

    public function label(): string
    {
        return match ($this) {
            self::Pickup => __('Pickup'),
            self::Delivery => __('Delivery'),
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
