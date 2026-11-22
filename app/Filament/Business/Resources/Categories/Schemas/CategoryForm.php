<?php

namespace App\Filament\Business\Resources\Categories\Schemas;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make(__('Category'))
                    ->schema([
                        TextInput::make('name.en')
                            ->label(__('Name (English)'))
                            ->required(),
                        TextInput::make('name.ar')
                            ->label(__('Name (Arabic)'))
                            ->required()
                            ->dir('rtl')
                            ->language('ar'),
                        TextInput::make('slug')
                            ->slug('name.en')
                            ->required()
                            ->unique(ignoreRecord: true),
                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0),
                        Toggle::make('is_active')
                            ->default(true),
                    ])
                    ->columns(3),
            ]);
    }
}
