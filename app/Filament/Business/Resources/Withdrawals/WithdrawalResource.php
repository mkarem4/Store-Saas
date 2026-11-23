<?php

namespace App\Filament\Business\Resources\Withdrawals;

use App\Enums\BusinessTypeKey;
use App\Filament\Business\BusinessPanel;
use App\Filament\Business\BusinessResource;
use App\Models\PayoutRequest;
use App\Support\Permission;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The store's own payout requests and their status (M9.11/M9.12).
 */
class WithdrawalResource extends BusinessResource
{
    protected static ?string $model = PayoutRequest::class;

    protected static ?string $permission = Permission::ManageWallet;

    protected static array $exceptTypes = [BusinessTypeKey::CarShowroom->value, BusinessTypeKey::CarRental->value];

    protected static ?string $slug = 'withdrawals';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpRight;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(static::$navigationGroup);
    }

    protected static ?int $navigationSort = 5;

    public static function getModelLabel(): string
    {
        return __('Withdrawal');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Withdrawals');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')->label('#'),
                TextColumn::make('created_at')->label(__('Requested'))->dateTime(),
                TextColumn::make('amount_cents')->label(__('Amount'))->money(BusinessPanel::currency(), divideBy: 100),
                TextColumn::make('method_snapshot.summary')->label(__('To'))->placeholder('—'),
                TextColumn::make('status')->label(__('Status'))->badge(),
                TextColumn::make('reference')->label(__('Transfer reference'))->placeholder('—'),
                TextColumn::make('admin_note')->label(__('Platform note'))->placeholder('—')->limit(40),
                TextColumn::make('paid_at')->label(__('Paid'))->date()->placeholder('—'),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListWithdrawals::route('/')];
    }
}
