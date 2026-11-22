<?php

namespace App\Filament\Admin\Resources\BusinessTypes;

use App\Filament\Admin\Resources\BusinessTypes\Pages\ManageBusinessTypes;
use App\Filament\Support\Fields;
use App\Models\BusinessType;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Business types and their extra product fields (S2, M1.3). Simple fields
 * (text, number, select, date, yes/no) need no code; cars, rentals and
 * restaurants have dedicated modules keyed by the type key.
 */
class BusinessTypeResource extends Resource
{
    protected static ?string $model = BusinessType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Stores';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(static::$navigationGroup);
    }

    protected static ?int $navigationSort = 2;

    public const MODULE_KEYS = ['car_showroom', 'car_rental', 'restaurant', 'jewelry', 'grocery', 'clothing', 'electronics', 'perfume'];

    public static function getModelLabel(): string
    {
        return __('Business type');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Business types');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextInput::make('key')->label(__('Key'))->required()->alphaDash()->unique(ignoreRecord: true)
                ->disabledOn('edit')
                ->helperText(__('Stable identifier, e.g. bookstore.')),
            TextInput::make('icon')->label(__('Icon'))->placeholder('heroicon-o-building-storefront'),
            TextInput::make('sort_order')->label(__('Display order'))->helperText(__('Lower numbers appear first.'))->integer()->default(0),
            Fields::translatable('name', __('Name')),
            Toggle::make('is_active')->label(__('Active (shown in the app)'))->default(true)->columnSpanFull(),
            Repeater::make('fields')
                ->label(__('Product fields'))
                ->helperText(__('Shown on the product form of every store of this type.'))
                ->columnSpanFull()
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['label']['en'] ?? $state['key'] ?? null)
                ->schema([
                    Grid::make(4)->schema([
                        TextInput::make('key')->label(__('Key'))->required()->alphaDash(),
                        TextInput::make('label.en')->label(__('Label (English)'))->required(),
                        TextInput::make('label.ar')->label(__('Label (Arabic)'))->required()->extraInputAttributes(['dir' => 'rtl']),
                        Select::make('type')->label(__('Type'))->required()->live()->options([
                            'text' => __('Text'),
                            'number' => __('Number'),
                            'select' => __('Choice'),
                            'date' => __('Date'),
                            'boolean' => __('Yes / no'),
                        ]),
                    ]),
                    TagsInput::make('options')->label(__('Choices'))->visible(fn (Get $get): bool => $get('type') === 'select'),
                    Toggle::make('required')->label(__('Required')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')->label(__('Name'))->state(fn (BusinessType $record): string => $record->translatedName()),
                TextColumn::make('key')->label(__('Key'))->badge()->color('gray'),
                TextColumn::make('fields')->label(__('Fields'))->state(fn (BusinessType $record): int => count($record->fields ?? [])),
                TextColumn::make('businesses_count')->label(__('Stores'))->counts('businesses'),
                IconColumn::make('is_active')->label(__('Active'))->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageBusinessTypes::route('/')];
    }
}
