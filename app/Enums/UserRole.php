<?php

namespace App\Enums;

enum UserRole: string
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
}
