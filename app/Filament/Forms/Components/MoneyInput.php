<?php

namespace App\Filament\Forms\Components;

use App\Support\Money;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class MoneyInput extends TextInput
{
    protected string $view = 'filament.forms.components.text-input';

    /**
     * Relative state path of the integer-cents column this field mirrors.
     */
    protected ?string $centsStatePath = null;

    public function centsStatePath(?string $path): static
    {
        $this->centsStatePath = $path;

        return $this;
    }

    public function initialize(): void
    {
        parent::initialize();

        $this->numeric();
        $this->suffix(fn (): string => config('app.currency', 'USD'));

        $this->formatStateUsing(function (?string $state): mixed {
            if ($state === null || $state === '') {
                return $state;
            }

            return number_format(Money::fromCents((int) $state), 2, '.', ',');
        });
    }
}
