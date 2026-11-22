<?php

namespace App\Models\Concerns;

trait HasTranslations
{
    public function getTranslation(string $attribute, ?string $locale = null): ?string
    {
        $values = $this->getAttributeValue($attribute);

        if (is_string($values)) {
            return $values;
        }

        if (! is_array($values)) {
            return null;
        }

        $locale ??= app()->getLocale();

        return $values[$locale]
            ?? $values[config('app.fallback_locale')]
            ?? (count($values) > 0 ? reset($values) : null);
    }

    public function translated(string $attribute, ?string $locale = null): string
    {
        return (string) $this->getTranslation($attribute, $locale);
    }
}
