<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum RentalStatus: string implements HasColor, HasLabel
{
    case Reserved = 'reserved';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Reserved => __('Reserved'),
            self::Active => __('Active'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Reserved => 'warning',
            self::Active => 'info',
            self::Completed => 'success',
            self::Cancelled => 'gray',
        };
    }

    /**
     * Contracts that block the vehicle for their period (rule 4).
     *
     * @return array<int, self>
     */
    public static function blocking(): array
    {
        return [self::Reserved, self::Active];
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
