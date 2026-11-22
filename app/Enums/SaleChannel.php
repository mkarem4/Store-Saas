<?php

namespace App\Enums;

enum SaleChannel: string
{
    case Pos = 'pos';
    case App = 'app';
    case Vehicle = 'vehicle';
    case Rental = 'rental';

    public function label(): string
    {
        return match ($this) {
            self::Pos => __('POS'),
            self::App => __('App order'),
            self::Vehicle => __('Vehicle sale'),
            self::Rental => __('Rental'),
        };
    }
}
