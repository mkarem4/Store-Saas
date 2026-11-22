<?php

namespace App\Filament\Business\Pages;

use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Filament\Business\BusinessPanel;
use App\Filament\Business\Widgets\WalletOverview;
use App\Filament\Forms\Components\MoneyInput;
use App\Models\PayoutMethod;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use App\Support\Money;
use App\Support\Permission;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use InvalidArgumentException;
use UnitEnum;

/**
 * Store wallet (D26, M9.10, M9.11): app-order earnings net of the platform
 * commission and Stripe fees, held for the hold period, then withdrawable.
 */
class Wallet extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(static::$navigationGroup);
    }

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('Wallet');
    }

    public function getTitle(): string
    {
        return __('Wallet');
    }

    public static function canAccess(): bool
    {
        return BusinessPanel::can(Permission::ManageWallet) && ! BusinessPanel::isType(['car_showroom', 'car_rental']);
    }

    protected function getHeaderWidgets(): array
    {
        return [WalletOverview::class];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        $currency = BusinessPanel::currency();

        return $table
            ->query(WalletTransaction::query()->with('order'))
            ->heading(__('Transactions'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label(__('Date'))->dateTime()->sortable(),
                TextColumn::make('type')->label(__('Type'))->badge(),
                TextColumn::make('description')->label(__('Description')),
                TextColumn::make('amount_cents')->label(__('Amount'))->money($currency, divideBy: 100)
                    ->color(fn (int $state): string => $state < 0 ? 'danger' : 'success'),
                TextColumn::make('status')->label(__('Status'))->badge(),
                TextColumn::make('available_at')->label(__('Available on'))->date()
                    ->visible(true)->placeholder('—')
                    ->formatStateUsing(fn ($state, WalletTransaction $record) => $record->status === WalletTransactionStatus::Pending ? $state?->toDateString() : '—'),
            ])
            ->filters([
                SelectFilter::make('type')->options(WalletTransactionType::class)->multiple(),
                SelectFilter::make('status')->options(WalletTransactionStatus::class),
            ]);
    }

    protected function getHeaderActions(): array
    {
        $business = BusinessPanel::business();

        return [
            Action::make('withdraw')
                ->label(__('Request withdrawal'))
                ->icon(Heroicon::OutlinedBanknotes)
                ->modalDescription(fn (): string => __('Available: :amount. The platform transfers it and updates the status.', [
                    'amount' => Money::format(app(WalletService::class)->walletFor($business)->refresh()->available_cents, $business->currency),
                ]))
                ->fillForm(fn (): array => [
                    'amount_cents' => max(0, app(WalletService::class)->walletFor($business)->refresh()->available_cents),
                    'payout_method_id' => PayoutMethod::query()->orderByDesc('is_default')->value('id'),
                ])
                ->schema([
                    MoneyInput::make('amount_cents')->label(__('Amount'))->required()->minValue(0.01),
                    Select::make('payout_method_id')->label(__('Send to'))->required()
                        ->options(fn (): array => PayoutMethod::query()->get()->mapWithKeys(fn (PayoutMethod $method): array => [$method->id => $method->summary()])->all())
                        ->helperText(__('Add accounts under Finance → Payout accounts.')),
                    Textarea::make('note')->label(__('Note')),
                ])
                ->action(function (array $data) use ($business): void {
                    try {
                        app(WalletService::class)->requestPayout(
                            $business,
                            (int) $data['amount_cents'],
                            PayoutMethod::query()->findOrFail($data['payout_method_id']),
                            $data['note'] ?? null,
                            auth()->id(),
                        );
                        Notification::make()->success()->title(__('Withdrawal requested'))->send();
                    } catch (InvalidArgumentException $exception) {
                        Notification::make()->danger()->title($exception->getMessage())->send();
                    }
                }),
        ];
    }
}
