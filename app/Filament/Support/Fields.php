<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;

/**
 * Shared form fragments for the three panels.
 */
final class Fields
{
    /**
     * English + Arabic inputs for a JSON translatable attribute (S13).
     */
    public static function translatable(string $attribute, string $label, bool $required = true, bool $textarea = false): Grid
    {
        $make = fn (string $locale, string $suffix) => ($textarea ? Textarea::make("{$attribute}.{$locale}")->rows(3) : TextInput::make("{$attribute}.{$locale}")->maxLength(255))
            ->label("{$label} ({$suffix})")
            ->extraInputAttributes(['dir' => $locale === 'ar' ? 'rtl' : 'ltr']);

        return Grid::make(2)->schema([
            $make('en', __('English'))->required($required),
            $make('ar', __('Arabic'))->required($required),
        ])->columnSpanFull();
    }

    /**
     * [value => label()] for a backed enum with a label() method.
     *
     * @param  class-string<\BackedEnum>  $enum
     * @return array<string|int, string>
     */
    public static function enumOptions(string $enum): array
    {
        $options = [];

        foreach ($enum::cases() as $case) {
            $options[$case->value] = method_exists($case, 'label') ? $case->label() : $case->name;
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function timezones(): array
    {
        $zones = \DateTimeZone::listIdentifiers(\DateTimeZone::AMERICA | \DateTimeZone::ASIA | \DateTimeZone::EUROPE | \DateTimeZone::UTC);

        return array_combine($zones, $zones);
    }

    /**
     * @return array<string, string>
     */
    public static function usStates(): array
    {
        $states = ['AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'DC', 'FL', 'GA', 'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD', 'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ', 'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC', 'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY'];

        return array_combine($states, $states);
    }
}
