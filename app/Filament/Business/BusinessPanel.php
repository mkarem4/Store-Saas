<?php

namespace App\Filament\Business;

use App\Models\Business;
use App\Models\User;
use Filament\Facades\Filament;

/**
 * Helpers shared by business-panel resources and pages.
 */
final class BusinessPanel
{
    public static function business(): ?Business
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Business ? $tenant : null;
    }

    public static function typeKey(): ?string
    {
        return self::business()?->businessType?->key;
    }

    /**
     * @param  array<int, string>  $types
     */
    public static function isType(array $types): bool
    {
        return in_array(self::typeKey(), $types, true);
    }

    public static function can(string $permission): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can($permission);
    }

    public static function currency(): string
    {
        return self::business()?->currency ?? 'USD';
    }
}
