<?php

namespace App\Enums;

enum RentalStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
        };
    }
}
