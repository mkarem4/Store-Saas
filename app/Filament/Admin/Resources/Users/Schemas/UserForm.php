<?php

namespace App\Filament\Admin\Resources\Users\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                DateTimePicker::make('email_verified_at'),
                TextInput::make('password')
                    ->password()
                    ->required(),
                Select::make('business_id')
                    ->relationship('business', 'name'),
                Select::make('role')
                    ->options(UserRole::class)
                    ->default('staff')
                    ->required(),
                TextInput::make('locale')
                    ->required()
                    ->default('en'),
                Toggle::make('is_active')
                    ->required(),
                Toggle::make('must_change_password')
                    ->required(),
                DateTimePicker::make('last_login_at'),
            ]);
    }
}
