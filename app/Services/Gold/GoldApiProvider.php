<?php

namespace App\Services\Gold;

use App\Contracts\GoldPriceProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * goldapi.io spot price (G6 assumption: the platform subscribes and bills the
 * jewelry stores outside the system). Cached so all stores share one call.
 */
class GoldApiProvider implements GoldPriceProvider
{
    public function __construct(private readonly ?string $apiKey) {}

    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    public function pricePerGram24kCents(string $currency): ?int
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $currency = strtoupper($currency);

        return Cache::remember("gold:spot:{$currency}", now()->addMinutes(30), function () use ($currency): ?int {
            try {
                $response = Http::timeout(10)
                    ->withHeaders(['x-access-token' => (string) $this->apiKey])
                    ->get("https://www.goldapi.io/api/XAU/{$currency}");

                $perGram = $response->json('price_gram_24k');

                if ($response->successful() && is_numeric($perGram) && $perGram > 0) {
                    return (int) round((float) $perGram * 100);
                }

                Log::warning('Gold price provider returned no price.', ['status' => $response->status()]);
            } catch (\Throwable $exception) {
                Log::warning('Gold price request failed.', ['message' => $exception->getMessage()]);
            }

            return null;
        });
    }
}
