<?php

namespace App\Filament\Business\Resources\Products;

use App\Enums\BusinessTypeKey;
use App\Filament\Business\BusinessPanel;
use App\Filament\Business\BusinessResource;
use App\Filament\Business\Resources\Products\Schemas\TypeFields;
use App\Filament\Business\Resources\Products\Schemas\VariantForm;
use App\Filament\Support\Fields;
use App\Models\Category;
use App\Models\Product;
use App\Support\Permission;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ProductResource extends BusinessResource
{
    protected static ?string $model = Product::class;

    protected static ?string $permission = Permission::ManageCatalog;

    protected static array $exceptTypes = [BusinessTypeKey::CarShowroom->value, BusinessTypeKey::CarRental->value];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(static::$navigationGroup);
    }

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return BusinessPanel::typeKey() === BusinessTypeKey::Restaurant->value ? __('Menu item') : __('Product');
    }

    public static function getPluralModelLabel(): string
    {
        return BusinessPanel::typeKey() === BusinessTypeKey::Restaurant->value ? __('Menu') : __('Products');
    }

    public static function form(Schema $schema): Schema
    {
        $type = BusinessPanel::business()?->businessType;
        $isRestaurant = $type?->key === BusinessTypeKey::Restaurant->value;
        // Type fields that duplicate real product or variant columns are left out, so nothing is asked twice.
        $typeFields = TypeFields::components($type, except: [
            'prep_time_minutes', 'is_available', 'modifiers', 'has_modifiers', 'is_published', 'track_stock',
            'sku', 'barcode', 'unit', 'low_stock_threshold', 'expiry_date',
            'pricing_mode', 'weight_g', 'karat', 'metal', 'making_value', 'certificate_number',
        ]);

        // Polaris-style: what the product is on the main side, status and organisation beside it.
        return $schema->columns(['default' => 1, 'lg' => 3])->components([
            Group::make([
                Section::make(__('Details'))->columns(2)->schema([
                    Fields::translatable('name', __('Name')),
                    Fields::translatable('description', __('Description'), required: false, textarea: true),
                ]),
                Section::make(__('Media'))->schema([
                    SpatieMediaLibraryFileUpload::make('images')->hiddenLabel()->collection('images')->multiple()->reorderable()->image()->maxFiles(8)
                        ->helperText(__('Up to 8 photos. The first one is shown in lists and the app.')),
                ]),
                Section::make(__('Price & stock'))
                    ->visibleOn('create')
                    ->statePath('variant')
                    ->schema(VariantForm::components(creating: true)),
                Section::make(__('Specifications'))->columns(2)->schema($typeFields)->visible($typeFields !== []),
            ])->columnSpan(['lg' => 2]),
            Group::make([
                Section::make(__('Status'))->schema([
                    Toggle::make('is_published')->label(__('Shown in the app'))->default(true),
                    Toggle::make('is_available')->label(__('Available'))->default(true)
                        ->helperText($isRestaurant ? __('Turn off when the dish runs out.') : null),
                    Toggle::make('track_stock')->label(__('Track stock'))->default(! $isRestaurant)->visible(! $isRestaurant),
                    Toggle::make('has_modifiers')->label(__('Has options / add-ons'))->visible($isRestaurant),
                ]),
                Section::make(__('Organisation'))->schema([
                    Select::make('category_id')->label(__('Category'))
                        ->options(fn (): array => Category::query()->orderBy('sort_order')->get()->mapWithKeys(fn (Category $category): array => [$category->id => $category->translatedName()])->all())
                        ->searchable(),
                    TextInput::make('prep_time_minutes')->label(__('Preparation time (min)'))->integer()->minValue(0)->visible($isRestaurant),
                ]),
            ])->columnSpan(['lg' => 1]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $isRestaurant = BusinessPanel::typeKey() === BusinessTypeKey::Restaurant->value;

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['variants', 'category']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                SpatieMediaLibraryImageColumn::make('images')->collection('images')->limit(1)->label('')->square(),
                TextColumn::make('name')->label(__('Name'))
                    ->state(fn (Product $record): string => $record->translatedName())
                    ->description(fn (Product $record): string => (string) $record->variants->first()?->sku)
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(fn (Builder $q) => $q
                        ->where('name', 'like', "%{$search}%")
                        ->orWhereHas('variants', fn (Builder $v) => $v->where('sku', 'like', "%{$search}%")->orWhere('barcode', $search)))),
                TextColumn::make('category.name')->label(__('Category'))->state(fn (Product $record): string => (string) $record->category?->translatedName())->toggleable(),
                TextColumn::make('price')->label(__('Price'))
                    ->state(fn (Product $record): ?int => $record->variants->first()?->retail_price_cents)
                    ->money(BusinessPanel::currency(), divideBy: 100),
                TextColumn::make('stock')->label(__('Stock'))
                    ->visible(! $isRestaurant)
                    ->state(fn (Product $record): string => $record->track_stock ? rtrim(rtrim(number_format((float) $record->variants->sum('stock_qty'), 3), '0'), '.') : '∞')
                    ->color(fn (Product $record): ?string => $record->variants->contains(fn ($variant) => $variant->isLowStock()) ? 'danger' : null),
                TextColumn::make('variants_count')->label(__('Variants'))->counts('variants')->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make('is_available')->label(__('Available')),
                ToggleColumn::make('is_published')->label(__('In app')),
            ])
            ->filters([
                SelectFilter::make('category_id')->label(__('Category'))
                    ->options(fn (): array => Category::query()->get()->mapWithKeys(fn (Category $category): array => [$category->id => $category->translatedName()])->all()),
                TernaryFilter::make('is_published')->label(__('In app')),
                Filter::make('low_stock')->label(__('Low stock'))
                    ->query(fn (Builder $query) => $query->where('track_stock', true)->whereHas('variants', fn (Builder $v) => $v->whereNotNull('low_stock_threshold')->whereColumn('stock_qty', '<=', 'low_stock_threshold'))),
                Filter::make('expiring')->label(__('Expiring within 30 days'))
                    ->visible(BusinessPanel::typeKey() === BusinessTypeKey::Grocery->value)
                    ->query(fn (Builder $query) => $query->whereHas('variants', fn (Builder $v) => $v->whereNotNull('expiry_date')->whereDate('expiry_date', '<=', now()->addDays(30)))),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\VariantsRelationManager::class,
            RelationManagers\ModifierGroupsRelationManager::class,
            RelationManagers\SerialNumbersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
