<?php

namespace App\Filament\Admin\Resources\BusinessTypes;

use App\Filament\Admin\Resources\BusinessTypes\Pages\CreateBusinessType;
use App\Filament\Admin\Resources\BusinessTypes\Pages\EditBusinessType;
use App\Filament\Admin\Resources\BusinessTypes\Pages\ListBusinessTypes;
use App\Filament\Admin\Resources\BusinessTypes\Schemas\BusinessTypeForm;
use App\Filament\Admin\Resources\BusinessTypes\Tables\BusinessTypesTable;
use App\Models\BusinessType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BusinessTypeResource extends Resource
{
    protected static ?string $model = BusinessType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return BusinessTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BusinessTypesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBusinessTypes::route('/'),
            'create' => CreateBusinessType::route('/create'),
            'edit' => EditBusinessType::route('/{record}/edit'),
        ];
    }
}
