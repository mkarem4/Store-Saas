<?php

namespace App\Filament\Business\Resources\Orders;

use App\Enums\BusinessTypeKey;
use App\Enums\FulfillmentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Filament\Business\BusinessPanel;
use App\Filament\Business\BusinessResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Support\Ltr;
use App\Support\Money;
use App\Support\Permission;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use Stripe\Exception\ApiErrorException;
use UnitEnum;

/**
 * Incoming app orders (M7): live list, accept (captures the card), prepare,
 * ready, complete (settles to the wallet), reject/cancel with a reason.
 */
class OrderResource extends BusinessResource
{
    protected static ?string $model = Order::class;

    protected static ?string $permission = Permission::ManageOrders;

    protected static array $exceptTypes = [BusinessTypeKey::CarShowroom->value, BusinessTypeKey::CarRental->value];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(static::$navigationGroup);
    }

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('App order');
    }

    public static function getPluralModelLabel(): string
    {
        return __('App orders');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Order::query()->where('status', OrderStatus::Pending)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function infolist(Schema $schema): Schema
    {
        $currency = BusinessPanel::currency();

        return $schema->columns(1)->components([
            Section::make(__('Details'))->columnSpanFull()->columns(['default' => 1, 'sm' => 2, 'lg' => 4])->schema([
                TextEntry::make('number')->label(__('Order #')),
                TextEntry::make('status')->label(__('Status'))->badge(),
                TextEntry::make('payment_status')->label(__('Payment'))->badge(),
                TextEntry::make('fulfillment')->label(__('Fulfillment'))->badge()->color('gray'),
                TextEntry::make('customer.name')->label(__('Customer')),
                TextEntry::make('customer.phone')->label(__('Phone'))->formatStateUsing(fn (?string $state): ?HtmlString => Ltr::wrap($state))->placeholder('—'),
                TextEntry::make('placed_at')->label(__('Placed'))->dateTime(),
                TextEntry::make('expires_at')->label(__('Accept before'))->dateTime()->visible(fn (Order $record): bool => $record->isPending()),
                TextEntry::make('delivery_address')->label(__('Delivery address'))->columnSpan(['sm' => 2, 'lg' => 3])
                    ->visible(fn (Order $record): bool => $record->fulfillment === FulfillmentType::Delivery)
                    ->state(fn (Order $record): string => collect([$record->delivery_address['line1'] ?? null, $record->delivery_address['line2'] ?? null, $record->delivery_address['city'] ?? null, $record->delivery_address['phone'] ?? null, $record->delivery_address['notes'] ?? null])->filter()->implode(' · '))
                    ->url(fn (Order $record): ?string => isset($record->delivery_address['latitude'])
                        ? 'https://www.google.com/maps/search/?api=1&query='.$record->delivery_address['latitude'].','.$record->delivery_address['longitude'] : null, shouldOpenInNewTab: true),
                TextEntry::make('delivery_distance_km')->label(__('Distance'))->suffix(' km')->placeholder('—')
                    ->visible(fn (Order $record): bool => $record->fulfillment === FulfillmentType::Delivery),
                TextEntry::make('notes')->label(__('Customer notes'))->placeholder('—')->columnSpanFull(),
            ]),
            Section::make(__('Items'))->columnSpanFull()->schema([
                RepeatableEntry::make('items')->hiddenLabel()->columns(4)->schema([
                    TextEntry::make('name')->label(__('Item'))
                        ->belowContent(fn ($record): ?string => $record->modifiers->isEmpty() ? null : $record->modifiers->map(fn ($modifier): string => '+ '.$modifier->name)->implode(', ')),
                    TextEntry::make('qty')->label(__('Qty'))->numeric(3),
                    TextEntry::make('unit_price_cents')->label(__('Unit price'))->money($currency, divideBy: 100),
                    TextEntry::make('total_cents')->label(__('Total'))->money($currency, divideBy: 100),
                ]),
            ]),
            Section::make(__('Totals'))->columnSpanFull()->columns(4)->schema([
                TextEntry::make('subtotal_cents')->label(__('Items'))->money($currency, divideBy: 100),
                TextEntry::make('delivery_fee_cents')->label(__('Delivery'))->money($currency, divideBy: 100),
                TextEntry::make('tax_cents')->label(__('Tax'))->money($currency, divideBy: 100),
                TextEntry::make('total_cents')->label(__('Total paid'))->money($currency, divideBy: 100),
                TextEntry::make('platform_fee_cents')->label(__('Platform commission'))->money($currency, divideBy: 100),
                TextEntry::make('gateway_fee_cents')->label(__('Payment fee'))->money($currency, divideBy: 100),
                TextEntry::make('store_payout_cents')->label(__('Your net'))->money($currency, divideBy: 100),
                TextEntry::make('refunded_cents')->label(__('Refunded'))->money($currency, divideBy: 100),
            ]),
            Section::make(__('History'))->columnSpanFull()->collapsed()->schema([
                RepeatableEntry::make('statusHistory')->hiddenLabel()->columns(3)->schema([
                    TextEntry::make('created_at')->label(__('When'))->dateTime(),
                    TextEntry::make('status')->label(__('Status'))->badge(),
                    TextEntry::make('note')->label(__('Note'))->placeholder('—'),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $currency = BusinessPanel::currency();

        return $table
            ->poll('10s')
            ->defaultSort('placed_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['customer', 'items']))
            ->columns([
                TextColumn::make('number')->label(__('Order #'))->searchable()->weight('medium')->extraAttributes(['class' => 'yl-ltr']),
                TextColumn::make('placed_at')->label(__('Placed'))->since()->sortable()
                    ->dateTimeTooltip(),
                TextColumn::make('customer.name')->label(__('Customer'))->searchable()
                    ->description(fn (Order $record): ?HtmlString => Ltr::wrap($record->customer?->phone)),
                TextColumn::make('fulfillment')->label(__('Fulfillment'))->badge()->color('gray'),
                TextColumn::make('total_cents')->label(__('Total'))->money($currency, divideBy: 100)->sortable()
                    ->description(fn (Order $record): string => trans_choice(':count item|:count items', $record->items->count())),
                // Payment and order status are separate facts: the card icon marks the payment one.
                TextColumn::make('payment_status')->label(__('Payment'))->badge()->icon(Heroicon::OutlinedCreditCard),
                TextColumn::make('status')->label(__('Status'))->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->options(OrderStatus::class)->multiple(),
                SelectFilter::make('fulfillment')->options(FulfillmentType::class),
                SelectFilter::make('payment_status')->label(__('Payment'))->options(PaymentStatus::class),
            ])
            ->recordActions([
                ...self::workflowActions(),
                ActionGroup::make([ViewAction::make(), self::cancelAction()]),
            ]);
    }

    /**
     * @return array<int, Action>
     */
    public static function workflowActions(): array
    {
        $service = fn (): OrderService => app(OrderService::class);

        return [
            Action::make('accept')->label(__('Accept'))->icon(Heroicon::OutlinedCheck)->color('success')->button()
                ->visible(fn (Order $record): bool => $record->isPending() && $record->payment_status === PaymentStatus::Authorized)
                // Accepting captures the held card payment; say so before it happens.
                ->requiresConfirmation()
                ->modalHeading(fn (Order $record): string => __('Accept order :number?', ['number' => $record->number]))
                ->modalDescription(fn (Order $record): string => __('The customer\'s card is charged :amount now. Start preparing once you accept.', ['amount' => Money::format($record->total_cents, BusinessPanel::currency())]))
                ->modalSubmitActionLabel(__('Accept and charge'))
                ->action(fn (Order $record) => self::run(fn () => $service()->accept($record, auth()->id()), __('Order accepted, payment captured'))),
            Action::make('reject')->label(__('Reject'))->icon(Heroicon::OutlinedXMark)->color('danger')
                ->visible(fn (Order $record): bool => $record->isPending())
                ->schema([Textarea::make('reason')->label(__('Reason'))->required()])
                ->action(fn (Order $record, array $data) => self::run(fn () => $service()->reject($record, $data['reason'], auth()->id()), __('Order rejected, payment released'))),
            Action::make('prepare')->label(__('Start preparing'))->icon(Heroicon::OutlinedFire)->color('info')->button()
                ->visible(fn (Order $record): bool => $record->status === OrderStatus::Accepted)
                ->action(fn (Order $record) => self::run(fn () => $service()->markPreparing($record, auth()->id()))),
            Action::make('ready')->icon(Heroicon::OutlinedBellAlert)->color('primary')->button()
                ->label(fn (Order $record): string => $record->fulfillment === FulfillmentType::Delivery ? __('Out for delivery') : __('Ready for pickup'))
                ->visible(fn (Order $record): bool => $record->status === OrderStatus::Preparing)
                ->action(fn (Order $record) => self::run(fn () => $service()->markReady($record, auth()->id()))),
            Action::make('complete')->label(__('Complete'))->icon(Heroicon::OutlinedCheckBadge)->color('success')->button()
                ->visible(fn (Order $record): bool => in_array($record->status, [OrderStatus::ReadyForPickup, OrderStatus::OutForDelivery], true))
                ->requiresConfirmation()
                ->modalDescription(__('Confirm the customer received the order.'))
                ->action(fn (Order $record) => self::run(fn () => $service()->complete($record, auth()->id()), __('Order completed'))),
        ];
    }

    public static function cancelAction(): Action
    {
        return Action::make('cancel')->label(__('Cancel & refund'))->icon(Heroicon::OutlinedNoSymbol)->color('danger')
            ->visible(fn (Order $record): bool => in_array($record->status, [OrderStatus::Accepted, OrderStatus::Preparing, OrderStatus::ReadyForPickup, OrderStatus::OutForDelivery], true))
            ->modalDescription(__('The customer gets a full refund to their card.'))
            ->schema([Textarea::make('reason')->label(__('Reason'))->required()])
            ->action(fn (Order $record, array $data) => self::run(fn () => app(OrderService::class)->cancelByBusiness($record, $data['reason'], auth()->id()), __('Order cancelled and refunded')));
    }

    private static function run(callable $callback, ?string $message = null): void
    {
        try {
            $callback();
            Notification::make()->success()->title($message ?? __('Order updated'))->send();
        } catch (InvalidArgumentException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();
        } catch (ApiErrorException $exception) {
            report($exception);
            Notification::make()->danger()->title(__('The payment provider refused the request: :message', ['message' => $exception->getMessage()]))->send();
        }
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
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }
}
