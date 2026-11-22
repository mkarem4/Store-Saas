<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AttendanceMode: string implements HasLabel
{
    case Ip = 'ip';
    case Gps = 'gps';
    case Both = 'both';
    case Either = 'either';

    public function label(): string
    {
        return match ($this) {
            self::Ip => __('Store network (IP) only'),
            self::Gps => __('Store location (GPS) only'),
            self::Both => __('Network and location'),
            self::Either => __('Network or location'),
        };
    }

    public function requiresIp(): bool
    {
        return in_array($this, [self::Ip, self::Both], true);
    }

    public function requiresGps(): bool
    {
        return in_array($this, [self::Gps, self::Both], true);
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
