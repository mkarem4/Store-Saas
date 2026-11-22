<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SaleChannel: string implements HasColor, HasLabel
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

    public function color(): string
    {
        return match ($this) {
            self::Pos => 'primary',
            self::App => 'info',
            self::Vehicle => 'warning',
            self::Rental => 'success',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return $this->color();
    }
}
