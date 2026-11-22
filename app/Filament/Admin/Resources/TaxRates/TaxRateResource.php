<?php

namespace App\Filament\Admin\Resources\TaxRates;

use App\Filament\Admin\Resources\TaxRates\Pages\ManageTaxRates;
use App\Filament\Support\Fields;
use App\Models\TaxRate;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

/**
 * Sales tax by region (D31, M1.7). A store uses its state's rate, else its
 * country's (state empty), else the platform default.
 */
class TaxRateResource extends Resource
{
    protected static ?string $model = TaxRate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Platform';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(static::$navigationGroup);
    }

    protected static ?int $navigationSort = 10;

    public static function getModelLabel(): string
    {
        return __('Tax rate');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Tax rates');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('country')->label(__('Country'))->options(['US' => __('United States'), 'JO' => __('Jordan')])->default('US')->required(),
            TextInput::make('state')->label(__('State / region'))->datalist(Fields::usStates())
                ->helperText(__('Leave empty for the country-wide rate.'))
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('country', $get('country'))),
            TextInput::make('name')->label(__('Name'))->placeholder(__('e.g. Texas state sales tax')),
            TextInput::make('rate')->label(__('Rate'))->numeric()->suffix('%')->minValue(0)->maxValue(100)->required(),
            Toggle::make('is_active')->label(__('Active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('state')
            ->columns([
                TextColumn::make('country')->label(__('Country'))->badge()->color('gray')->sortable(),
                TextColumn::make('state')->label(__('State'))->placeholder(__('Country-wide'))->sortable()->searchable(),
                TextColumn::make('name')->label(__('Name'))->toggleable(),
                TextColumn::make('rate')->label(__('Rate'))->suffix('%')->sortable(),
                IconColumn::make('is_active')->label(__('Active'))->boolean(),
            ])
            ->filters([SelectFilter::make('country')->options(['US' => 'US', 'JO' => 'JO'])])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageTaxRates::route('/')];
    }
}
