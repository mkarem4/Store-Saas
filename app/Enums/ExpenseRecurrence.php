<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ExpenseRecurrence: string implements HasLabel
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function getLabel(): string
    {
        return match ($this) {
            self::Weekly => __('Week'),
            self::Monthly => __('Month'),
            self::Yearly => __('Year'),
        };
    }
}
