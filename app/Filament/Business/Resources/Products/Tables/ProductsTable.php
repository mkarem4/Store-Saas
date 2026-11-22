<?php

namespace App\Filament\Business\Resources\Products\Tables;

use App\Models\Product;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->formatStateUsing(fn (Product $record): string => $record->translatedName())
                    ->searchable(['name', 'sku', 'barcode'])
                    ->sortable(),
                TextColumn::make('sku')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label(__('Category'))
                    ->formatStateUsing(fn (Product $record, ?string $state): string => $record->category?->translatedName() ?? '—'),
                TextColumn::make('variants_count')
                    ->label(__('Variants')),
                IconColumn::make('is_published')
                    ->label(__('Published'))
                    ->boolean(),
                IconColumn::make('is_available')
                    ->label(__('Available'))
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_published')
                    ->label(__('Published')),
                TernaryFilter::make('is_available')
                    ->label(__('Available')),
                TernaryFilter::make('track_stock')
                    ->label(__('Track stock')),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
