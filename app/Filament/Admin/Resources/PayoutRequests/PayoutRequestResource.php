<?php

namespace App\Filament\Admin\Resources\PayoutRequests;

use App\Enums\PayoutStatus;
use App\Filament\Admin\Resources\PayoutRequests\Pages\ListPayoutRequests;
use App\Models\PayoutRequest;
use App\Services\WalletService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use InvalidArgumentException;
use UnitEnum;

/**
 * Store payout requests (M9.12): approve → transfer outside the system →
 * record the reference (paid), or reject (funds return to the wallet).
 */
class PayoutRequestResource extends Resource
{
    protected static ?string $model = PayoutRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Payments';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(static::$navigationGroup);
    }

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('Payout request');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Payout requests');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = PayoutRequest::query()->where('status', PayoutStatus::Requested)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextEntry::make('business.name')->label(__('Store'))->state(fn (PayoutRequest $record): string => $record->business->translatedName()),
            TextEntry::make('amount_cents')->label(__('Amount'))->money('USD', divideBy: 100),
            TextEntry::make('status')->label(__('Status'))->badge(),
            TextEntry::make('created_at')->label(__('Requested'))->dateTime(),
            TextEntry::make('requester.name')->label(__('Requested by'))->placeholder('—'),
            TextEntry::make('reference')->label(__('Transfer reference'))->placeholder('—'),
            TextEntry::make('note')->label(__('Store note'))->placeholder('—')->columnSpanFull(),
            TextEntry::make('admin_note')->label(__('Platform note'))->placeholder('—')->columnSpanFull(),
            Section::make(__('Transfer to'))->columnSpanFull()->schema([
                TextEntry::make('method_snapshot.summary')->label(__('Method'))->placeholder(__('No method selected')),
                KeyValueEntry::make('payoutMethod.details')->label(__('Bank details'))
                    ->state(fn (PayoutRequest $record): array => array_map('strval', $record->payoutMethod?->details ?? [])),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('business.name')->label(__('Store'))
                    ->state(fn (PayoutRequest $record): string => $record->business->translatedName())
                    ->searchable(),
                TextColumn::make('amount_cents')->label(__('Amount'))->money('USD', divideBy: 100)->sortable(),
                TextColumn::make('method_snapshot.summary')->label(__('Method'))->placeholder('—'),
                TextColumn::make('status')->label(__('Status'))->badge(),
                TextColumn::make('created_at')->label(__('Requested'))->since()->sortable(),
                TextColumn::make('reference')->label(__('Reference'))->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(PayoutStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('approve')
                    ->label(__('Approve'))
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('info')
                    ->visible(fn (PayoutRequest $record): bool => $record->status === PayoutStatus::Requested)
                    ->requiresConfirmation()
                    ->action(fn (PayoutRequest $record) => self::run(fn () => app(WalletService::class)->approvePayout($record, auth()->user()))),
                Action::make('markPaid')
                    ->label(__('Mark as transferred'))
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('success')
                    ->visible(fn (PayoutRequest $record): bool => $record->status->isOpen())
                    ->schema([
                        TextInput::make('reference')->label(__('Transfer reference'))->required()->maxLength(120),
                        Textarea::make('note')->label(__('Note')),
                    ])
                    ->action(fn (PayoutRequest $record, array $data) => self::run(fn () => app(WalletService::class)->markPayoutPaid($record, auth()->user(), $data['reference'], $data['note'] ?? null))),
                Action::make('reject')
                    ->label(__('Reject'))
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->visible(fn (PayoutRequest $record): bool => $record->status->isOpen())
                    ->schema([Textarea::make('reason')->label(__('Reason'))->required()])
                    ->action(fn (PayoutRequest $record, array $data) => self::run(fn () => app(WalletService::class)->rejectPayout($record, auth()->user(), $data['reason']))),
            ]);
    }

    private static function run(callable $callback): void
    {
        try {
            $callback();
            Notification::make()->success()->title(__('Payout updated'))->send();
        } catch (InvalidArgumentException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();
        }
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListPayoutRequests::route('/')];
    }
}
