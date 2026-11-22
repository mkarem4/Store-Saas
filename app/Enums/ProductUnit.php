<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ProductUnit: string implements HasLabel
{
    case Piece = 'piece';
    case Carton = 'carton';
    case Kilogram = 'kg';

    public function label(): string
    {
        return match ($this) {
            self::Piece => __('Piece'),
            self::Carton => __('Carton'),
            self::Kilogram => __('Kilogram'),
        };
    }

    public function allowsDecimalQuantity(): bool
    {
        return $this === self::Kilogram;
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
