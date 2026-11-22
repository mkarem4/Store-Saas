<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case Owner = 'owner';
    case BusinessOwner = 'business_owner';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Owner => __('Platform owner'),
            self::BusinessOwner => __('Business owner'),
            self::Staff => __('Staff'),
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
