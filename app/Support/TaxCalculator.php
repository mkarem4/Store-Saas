<?php

namespace App\Support;

final class TaxCalculator
{
    public static function rateFor(?int $businessId = null): float
    {
        if ($businessId !== null) {
            $business = \App\Models\Business::query()->find($businessId);

            if ($business !== null) {
                return $business->taxRate();
            }
        }

        return (float) \App\Models\PlatformSetting::get('tax_rate', 15);
    }

    public static function taxCents(int $subtotalCents, ?int $businessId = null): int
    {
        $rate = self::rateFor($businessId);

        return (int) round($subtotalCents * $rate / 100);
    }
}
