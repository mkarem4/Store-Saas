<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Authorized = 'authorized';
    case Captured = 'captured';
    case Voided = 'voided';
    case Refunded = 'refunded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Authorized => __('Authorized'),
            self::Captured => __('Captured'),
            self::Voided => __('Voided'),
            self::Refunded => __('Refunded'),
            self::Failed => __('Failed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Authorized => 'info',
            self::Captured => 'success',
            self::Voided, self::Failed => 'danger',
            self::Refunded => 'gray',
        };
    }
}
