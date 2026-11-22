<?php

namespace App\Filament\Admin\Resources\Businesses\Schemas;

use App\Enums\BusinessStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BusinessForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('business_type_id')
                    ->relationship('businessType', 'name')
                    ->required(),
                Textarea::make('name')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('slug')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('website')
                    ->url(),
                TextInput::make('address_line'),
                TextInput::make('city'),
                TextInput::make('state'),
                TextInput::make('postal_code'),
                TextInput::make('country')
                    ->required()
                    ->default('US'),
                TextInput::make('currency')
                    ->required()
                    ->default('USD'),
                TextInput::make('currency_decimals')
                    ->required()
                    ->numeric()
                    ->default(2),
                TextInput::make('timezone')
                    ->required()
                    ->default('America/New_York'),
                Select::make('status')
                    ->options(BusinessStatus::class)
                    ->default('active')
                    ->required(),
                Textarea::make('allowed_ips')
                    ->columnSpanFull(),
                TextInput::make('stripe_account_id'),
                Toggle::make('stripe_onboarding_complete')
                    ->required(),
                Textarea::make('settings')
                    ->columnSpanFull(),
            ]);
    }
}
