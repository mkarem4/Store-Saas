<?php

namespace App\Filament\Forms\Components;

use App\Support\BusinessContext;
use App\Support\Money;
use Filament\Forms\Components\TextInput;

/**
 * Edits an integer-cents column as a decimal amount (spec §7: money is
 * stored as integers).
 */
class MoneyInput extends TextInput
{
    protected bool $allowsNegative = false;

    public function allowNegative(bool $condition = true): static
    {
        $this->allowsNegative = $condition;

        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->numeric();
        $this->step('0.01');
        $this->minValue(fn (): ?int => $this->allowsNegative ? null : 0);
        $this->prefix(fn (): string => BusinessContext::business()?->currency ?? 'USD');

        $this->formatStateUsing(function (mixed $state): ?string {
            if ($state === null || $state === '') {
                return null;
            }

            return number_format(Money::fromCents((int) $state), 2, '.', '');
        });

        $this->dehydrateStateUsing(fn (mixed $state): int => Money::toCents($state));
    }
}
