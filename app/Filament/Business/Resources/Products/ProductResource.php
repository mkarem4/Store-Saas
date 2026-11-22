<?php

namespace App\Filament\Business\Resources\Products;

use App\Filament\Business\Resources\Products\Pages\CreateProduct;
use App\Filament\Business\Resources\Products\Pages\EditProduct;
use App\Filament\Business\Resources\Products\Pages\ListProducts;
use App\Filament\Business\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Filament\Business\Resources\Products\Schemas\ProductForm;
use App\Filament\Business\Resources\Products\Tables\ProductsTable;
use App\Models\BusinessType;
use App\Models\Product;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return ProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            VariantsRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount('variants')
            ->orderBy('sort_order');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::tenantBusinessType()->key !== 'restaurant';
    }

    public static function tenantBusinessType(): ?\App\Models\BusinessType
    {
        return \App\Support\BusinessContext::business()?->businessType;
    }
}
