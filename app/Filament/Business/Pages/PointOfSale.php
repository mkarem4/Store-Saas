<?php

namespace App\Filament\Business\Pages;

use App\Filament\Pos\PointOfSalePage;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class PointOfSale extends PointOfSalePage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(static::$navigationGroup);
    }

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'pos';
}
