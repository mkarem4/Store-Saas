<?php

namespace App\Filament\Business;

use Illuminate\Support\Carbon;

/**
 * Turns the reports page filters into a local-date range (M6.1).
 */
final class ReportPeriod
{
    /**
     * @param  array<string, mixed>|null  $filters
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function from(?array $filters): array
    {
        $now = now(BusinessPanel::business()?->timezone);
        $preset = $filters['preset'] ?? 'month';

        [$from, $until] = match ($preset) {
            'today' => [$now->copy(), $now->copy()],
            'week' => [$now->copy()->startOfWeek(), $now->copy()],
            'custom' => [
                Carbon::parse($filters['from'] ?? $now->copy()->startOfMonth()),
                Carbon::parse($filters['until'] ?? $now),
            ],
            default => [$now->copy()->startOfMonth(), $now->copy()],
        };

        return $from->greaterThan($until) ? [$until, $from] : [$from, $until];
    }

    /**
     * The same number of days right before the period, for comparisons.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function previous(Carbon $from, Carbon $until): array
    {
        $days = (int) $from->copy()->startOfDay()->diffInDays($until->copy()->startOfDay()) + 1;

        return [$from->copy()->subDays($days), $from->copy()->subDay()];
    }
}
