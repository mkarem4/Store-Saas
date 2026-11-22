<?php

namespace App\Filament\Business\Resources\Categories;

use App\Filament\Business\BusinessPanel;
use App\Filament\Business\BusinessResource;
use App\Filament\Support\Fields;
use App\Models\Category;
use App\Support\Permission;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class CategoryResource extends BusinessResource
{
    protected static ?string $model = Category::class;

    protected static ?string $permission = Permission::ManageCatalog;

    protected static array $exceptTypes = ['car_showroom', 'car_rental'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(static::$navigationGroup);
    }

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('Category');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Categories');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Fields::translatable('name', __('Name')),
            TextInput::make('slug')->label(__('Slug'))->alphaDash()
                ->helperText(__('Leave empty to generate it from the English name.'))
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('business_id', BusinessPanel::business()?->id)),
            TextInput::make('sort_order')->label(__('Order'))->integer()->default(0),
            Toggle::make('is_active')->label(__('Shown in the app'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')->label(__('Name'))->formatStateUsing(fn (Category $record): string => $record->translatedName())
                    ->searchable(query: fn ($query, string $search) => $query->where('name', 'like', "%{$search}%")),
                TextColumn::make('products_count')->label(__('Products'))->counts('products'),
                ToggleColumn::make('is_active')->label(__('Active')),
            ])
            ->recordActions([EditAction::make()->mutateDataUsing(fn (array $data): array => self::mutateSlug($data)), DeleteAction::make()]);
    }

    public static function mutateSlug(array $data): array
    {
        $data['slug'] = filled($data['slug'] ?? null) ? $data['slug'] : (Str::slug($data['name']['en'] ?? '') ?: Str::lower(Str::random(6)));

        return $data;
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageCategories::route('/')];
    }
}
