<?php

namespace App\Enums;

enum ProductUnit: string
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
}
