<?php

namespace App\Filament\Business\Resources\Suppliers;

use App\Enums\BusinessTypeKey;
use App\Filament\Business\BusinessResource;
use App\Models\Supplier;
use App\Support\Ltr;
use App\Support\Permission;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

class SupplierResource extends BusinessResource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $permission = Permission::ManageSuppliers;

    protected static array $exceptTypes = [BusinessTypeKey::CarShowroom->value, BusinessTypeKey::CarRental->value];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(static::$navigationGroup);
    }

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('Supplier');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Suppliers');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label(__('Name'))->required(),
            TextInput::make('phone')->label(__('Phone'))->tel(),
            TextInput::make('email')->label(__('Email'))->email(),
            TextInput::make('address_line')->label(__('Address')),
            Textarea::make('notes')->label(__('Notes'))->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(__('Name'))->searchable(),
            TextColumn::make('phone')->label(__('Phone'))->searchable()->formatStateUsing(fn (?string $state): ?HtmlString => Ltr::wrap($state)),
            TextColumn::make('email')->label(__('Email')),
        ])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageSuppliers::route('/')];
    }
}
