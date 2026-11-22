<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Preparing = 'preparing';
    case ReadyForPickup = 'ready_for_pickup';
    case OutForDelivery = 'out_for_delivery';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Accepted => __('Accepted'),
            self::Preparing => __('Preparing'),
            self::ReadyForPickup => __('Ready for pickup'),
            self::OutForDelivery => __('Out for delivery'),
            self::Completed => __('Completed'),
            self::Rejected => __('Rejected'),
            self::Cancelled => __('Cancelled'),
            self::Expired => __('Expired'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Accepted, self::Preparing => 'info',
            self::ReadyForPickup, self::OutForDelivery => 'primary',
            self::Completed => 'success',
            self::Rejected, self::Expired => 'danger',
            self::Cancelled => 'gray',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Rejected, self::Cancelled, self::Expired], true);
    }

    public function canBeCancelledByCustomer(): bool
    {
        return $this === self::Pending;
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
