<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WalletTransactionStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Available = 'available';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Available => __('Available'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Available => 'success',
        };
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
