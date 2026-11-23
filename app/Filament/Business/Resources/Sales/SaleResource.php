<?php

namespace App\Filament\Business\Resources\Sales;

use App\Enums\SaleChannel;
use App\Enums\SaleStatus;
use App\Filament\Business\BusinessPanel;
use App\Filament\Business\BusinessResource;
use App\Models\Employee;
use App\Models\Refund;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\SaleService;
use App\Support\Money;
use App\Support\Permission;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use UnitEnum;

/**
 * All sales (POS, app, vehicle, rental) with receipts and returns (M3.4, M3.5).
 */
class SaleResource extends BusinessResource
{
    protected static ?string $model = Sale::class;

    protected static ?string $permission = Permission::ViewSales;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptRefund;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(static::$navigationGroup);
    }

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('Sale');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Sales & returns');
    }

    public static function infolist(Schema $schema): Schema
    {
        $currency = BusinessPanel::currency();

        return $schema->columns(4)->components([
            TextEntry::make('number')->label(__('Receipt #')),
            TextEntry::make('sold_at')->label(__('Date'))->dateTime(),
            TextEntry::make('channel')->label(__('Channel'))->badge(),
            TextEntry::make('status')->label(__('Status'))->badge(),
            TextEntry::make('employee.name')->label(__('Employee'))->placeholder('—'),
            TextEntry::make('client.name')->label(__('Client'))->placeholder('—'),
            TextEntry::make('order.number')->label(__('App order'))->placeholder('—'),
            TextEntry::make('notes')->label(__('Notes'))->placeholder('—'),
            Section::make(__('Items'))->columnSpanFull()->schema([
                RepeatableEntry::make('items')->hiddenLabel()->columns(5)->schema([
                    TextEntry::make('name')->label(__('Item')),
                    TextEntry::make('qty')->label(__('Qty'))->numeric(3),
                    TextEntry::make('unit_price_cents')->label(__('Unit price'))->money($currency, divideBy: 100),
                    TextEntry::make('unit_cost_cents')->label(__('Unit cost'))->money($currency, divideBy: 100),
                    TextEntry::make('total_cents')->label(__('Total'))->money($currency, divideBy: 100),
                ]),
            ]),
            Section::make(__('Totals'))->columnSpanFull()->columns(5)->schema([
                TextEntry::make('subtotal_cents')->label(__('Subtotal'))->money($currency, divideBy: 100),
                TextEntry::make('discount_cents')->label(__('Discount'))->money($currency, divideBy: 100),
                TextEntry::make('tax_cents')->label(__('Tax'))->money($currency, divideBy: 100),
                TextEntry::make('total_cents')->label(__('Total'))->money($currency, divideBy: 100),
                TextEntry::make('cogs_cents')->label(__('Cost of goods'))->money($currency, divideBy: 100),
            ]),
            Section::make(__('Returns'))->columnSpanFull()->visible(fn (Sale $record): bool => $record->refunds->isNotEmpty())->schema([
                RepeatableEntry::make('refunds')->hiddenLabel()->columns(4)->schema([
                    TextEntry::make('refunded_at')->label(__('Date'))->dateTime(),
                    TextEntry::make('amount_cents')->label(__('Amount'))->money($currency, divideBy: 100),
                    TextEntry::make('reason')->label(__('Reason'))->placeholder('—'),
                    TextEntry::make('gateway_ref')->label(__('Card refund'))->placeholder('—'),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $currency = BusinessPanel::currency();

        return $table
            ->defaultSort('sold_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['employee', 'payments']))
            ->columns([
                TextColumn::make('number')->label(__('Receipt #'))->searchable(),
                TextColumn::make('sold_at')->label(__('Date'))->dateTime()->sortable(),
                TextColumn::make('channel')->label(__('Channel'))->badge(),
                TextColumn::make('employee.name')->label(__('Employee'))->placeholder('—')->toggleable(),
                TextColumn::make('payments.method')->label(__('Paid by'))->badge()->toggleable(),
                TextColumn::make('tax_cents')->label(__('Tax'))->money($currency, divideBy: 100)->toggleable(),
                TextColumn::make('total_cents')->label(__('Total'))->money($currency, divideBy: 100)->sortable(),
                TextColumn::make('status')->label(__('Status'))->badge(),
            ])
            ->filters([
                SelectFilter::make('channel')->options(SaleChannel::class),
                SelectFilter::make('status')->options(SaleStatus::class),
                SelectFilter::make('employee_id')->label(__('Employee'))->options(fn (): array => Employee::query()->pluck('name', 'id')->all()),
                Filter::make('period')->schema([
                    DatePicker::make('from')->label(__('From'))->default(fn (): string => now(BusinessPanel::business()?->timezone)->startOfMonth()->toDateString()),
                    DatePicker::make('until')->label(__('Until')),
                ])->query(fn (Builder $query, array $data) => $query
                    ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('sold_at', '>=', $date))
                    ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('sold_at', '<=', $date))),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    Action::make('receipt')->label(__('Print receipt'))->icon(Heroicon::OutlinedPrinter)
                        ->url(fn (Sale $record): string => route('receipts.show', ['sale' => $record->id, 'print' => 1]), shouldOpenInNewTab: true),
                    Action::make('pdf')->label(__('Download PDF'))->icon(Heroicon::OutlinedDocumentArrowDown)
                        ->url(fn (Sale $record): string => route('receipts.pdf', $record->id)),
                    self::refundAction(),
                ]),
            ]);
    }

    public static function refundAction(): Action
    {
        return Action::make('refund')
            ->label(__('Return items'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->visible(fn (Sale $record): bool => BusinessPanel::can(Permission::RefundSales) && $record->isRefundable() && in_array($record->channel, [SaleChannel::Pos, SaleChannel::App], true))
            ->modalDescription(fn (Sale $record): string => $record->channel === SaleChannel::App
                ? __('The amount goes back to the customer\'s card and is deducted from your wallet.')
                : __('Hand the amount back to the customer.'))
            ->fillForm(fn (Sale $record): array => ['lines' => self::refundableLines($record), 'restock' => true])
            ->schema([
                Repeater::make('lines')->label(__('Items'))->addable(false)->deletable(false)->reorderable(false)->columns(3)->schema([
                    Hidden::make('sale_item_id'),
                    TextInput::make('name')->label(__('Item'))->disabled()->dehydrated(false),
                    TextInput::make('remaining')->label(__('Can return'))->disabled()->dehydrated(false),
                    TextInput::make('qty')->label(__('Return qty'))->numeric()->minValue(0)->default(0),
                ]),
                Toggle::make('restock')->label(__('Put items back in stock')),
                TextInput::make('reason')->label(__('Reason'))->required(),
            ])
            ->action(function (Sale $record, array $data): void {
                $lines = collect($data['lines'])
                    ->filter(fn (array $line): bool => (float) ($line['qty'] ?? 0) > 0)
                    ->map(fn (array $line): array => ['sale_item_id' => (int) $line['sale_item_id'], 'qty' => (float) $line['qty']])
                    ->values()
                    ->all();

                try {
                    $refund = app(SaleService::class)->refund($record, $lines, $data['reason'], auth()->id(), (bool) $data['restock']);

                    activity('sales')->performedOn($record)->causedBy(auth()->user())
                        ->withProperties(['refund_id' => $refund->id, 'amount_cents' => $refund->amount_cents, 'reason' => $data['reason']])
                        ->log('sale.refunded');

                    Notification::make()->success()->title(__('Refunded :amount', ['amount' => Money::format($refund->amount_cents, BusinessPanel::currency())]))->send();
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();
                }
            });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function refundableLines(Sale $sale): array
    {
        $returned = [];

        foreach (Refund::query()->where('sale_id', $sale->id)->get() as $refund) {
            foreach ((array) $refund->items as $line) {
                $returned[(int) $line['sale_item_id']] = ($returned[(int) $line['sale_item_id']] ?? 0) + (float) $line['qty'];
            }
        }

        return $sale->items->map(fn (SaleItem $item): array => [
            'sale_item_id' => $item->id,
            'name' => $item->name,
            'remaining' => round((float) $item->qty - ($returned[$item->id] ?? 0), 3),
            'qty' => 0,
        ])->filter(fn (array $line): bool => $line['remaining'] > 0)->values()->all();
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
        return [
            'index' => Pages\ListSales::route('/'),
            'view' => Pages\ViewSale::route('/{record}'),
        ];
    }
}
