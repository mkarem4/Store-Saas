<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum StockMovementType: string implements HasLabel
{
    case Purchase = 'purchase';
    case Sale = 'sale';
    case Return = 'return';
    case Adjustment = 'adjustment';
    case Damage = 'damage';
    case Reservation = 'reservation';
    case Release = 'release';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => __('Purchase'),
            self::Sale => __('Sale'),
            self::Return => __('Return'),
            self::Adjustment => __('Adjustment'),
            self::Damage => __('Damage'),
            self::Reservation => __('Reservation'),
            self::Release => __('Release'),
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
