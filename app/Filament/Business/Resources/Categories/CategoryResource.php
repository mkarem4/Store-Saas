<?php

namespace App\Filament\Business\Resources\Categories;

use App\Filament\Business\Resources\Categories\Pages\CreateCategory;
use App\Filament\Business\Resources\Categories\Pages\EditCategory;
use App\Filament\Business\Resources\Categories\Pages\ListCategories;
use App\Filament\Business\Resources\Categories\Schemas\CategoryForm;
use App\Filament\Business\Resources\Categories\Tables\CategoriesTable;
use App\Models\Category;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return CategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CategoriesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->orderBy('sort_order');
    }
}
