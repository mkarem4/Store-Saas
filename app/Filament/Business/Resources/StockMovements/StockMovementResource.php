<?php

namespace App\Filament\Business\Resources\StockMovements;

use App\Enums\StockMovementType;
use App\Filament\Business\BusinessPanel;
use App\Filament\Business\BusinessResource;
use App\Models\StockMovement;
use App\Support\Permission;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Read-only stock ledger (M2.2): quantity = sum of movements.
 */
class StockMovementResource extends BusinessResource
{
    protected static ?string $model = StockMovement::class;

    protected static ?string $permission = Permission::ManageInventory;

    protected static array $exceptTypes = ['car_showroom', 'car_rental', 'restaurant'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsUpDown;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(static::$navigationGroup);
    }

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('Stock movement');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Stock movements');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['variant.product', 'user']))
            ->columns([
                TextColumn::make('occurred_at')->label(__('When'))->dateTime()->sortable(),
                TextColumn::make('variant')->label(__('Item'))->state(fn (StockMovement $record): string => (string) $record->variant?->displayName())
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('variant', fn (Builder $v) => $v->where('sku', 'like', "%{$search}%")->orWhereHas('product', fn (Builder $p) => $p->where('name', 'like', "%{$search}%")))),
                TextColumn::make('type')->label(__('Type'))->badge(),
                TextColumn::make('qty')->label(__('Quantity'))->numeric(3)
                    ->color(fn (StockMovement $record): string => (float) $record->qty < 0 ? 'danger' : 'success'),
                TextColumn::make('unit_cost_cents')->label(__('Unit cost'))->money(BusinessPanel::currency(), divideBy: 100)->toggleable(),
                TextColumn::make('notes')->label(__('Notes'))->limit(40)->toggleable(),
                TextColumn::make('user.name')->label(__('By'))->placeholder(__('System')),
            ])
            ->filters([
                SelectFilter::make('type')->options(StockMovementType::class)->multiple(),
                Filter::make('period')->schema([
                    DatePicker::make('from')->label(__('From')),
                    DatePicker::make('until')->label(__('Until')),
                ])->query(fn (Builder $query, array $data) => $query
                    ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('occurred_at', '>=', $date))
                    ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('occurred_at', '<=', $date))),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListStockMovements::route('/')];
    }
}
