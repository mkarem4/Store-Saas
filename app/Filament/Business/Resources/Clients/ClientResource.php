<?php

namespace App\Filament\Business\Resources\Clients;

use App\Filament\Business\BusinessResource;
use App\Models\Client;
use App\Support\Ltr;
use App\Support\Permission;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Simple client records for rentals, car buyers and optional POS invoices (D19).
 */
class ClientResource extends BusinessResource
{
    protected static ?string $model = Client::class;

    protected static ?string $permission = Permission::ManageClients;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(static::$navigationGroup);
    }

    protected static ?int $navigationSort = 5;

    public static function getModelLabel(): string
    {
        return __('Client');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Clients');
    }

    /**
     * @return array<int, Component>
     */
    public static function fields(): array
    {
        return [
            TextInput::make('name')->label(__('Name'))->required(),
            TextInput::make('phone')->label(__('Phone'))->tel()->required(),
            TextInput::make('email')->label(__('Email'))->email(),
            TextInput::make('id_number')->label(__('ID number')),
            TextInput::make('license_number')->label(__('Driving license #')),
            Textarea::make('notes')->label(__('Notes'))->columnSpanFull(),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components(self::fields());
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('name')->label(__('Name'))->searchable(),
            TextColumn::make('phone')->label(__('Phone'))->searchable()->formatStateUsing(fn (?string $state): ?HtmlString => Ltr::wrap($state)),
            TextColumn::make('id_number')->label(__('ID number'))->searchable()->toggleable(),
            TextColumn::make('license_number')->label(__('License #'))->toggleable(),
            TextColumn::make('created_at')->label(__('Added'))->date()->sortable(),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageClients::route('/')];
    }
}
