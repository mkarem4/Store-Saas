<?php

namespace App\Support;

final class Money
{
    public static function format(?int $cents, string $currency = 'USD', int $decimals = 2): string
    {
        $amount = ($cents ?? 0) / (10 ** $decimals);

        return $currency.' '.number_format($amount, $decimals);
    }

    public static function toCents(float|int|string|null $amount, int $decimals = 2): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        return (int) round((float) $amount * (10 ** $decimals));
    }

    public static function fromCents(int $cents, int $decimals = 2): float
    {
        return $cents / (10 ** $decimals);
    }
}
