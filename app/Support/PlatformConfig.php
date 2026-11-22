<?php

namespace App\Support;

use App\Models\PlatformSetting;

/**
 * Typed accessors for platform-wide settings managed from the owner panel.
 */
final class PlatformConfig
{
    /** Platform commission charged on app orders, in percent (D26: 3%). */
    public static function commissionRate(): float
    {
        return (float) PlatformSetting::get('commission_rate', 3);
    }

    /** Days a completed order's earnings stay pending before payout (G4: 7). */
    public static function walletHoldDays(): int
    {
        return (int) PlatformSetting::get('wallet_hold_days', 7);
    }

    public static function minimumPayoutCents(): int
    {
        return (int) PlatformSetting::get('minimum_payout_cents', 0);
    }

    public static function commissionCents(int $commissionableCents): int
    {
        return (int) round(max(0, $commissionableCents) * self::commissionRate() / 100);
    }
}
