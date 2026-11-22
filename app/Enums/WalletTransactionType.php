<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WalletTransactionType: string implements HasColor, HasLabel
{
    case Sale = 'sale';
    case PlatformFee = 'platform_fee';
    case GatewayFee = 'gateway_fee';
    case Refund = 'refund';
    case PlatformFeeReversal = 'platform_fee_reversal';
    case Payout = 'payout';
    case PayoutReversal = 'payout_reversal';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Sale => __('Sale'),
            self::PlatformFee => __('Platform commission'),
            self::GatewayFee => __('Payment fee'),
            self::Refund => __('Refund'),
            self::PlatformFeeReversal => __('Commission reversal'),
            self::Payout => __('Payout'),
            self::PayoutReversal => __('Payout reversal'),
            self::Adjustment => __('Adjustment'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Sale, self::PlatformFeeReversal, self::PayoutReversal => 'success',
            self::PlatformFee, self::GatewayFee, self::Refund => 'danger',
            self::Payout => 'info',
            self::Adjustment => 'gray',
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
