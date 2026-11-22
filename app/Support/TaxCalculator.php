<?php

namespace App\Support;

use App\Models\Business;
use App\Models\PlatformSetting;

final class TaxCalculator
{
    public static function rateFor(Business|int|null $business = null): float
    {
        if (is_int($business)) {
            $business = Business::query()->find($business);
        }

        if ($business instanceof Business) {
            return $business->taxRate();
        }

        return (float) PlatformSetting::get('default_tax_rate', PlatformSetting::get('tax_rate', 15));
    }

    /**
     * Tax is added on top of the product price (D6), never on delivery.
     */
    public static function taxCents(int $taxableCents, Business|int|float|null $businessOrRate = null): int
    {
        $rate = is_float($businessOrRate) ? $businessOrRate : self::rateFor($businessOrRate);

        return (int) round(max(0, $taxableCents) * $rate / 100);
    }

    /**
     * Whether POS sales carry tax. The client's 22/11 answer puts tax on both
     * POS and app orders (G2); the setting keeps the earlier answer reachable.
     */
    public static function appliesToPos(): bool
    {
        return PlatformSetting::get('tax_scope', 'all') === 'all';
    }

    /**
     * Split a tax amount across lines proportionally to their taxable value,
     * pushing the rounding remainder onto the largest line.
     *
     * @param  array<int|string, int>  $lineTaxables
     * @return array<int|string, int>
     */
    public static function allocate(int $taxCents, array $lineTaxables): array
    {
        $total = array_sum($lineTaxables);

        if ($lineTaxables === [] || $total <= 0 || $taxCents <= 0) {
            return array_map(fn (): int => 0, $lineTaxables);
        }

        $allocated = [];

        foreach ($lineTaxables as $key => $value) {
            $allocated[$key] = intdiv($taxCents * $value, $total);
        }

        $remainder = $taxCents - array_sum($allocated);

        if ($remainder !== 0) {
            $largest = array_keys($lineTaxables, max($lineTaxables), true)[0];
            $allocated[$largest] += $remainder;
        }

        return $allocated;
    }
}
