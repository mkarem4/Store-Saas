<?php

namespace App\Enums;

enum SaleStatus: string
{
    case Completed = 'completed';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Completed => __('Completed'),
            self::PartiallyRefunded => __('Partially refunded'),
            self::Refunded => __('Refunded'),
            self::Void => __('Void'),
        };
    }
}
