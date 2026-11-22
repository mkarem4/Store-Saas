<?php

namespace App\Filament\Business\Resources\PayoutMethods;

use App\Enums\PayoutMethodType;
use App\Filament\Business\BusinessResource;
use App\Models\PayoutMethod;
use App\Support\Permission;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Where the platform sends the store's money (M9.11). Details are stored
 * encrypted and only shown masked outside the payout screen.
 */
class PayoutMethodResource extends BusinessResource
{
    protected static ?string $model = PayoutMethod::class;

    protected static ?string $permission = Permission::ManageWallet;

    protected static array $exceptTypes = ['car_showroom', 'car_rental'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(static::$navigationGroup);
    }

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return __('Payout account');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Payout accounts');
    }

    public static function form(Schema $schema): Schema
    {
        $bank = fn (Get $get): bool => ($get('type') instanceof PayoutMethodType ? $get('type') : PayoutMethodType::tryFrom((string) $get('type'))) === PayoutMethodType::BankTransfer;

        return $schema->columns(2)->components([
            Select::make('type')->label(__('Type'))->options(PayoutMethodType::class)->default(PayoutMethodType::BankTransfer)->required()->live(),
            TextInput::make('label')->label(__('Name'))->required()->placeholder(__('e.g. Chase business account')),
            TextInput::make('details.account_name')->label(__('Account holder'))->required(),
            TextInput::make('details.bank_name')->label(__('Bank'))->visible($bank),
            TextInput::make('details.account_number')->label(__('Account number / IBAN'))->visible($bank)->required($bank),
            TextInput::make('details.routing_number')->label(__('Routing / SWIFT'))->visible($bank),
            Textarea::make('details.instructions')->label(__('Instructions'))->columnSpanFull()->visible(fn (Get $get): bool => ! $bank($get)),
            Toggle::make('is_default')->label(__('Default'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('label')->label(__('Name'))->description(fn (PayoutMethod $record): string => $record->summary()),
            TextColumn::make('type')->label(__('Type'))->badge()->color('gray'),
            IconColumn::make('is_default')->label(__('Default'))->boolean(),
        ])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManagePayoutMethods::route('/')];
    }
}
