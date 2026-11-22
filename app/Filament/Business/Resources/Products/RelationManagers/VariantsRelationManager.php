<?php

namespace App\Filament\Business\Resources\Products;

use App\Enums\ProductUnit;
use App\Filament\Forms\Components\MoneyInput;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static string | Heroicon | null $icon = Heroicon::OutlinedTag;

    protected static ?string $title = 'Variants';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(4)
            ->components([
                Section::make(__('Product'))
                    ->schema([
                        TextInput::make('name.en')
                            ->label(__('Name (English)'))
                            ->placeholder(__('Leave blank to inherit the product name')),
                        TextInput::make('name.ar')
                            ->label(__('Name (Arabic)'))
                            ->dir('rtl')
                            ->language('ar'),
                        TextInput::make('sku')
                            ->required(),
                        TextInput::make('barcode'),
                        Select::make('unit')
                            ->nativeEnum(ProductUnit::class)
                            ->required()
                            ->default(ProductUnit::Piece)
                            ->live(),
                        Toggle::make('is_default')
                            ->label(__('Default variant'))
                            ->helperText(__('Used for quick-add and inventory summary.')),
                    ])
                    ->columns(4),

                Section::make(__('Pricing'))
                    ->description(__('Amounts are entered in whole currency units; cents are stored automatically.'))
                    ->schema([
                        MoneyInput::make('retail_price_cents')
                            ->label(__('Retail price'))
                            ->required(),
                        MoneyInput::make('wholesale_price_cents')
                            ->label(__('Wholesale price')),
                        MoneyInput::make('cost_price_cents')
                            ->label(__('Cost price'))
                            ->visible(fn (): bool => auth()->user()?->can(\App\Support\Permission::ViewCosts) ?? false),
                    ])
                    ->columns(4),

                Section::make(__('Inventory & lifecycle'))
                    ->schema([
                        TextInput::make('stock_qty')
                            ->label(__('Stock on hand'))
                            ->numeric(decimalPlaces: 3)
                            ->default(0),
                        TextInput::make('low_stock_threshold')
                            ->label(__('Low stock warning level'))
                            ->numeric(decimalPlaces: 3),
                        DatePicker::make('expiry_date')
                            ->label(__('Expiry date')),
                        TextInput::make('sort_order')
                            ->label(__('Sort'))
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(4),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->formatStateUsing(fn (Model $record): string => $record->displayName())
                    ->searchable(['name', 'sku', 'barcode']),
                TextColumn::make('sku'),
                TextColumn::make('unit')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('retail_price_cents')
                    ->label(__('Retail'))
                    ->money(fn (Model $record): string => (string) $record->business?->currency ?? 'USD'),
                TextColumn::make('wholesale_price_cents')
                    ->label(__('Wholesale'))
                    ->money(fn (Model $record): string => (string) $record->business?->currency ?? 'USD')
                    ->visible(fn (): bool => auth()->user()?->can(\App\Support\Permission::ViewCosts) ?? false),
                TextColumn::make('cost_price_cents')
                    ->label(__('Cost'))
                    ->money(fn (Model $record): string => (string) $record->business?->currency ?? 'USD')
                    ->visible(fn (): bool => auth()->user()?->can(\App\Support\Permission::ViewCosts) ?? false),
                TextColumn::make('stock_qty')
                    ->label(__('Stock'))
                    ->numeric(decimalPlaces: 3)
                    ->sortable(),
                TextColumn::make('reserved_qty')
                    ->label(__('Reserved'))
                    ->numeric(decimalPlaces: 3)
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_default')
                    ->label(__('Default'))
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
