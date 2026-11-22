<?php

namespace App\Filament\Business\Resources\Products\Schemas;

use App\Models\BusinessType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;

/**
 * Builds the extra product inputs a business type defines (S2, §4): the
 * template stored in business_types.fields, saved into products.attributes.
 */
final class TypeFields
{
    /**
     * @param  array<int, string>  $except  keys the form already has as real fields
     * @return array<int, Component>
     */
    public static function components(?BusinessType $type, string $statePath = 'attributes', array $except = []): array
    {
        $locale = app()->getLocale();
        $components = [];

        foreach ($type?->fields ?? [] as $field) {
            $key = (string) ($field['key'] ?? '');

            if ($key === '' || in_array($key, $except, true)) {
                continue;
            }

            $label = $field['label'][$locale] ?? $field['label']['en'] ?? $key;
            $name = "{$statePath}.{$key}";

            $component = match ($field['type'] ?? 'text') {
                'number' => TextInput::make($name)->numeric(),
                'select' => Select::make($name)->options(collect($field['options'] ?? [])->mapWithKeys(fn ($option): array => [(string) $option => (string) $option])->all()),
                'date' => DatePicker::make($name),
                'boolean' => Toggle::make($name),
                default => TextInput::make($name)->maxLength(255),
            };

            $components[] = $component->label($label)->required((bool) ($field['required'] ?? false));
        }

        return $components;
    }
}
