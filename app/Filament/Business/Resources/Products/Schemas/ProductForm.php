<?php

namespace App\Filament\Business\Resources\Products\Schemas;

use App\Filament\Business\Resources\Products\RelationManagers\ModifierGroupsRelationManager;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Group::make([
                    TextInput::make('name.en')
                        ->label(__('Name (English)'))
                        ->required()
                        ->maxLength(255),
                    TextInput::make('name.ar')
                        ->label(__('Name (Arabic)'))
                        ->dir('rtl')
                        ->language('ar')
                        ->maxLength(255),
                ])->columns(2),

                Group::make([
                    Select::make('category_id')
                        ->label(__('Category'))
                        ->relationship('category', 'name')
                        ->getOptionLabelFromRecordUsing(fn ($record): string => $record->translatedName())
                        ->searchable()
                        ->preload()
                        ->helperText(__('Pick a category, or leave empty to uncategorize.')),
                    TextInput::make('sku')
                        ->required()
                        ->unique(ignoreRecord: true),
                ])->columns(2),

                TextInput::make('barcode'),

                Textarea::make('description.en')
                    ->label(__('Description (English)'))
                    ->rows(3),
                Textarea::make('description.ar')
                    ->label(__('Description (Arabic)'))
                    ->rows(3)
                    ->dir('rtl')
                    ->language('ar'),

                KeyValue::make('attributes')
                    ->label(__('Attributes'))
                    ->keyLabel(__('Name'))
                    ->valueLabel(__('Value'))
                    ->reorderable()
                    ->addable()
                    ->deletable(),

                Group::make([
                    FileUpload::make('images')
                        ->label(__('Images'))
                        ->image()
                        ->columnSpanFull(),
                    TextInput::make('sort_order')
                        ->numeric()
                        ->default(0),
                    TextInput::make('prep_time_minutes')
                        ->label(__('Prep time (minutes)'))
                        ->numeric()
                        ->minValue(0),
                    Checkbox::make('is_published')
                        ->label(__('Published (visible in app)'))
                        ->default(true),
                    Checkbox::make('is_available')
                        ->label(__('Available for sale'))
                        ->default(true),
                    Checkbox::make('track_stock')
                        ->label(__('Track stock'))
                        ->default(true),
                    Checkbox::make('has_modifiers')
                        ->label(__('Has modifiers (extras/options)')),
                ])->columns(4),

                ModifierGroupsRelationManager::make()
                    ->visible(fn (string $operation): bool => $operation !== 'create'),
            ]);
    }
}
