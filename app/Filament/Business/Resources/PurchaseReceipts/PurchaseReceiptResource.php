<?php

namespace App\Filament\Business\Resources\PurchaseReceipts;

use App\Enums\BusinessTypeKey;
use App\Filament\Business\BusinessPanel;
use App\Filament\Business\BusinessResource;
use App\Filament\Forms\Components\MoneyInput;
use App\Models\ProductVariant;
use App\Models\PurchaseReceipt;
use App\Models\Supplier;
use App\Support\Permission;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Goods receiving (M2.3): purchase cost is mandatory per line (D9).
 */
class PurchaseReceiptResource extends BusinessResource
{
    protected static ?string $model = PurchaseReceipt::class;

    protected static ?string $permission = Permission::ManageInventory;

    protected static array $exceptTypes = [BusinessTypeKey::CarShowroom->value, BusinessTypeKey::CarRental->value, BusinessTypeKey::Restaurant->value];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(static::$navigationGroup);
    }

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('Stock receipt');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Receive stock');
    }

    /**
     * @return array<int|string, string>
     */
    public static function variantOptions(?string $search = null): array
    {
        return ProductVariant::query()
            ->with('product')
            ->whereHas('product', fn ($query) => $query->where('track_stock', true))
            ->when($search, fn ($query) => $query->where(fn ($q) => $q
                ->where('sku', 'like', "%{$search}%")
                ->orWhere('barcode', $search)
                ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$search}%"))))
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (ProductVariant $variant): array => [$variant->id => trim($variant->product->translatedName().' · '.$variant->translated('name').' '.($variant->sku ? "({$variant->sku})" : ''), ' ·')])
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        // Main column: the received items. Side column: where and when they came from.
        return $schema->columns(['default' => 1, 'lg' => 3])->components([
            Section::make(__('Items'))->columnSpan(['lg' => 2])->schema([
                Repeater::make('lines')->hiddenLabel()->minItems(1)->columns(['default' => 1, 'sm' => 3])->required()
                    ->addActionLabel(__('Add item'))
                    ->schema([
                        Select::make('variant_id')->label(__('Item'))->required()->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => self::variantOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => self::variantOptions()[$value] ?? ProductVariant::query()->find($value)?->displayName())
                            ->options(fn (): array => self::variantOptions()),
                        TextInput::make('qty')->label(__('Quantity'))->numeric()->minValue(0.001)->required(),
                        MoneyInput::make('unit_cost_cents')->label(__('Unit cost'))->required()->minValue(0.01),
                    ]),
            ]),
            Section::make(__('Receipt'))->columnSpan(['lg' => 1])->schema([
                Select::make('supplier_id')->label(__('Supplier'))
                    ->options(fn (): array => Supplier::query()->pluck('name', 'id')->all())->searchable(),
                TextInput::make('reference')->label(__('Invoice #')),
                DateTimePicker::make('received_at')->label(__('Received at'))->default(now())->required()->seconds(false),
                Textarea::make('notes')->label(__('Notes')),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        $currency = BusinessPanel::currency();

        return $schema->columns(1)->components([
            Section::make(__('Details'))->columnSpanFull()->columns(['default' => 1, 'sm' => 2, 'lg' => 4])->schema([
                TextEntry::make('reference')->label(__('Invoice #'))->placeholder('—'),
                TextEntry::make('supplier.name')->label(__('Supplier'))->placeholder('—'),
                TextEntry::make('received_at')->label(__('Received at'))->dateTime(),
                TextEntry::make('total_cost_cents')->label(__('Total cost'))->money($currency, divideBy: 100),
            ]),
            Section::make(__('Items'))->columnSpanFull()->schema([
                RepeatableEntry::make('items')->hiddenLabel()->columns(4)->schema([
                    TextEntry::make('variant')->label(__('Item'))->state(fn ($record): string => (string) $record->variant?->displayName()),
                    TextEntry::make('qty')->label(__('Quantity'))->numeric(3),
                    TextEntry::make('unit_cost_cents')->label(__('Unit cost'))->money($currency, divideBy: 100),
                    TextEntry::make('total_cost_cents')->label(__('Total'))->money($currency, divideBy: 100),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('received_at', 'desc')
            ->columns([
                TextColumn::make('received_at')->label(__('Received'))->dateTime()->sortable(),
                TextColumn::make('reference')->label(__('Invoice #'))->searchable()->placeholder('—'),
                TextColumn::make('supplier.name')->label(__('Supplier'))->placeholder('—'),
                TextColumn::make('items_count')->label(__('Lines'))->counts('items'),
                TextColumn::make('total_cost_cents')->label(__('Total cost'))->money(BusinessPanel::currency(), divideBy: 100)->sortable(),
                TextColumn::make('user.name')->label(__('By'))->toggleable(),
            ])
            ->filters([SelectFilter::make('supplier_id')->label(__('Supplier'))->relationship('supplier', 'name')])
            ->recordActions([ViewAction::make()]);
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseReceipts::route('/'),
            'create' => Pages\CreatePurchaseReceipt::route('/create'),
            'view' => Pages\ViewPurchaseReceipt::route('/{record}'),
        ];
    }
}
