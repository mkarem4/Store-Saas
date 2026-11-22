<?php

namespace App\Filament\Business;

use Filament\Resources\Resource;

/**
 * Base for store resources. Rows are isolated by the BelongsToBusiness global
 * scope (which reads the Filament tenant), so Filament's own relationship
 * scoping is off. Access is gated by a Spatie permission and, optionally, the
 * store types the module belongs to.
 */
abstract class BusinessResource extends Resource
{
    protected static bool $isScopedToTenant = false;

    protected static ?string $permission = null;

    /** @var array<int, string> store type keys; empty = all types */
    protected static array $types = [];

    /** @var array<int, string> store type keys the module is hidden for */
    protected static array $exceptTypes = [];

    public static function canAccess(): bool
    {
        if (static::$types !== [] && ! BusinessPanel::isType(static::$types)) {
            return false;
        }

        if (static::$exceptTypes !== [] && BusinessPanel::isType(static::$exceptTypes)) {
            return false;
        }

        return static::$permission === null || BusinessPanel::can(static::$permission);
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return static::canAccess();
    }

    public static function canEdit($record): bool
    {
        return static::canAccess();
    }

    public static function canDelete($record): bool
    {
        return static::canAccess();
    }

    public static function canView($record): bool
    {
        return static::canAccess();
    }
}
