<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class TaxRate extends Model
{
    protected $fillable = [
        'country',
        'state',
        'name',
        'rate',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $rate): void {
            $rate->country = strtoupper((string) $rate->country);
            $rate->state = blank($rate->state) ? null : strtoupper(trim((string) $rate->state));
        });

        static::saved(fn () => Cache::forget('tax_rates.lookup'));
        static::deleted(fn () => Cache::forget('tax_rates.lookup'));
    }

    /**
     * Resolve the rate for a region: exact state match, then the country-wide
     * rate (state = null), else null so callers fall back to the platform default.
     */
    public static function lookup(?string $country, ?string $state): ?float
    {
        if (blank($country)) {
            return null;
        }

        /** @var array<string, float> $table */
        $table = Cache::rememberForever('tax_rates.lookup', fn (): array => self::query()
            ->where('is_active', true)
            ->get()
            ->mapWithKeys(fn (self $rate): array => [
                $rate->country.'|'.($rate->state ?? '') => (float) $rate->rate,
            ])
            ->all());

        $country = strtoupper($country);
        $state = blank($state) ? '' : strtoupper(trim($state));

        return $table[$country.'|'.$state] ?? $table[$country.'|'] ?? null;
    }
}
