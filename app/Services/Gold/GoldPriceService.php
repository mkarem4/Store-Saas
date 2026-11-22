<?php

namespace App\Services\Gold;

use App\Contracts\GoldPriceProvider;
use App\Enums\GoldPriceSource;
use App\Models\Business;
use App\Models\GoldPrice;
use Illuminate\Support\Carbon;

/**
 * Daily gram price per karat for jewelry stores (D5, M2.11). Prices are
 * fetched automatically and derived by purity (karat / 24); a manual entry for
 * the same day always wins and is the fallback when the provider is down.
 */
class GoldPriceService
{
    public const DEFAULT_KARATS = ['18', '21', '22', '24'];

    public function __construct(private readonly GoldPriceProvider $provider) {}

    /**
     * @return array<string, int> karat => cents per gram written for today
     */
    public function refresh(Business $business, ?Carbon $date = null): array
    {
        if (! $business->settings('gold_auto_price', true)) {
            return [];
        }

        $spot = $this->provider->pricePerGram24kCents($business->currency);

        if ($spot === null) {
            return [];
        }

        $date = ($date ?? now($business->timezone))->toDateString();
        $written = [];

        foreach ($this->karats($business) as $karat) {
            $manual = GoldPrice::query()
                ->withoutGlobalScope('business')
                ->where('business_id', $business->id)
                ->where('karat', $karat)
                ->whereDate('effective_date', $date)
                ->where('source', GoldPriceSource::Manual)
                ->exists();

            if ($manual) {
                continue;
            }

            $cents = (int) round($spot * ((int) $karat) / 24);

            GoldPrice::query()->withoutGlobalScope('business')->updateOrCreate(
                ['business_id' => $business->id, 'karat' => $karat, 'effective_date' => $date],
                ['price_per_gram_cents' => $cents, 'source' => GoldPriceSource::Auto],
            );

            $written[$karat] = $cents;
        }

        return $written;
    }

    public function setManual(Business $business, string $karat, int $pricePerGramCents, ?Carbon $date = null): GoldPrice
    {
        return GoldPrice::query()->withoutGlobalScope('business')->updateOrCreate(
            [
                'business_id' => $business->id,
                'karat' => $karat,
                'effective_date' => ($date ?? now($business->timezone))->toDateString(),
            ],
            ['price_per_gram_cents' => $pricePerGramCents, 'source' => GoldPriceSource::Manual],
        );
    }

    /**
     * @return array<int, string>
     */
    public function karats(Business $business): array
    {
        $karats = $business->settings('gold_karats') ?: self::DEFAULT_KARATS;

        return array_values(array_map('strval', $karats));
    }
}
