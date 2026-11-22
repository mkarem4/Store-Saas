<?php

namespace App\Support;

final class Money
{
    /** @var array<string, string> */
    private const SYMBOLS = ['USD' => '$', 'JOD' => 'JD '];

    /**
     * "$1,234.50", "−$175.04". Western digits in both locales, so figures read
     * the same on receipts, screens and exports.
     */
    public static function format(?int $cents, string $currency = 'USD', int $decimals = 2): string
    {
        $cents ??= 0;
        $amount = number_format(abs($cents) / (10 ** $decimals), $decimals);
        $symbol = self::SYMBOLS[strtoupper($currency)] ?? strtoupper($currency).' ';

        // The left-to-right mark keeps "$2.86" in order inside Arabic text.
        return "\u{200E}".($cents < 0 ? '−' : '').$symbol.$amount;
    }

    /**
     * "+$12.00" / "−$3.50" for changes.
     */
    public static function signed(int $cents, string $currency = 'USD', int $decimals = 2): string
    {
        return $cents > 0 ? "\u{200E}+".ltrim(self::format($cents, $currency, $decimals), "\u{200E}") : self::format($cents, $currency, $decimals);
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
