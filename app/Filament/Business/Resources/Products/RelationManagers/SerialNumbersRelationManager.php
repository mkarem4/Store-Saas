<?php

namespace App\Filament\Business\Resources\Products\RelationManagers;

use App\Enums\BusinessTypeKey;
use App\Filament\Business\BusinessPanel;
use App\Models\InventoryUnit;
use App\Models\ProductVariant;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

/**
 * IMEI / serial numbers for electronics (§4, optional), unique per store.
 */
class SerialNumbersRelationManager extends RelationManager
{
    protected static string $relationship = 'inventoryUnits';

    public const STATUSES = ['in_stock' => 'In stock', 'sold' => 'Sold', 'returned' => 'Returned', 'defective' => 'Defective'];

    protected static function getModelLabel(): ?string
    {
        return __('serial number');
    }

    protected static function getPluralModelLabel(): ?string
    {
        return __('serial numbers');
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('IMEI / serial numbers');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return BusinessPanel::typeKey() === BusinessTypeKey::Electronics->value;
    }

    /**
     * @return array<string, string>
     */
    private static function statuses(): array
    {
        return array_map(fn (string $label): string => __($label), self::STATUSES);
    }

    public function form(Schema $schema): Schema
    {
        $businessId = BusinessPanel::business()?->id;

        return $schema->columns(2)->components([
            Select::make('product_variant_id')->label(__('Variant'))->required()
                ->options(fn (): array => $this->getOwnerRecord()->variants->mapWithKeys(fn (ProductVariant $variant): array => [$variant->id => $variant->displayName()])->all())
                ->default(fn (): ?int => $this->getOwnerRecord()->variants->first()?->id),
            TextInput::make('serial_number')->label(__('IMEI / serial'))->required()->maxLength(64)
                ->unique('inventory_units', 'serial_number', ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('business_id', $businessId)),
            Select::make('status')->label(__('Status'))->options(self::statuses())->default('in_stock')->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('serial_number')->label(__('IMEI / serial'))->searchable()->copyable(),
                TextColumn::make('variant')->label(__('Variant'))->state(fn (InventoryUnit $record): string => (string) $record->variant?->displayName()),
                SelectColumn::make('status')->label(__('Status'))->options(self::statuses())->selectablePlaceholder(false),
                TextColumn::make('created_at')->label(__('Added'))->date(),
            ])
            ->filters([SelectFilter::make('status')->options(self::statuses())])
            ->headerActions([
                CreateAction::make()->mutateDataUsing(fn (array $data): array => $data + ['business_id' => BusinessPanel::business()?->id]),
            ])
            ->recordActions([DeleteAction::make()]);
    }
}
