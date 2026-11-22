<?php

namespace App\Filament\Business\Resources\Products\RelationManagers;

use App\Filament\Business\BusinessPanel;
use App\Filament\Forms\Components\MoneyInput;
use App\Filament\Support\Fields;
use App\Models\ModifierGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Restaurant options/add-ons (S14, D25, M2.12): single or multiple choice,
 * required or optional, each option with a price difference.
 */
class ModifierGroupsRelationManager extends RelationManager
{
    protected static string $relationship = 'modifierGroups';

    protected static function getModelLabel(): ?string
    {
        return __('option group');
    }

    protected static function getPluralModelLabel(): ?string
    {
        return __('option groups');
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Options & add-ons');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return BusinessPanel::typeKey() === 'restaurant';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Fields::translatable('name', __('Group name')),
            Grid::make(3)->schema([
                TextInput::make('min_selections')->label(__('Minimum choices'))->integer()->minValue(0)->default(0),
                TextInput::make('max_selections')->label(__('Maximum choices'))->integer()->minValue(1)->default(1)
                    ->gte('min_selections'),
                Toggle::make('is_required')->label(__('Required'))->inline(false),
            ]),
            Repeater::make('modifiers')
                ->relationship('modifiers')
                ->label(__('Options'))
                ->orderColumn('sort_order')
                ->defaultItems(1)
                ->columns(4)
                ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => $data + ['business_id' => BusinessPanel::business()?->id])
                ->schema([
                    TextInput::make('name.en')->label(__('Name (English)'))->required(),
                    TextInput::make('name.ar')->label(__('Name (Arabic)'))->required()->extraInputAttributes(['dir' => 'rtl']),
                    MoneyInput::make('price_delta_cents')->label(__('Price difference'))->allowNegative()->default(0),
                    Toggle::make('is_available')->label(__('Available'))->default(true)->inline(false),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->label(__('Group'))->state(fn (ModifierGroup $record): string => $record->translatedName()),
                TextColumn::make('modifiers_count')->label(__('Options'))->counts('modifiers'),
                TextColumn::make('selections')->label(__('Choices'))->state(fn (ModifierGroup $record): string => $record->min_selections.'–'.$record->max_selections),
                IconColumn::make('is_required')->label(__('Required'))->boolean(),
            ])
            ->headerActions([
                CreateAction::make()->mutateDataUsing(function (array $data): array {
                    $this->getOwnerRecord()->update(['has_modifiers' => true]);

                    return $data + ['business_id' => BusinessPanel::business()?->id];
                }),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
