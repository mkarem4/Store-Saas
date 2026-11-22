<?php

namespace App\Enums;

enum VehicleStatus: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Sold = 'sold';
    case Maintenance = 'maintenance';
    case Rented = 'rented';

    public function label(): string
    {
        return match ($this) {
            self::Available => __('Available'),
            self::Reserved => __('Reserved'),
            self::Sold => __('Sold'),
            self::Maintenance => __('Maintenance'),
            self::Rented => __('Rented'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::Reserved, self::Rented => 'info',
            self::Sold => 'gray',
            self::Maintenance => 'warning',
        };
    }
}
