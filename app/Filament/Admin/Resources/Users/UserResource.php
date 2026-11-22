<?php

namespace App\Filament\Admin\Resources\Users;

use App\Enums\UserRole;
use App\Filament\Admin\Resources\Users\Pages\ManageUsers;
use App\Models\Business;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Stores';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(static::$navigationGroup);
    }

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('User');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Users');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label(__('Name'))->required(),
            TextInput::make('email')->label(__('Email'))->email()->required()->unique(ignoreRecord: true),
            Select::make('role')->label(__('Role'))->options(UserRole::class)->required()->live()->default(UserRole::BusinessOwner),
            Select::make('business_id')->label(__('Store'))
                ->options(fn (): array => Business::query()->get()->mapWithKeys(fn (Business $business): array => [$business->id => $business->translatedName()])->all())
                ->searchable()
                ->required(fn (Get $get): bool => $get('role') !== UserRole::Owner->value && $get('role') !== UserRole::Owner)
                ->visible(fn (Get $get): bool => $get('role') !== UserRole::Owner->value && $get('role') !== UserRole::Owner),
            TextInput::make('password')->label(__('Password'))->password()->revealable()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->minLength(8),
            Select::make('locale')->label(__('Language'))->options(['en' => 'English', 'ar' => 'العربية'])->default('en')->required(),
            Toggle::make('is_active')->label(__('Active'))->default(true),
            Toggle::make('must_change_password')->label(__('Must change password at next sign-in'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('business'))
            ->columns([
                TextColumn::make('name')->label(__('Name'))->searchable()->description(fn (User $record): string => $record->email),
                TextColumn::make('role')->label(__('Role'))->badge(),
                TextColumn::make('business.slug')->label(__('Store'))
                    ->state(fn (User $record): string => (string) $record->business?->translatedName())->placeholder('—'),
                TextColumn::make('last_login_at')->label(__('Last sign-in'))->since()->placeholder(__('Never')),
                ToggleColumn::make('is_active')->label(__('Active'))
                    ->disabled(fn (User $record): bool => $record->is(auth()->user())),
            ])
            ->filters([
                SelectFilter::make('role')->options(UserRole::class),
                SelectFilter::make('business_id')->label(__('Store'))->relationship('business', 'slug')->searchable(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('resetPassword')
                    ->modalSubmitActionLabel(__('Reset password'))
                    ->label(__('Temporary password'))
                    ->icon(Heroicon::OutlinedKey)
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        $password = Str::password(12, symbols: false);
                        $record->update(['password' => $password, 'must_change_password' => true]);

                        Notification::make()->success()->persistent()
                            ->title(__('New temporary password'))
                            ->body(__('Give :email this password: :password', ['email' => $record->email, 'password' => $password]))
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }
}
