<?php

namespace App\Contracts;

interface GoldPriceProvider
{
    /**
     * Spot price of pure (24K) gold per gram in the given currency's minor
     * units (cents), or null when the provider cannot answer.
     */
    public function pricePerGram24kCents(string $currency): ?int;

    public function isConfigured(): bool;
}
