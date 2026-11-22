<?php

namespace App\Enums;

enum InquiryStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::New => __('New'),
            self::Contacted => __('Contacted'),
            self::Closed => __('Closed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::Contacted => 'info',
            self::Closed => 'gray',
        };
    }
}
