<?php

namespace App\Filament\Business\Resources\Products\RelationManagers;

use App\Enums\StockMovementType;
use App\Filament\Business\BusinessPanel;
use App\Filament\Business\Resources\Products\Schemas\VariantForm;
use App\Filament\Forms\Components\MoneyInput;
use App\Filament\Support\Fields;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CatalogService;
use App\Services\StockService;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Variants & stock');
    }

    public function form(Schema $schema): Schema
    {
        $creating = $schema->getOperation() === 'create';

        return $schema->components([
            Fields::translatable('name', __('Variant name'), required: false)
                ->hint(__('e.g. size, color, or 500 ml. Leave empty for a single-variant product.')),
            ...VariantForm::components($creating, ignoreId: $creating ? null : $schema->getRecord()?->getKey()),
        ]);
    }

    public function table(Table $table): Table
    {
        $currency = BusinessPanel::currency();
        $tracksStock = (bool) $this->getOwnerRecord()->track_stock;

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('jewelryAttribute'))
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->label(__('Variant'))->formatStateUsing(fn (ProductVariant $record): string => $record->displayName()),
                TextColumn::make('sku')->label(__('SKU'))->placeholder('—')->searchable(),
                TextColumn::make('barcode')->label(__('Barcode'))->placeholder('—')->searchable()->toggleable(),
                TextColumn::make('unit')->label(__('Unit'))->badge()->color('gray'),
                TextColumn::make('cost_price_cents')->label(__('Cost'))->money($currency, divideBy: 100),
                TextColumn::make('wholesale_price_cents')->label(__('Wholesale'))->money($currency, divideBy: 100)->toggleable(),
                TextColumn::make('retail_price_cents')->label(__('Retail'))->money($currency, divideBy: 100)
                    ->description(fn (ProductVariant $record): ?string => $record->jewelryAttribute?->pricing_mode?->value === 'by_weight'
                        ? __('Today: :price', ['price' => Money::format($record->priceFor(), $currency)]) : null),
                TextColumn::make('stock_qty')->label(__('Stock'))->visible($tracksStock)->numeric(3)
                    ->color(fn (ProductVariant $record): ?string => $record->isLowStock() ? 'danger' : null),
                TextColumn::make('reserved_qty')->label(__('Reserved'))->visible($tracksStock)->numeric(3)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('expiry_date')->label(__('Expires'))->date()->visible(BusinessPanel::typeKey() === 'grocery')
                    ->color(fn (ProductVariant $record): ?string => $record->isExpiringSoon() ? 'warning' : null),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(fn (array $data): ProductVariant => app(CatalogService::class)->addVariant($this->getOwnerRecord(), $data, auth()->id())),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->mutateRecordDataUsing(function (array $data, ProductVariant $record): array {
                            $data['jewelry'] = $record->jewelryAttribute?->only(['pricing_mode', 'weight_g', 'karat', 'metal', 'making_type', 'making_value', 'certificate_number']);

                            return $data;
                        })
                        ->using(function (ProductVariant $record, array $data): ProductVariant {
                            $jewelry = Arr::pull($data, 'jewelry');
                            $before = $record->only(['retail_price_cents', 'wholesale_price_cents']);
                            $record->update($data);
                            app(CatalogService::class)->saveJewelry($record, $jewelry);

                            if ($record->wasChanged(['retail_price_cents', 'wholesale_price_cents'])) {
                                activity('catalog')->performedOn($record)->causedBy(auth()->user())
                                    ->withProperties(['old' => $before, 'attributes' => $record->only(['retail_price_cents', 'wholesale_price_cents'])])
                                    ->log('price.changed');
                            }

                            return $record;
                        }),
                    Action::make('receive')
                        ->label(__('Receive stock'))
                        ->icon(Heroicon::OutlinedArrowDownOnSquare)
                        ->visible($tracksStock)
                        ->schema([
                            TextInput::make('qty')->label(__('Quantity'))->numeric()->minValue(0.001)->required(),
                            MoneyInput::make('unit_cost_cents')->label(__('Unit cost'))->required(),
                        ])
                        ->action(fn (ProductVariant $record, array $data) => $this->stockAction(fn () => app(StockService::class)->receive($record, (float) $data['qty'], (int) $data['unit_cost_cents'], null, auth()->id(), __('Manual receipt')))),
                    Action::make('adjust')
                        ->label(__('Stock count'))
                        ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                        ->visible($tracksStock)
                        ->schema([
                            TextInput::make('qty')->label(__('Counted quantity'))->numeric()->minValue(0)->required(),
                            TextInput::make('notes')->label(__('Reason'))->required(),
                        ])
                        ->action(fn (ProductVariant $record, array $data) => $this->stockAction(fn () => app(StockService::class)->adjust($record, (float) $data['qty'], auth()->id(), $data['notes']))),
                    Action::make('damage')
                        ->label(__('Write off damaged'))
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger')
                        ->visible($tracksStock)
                        ->schema([
                            TextInput::make('qty')->label(__('Quantity'))->numeric()->minValue(0.001)->required(),
                            TextInput::make('notes')->label(__('Reason'))->required(),
                        ])
                        ->action(fn (ProductVariant $record, array $data) => $this->stockAction(fn () => app(StockService::class)->deduct($record, (float) $data['qty'], StockMovementType::Damage, null, auth()->id(), $data['notes'], allowNegative: false))),
                    DeleteAction::make()->visible(fn (): bool => $this->getOwnerRecord()->variants()->count() > 1),
                ]),
            ]);
    }

    private function stockAction(callable $callback): void
    {
        try {
            $callback();
            Notification::make()->success()->title(__('Stock updated'))->send();
        } catch (InvalidArgumentException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();
        }
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Product;
    }
}
