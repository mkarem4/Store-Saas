<?php

namespace App\Filament\Business\Resources\RentalContracts;

use App\Enums\BusinessTypeKey;
use App\Enums\PaymentMethod;
use App\Enums\RentalRateType;
use App\Enums\RentalStatus;
use App\Enums\VehiclePurpose;
use App\Enums\VehicleStatus;
use App\Filament\Business\BusinessPanel;
use App\Filament\Business\BusinessResource;
use App\Filament\Forms\Components\MoneyInput;
use App\Filament\Support\ClientSelect;
use App\Models\RentalContract;
use App\Models\Vehicle;
use App\Services\RentalService;
use App\Support\Money;
use App\Support\Permission;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use InvalidArgumentException;
use UnitEnum;

/**
 * Rental contracts (S11, M2.8, D20): book → pick up (paid in full) → return.
 */
class RentalContractResource extends BusinessResource
{
    protected static ?string $model = RentalContract::class;

    protected static ?string $permission = Permission::ManageRentals;

    protected static array $types = [BusinessTypeKey::CarRental->value];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Rentals';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(static::$navigationGroup);
    }

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('Rental contract');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Contracts');
    }

    public static function form(Schema $schema): Schema
    {
        $rate = function (Get $get, Set $set): void {
            $vehicle = Vehicle::query()->find($get('vehicle_id'));
            $key = ($get('rate_type') ?: RentalRateType::Daily->value).'_rate_cents';
            $cents = (int) ($vehicle?->attributes[$key] ?? 0);

            if ($cents > 0) {
                $set('rate_cents', number_format($cents / 100, 2, '.', ''));
            }
        };

        // Main column: who rents what and when. Side column: the money.
        return $schema->columns(['default' => 1, 'lg' => 3])->components([
            Group::make([
                Section::make(__('Contract'))->columns(2)->schema([
                    Select::make('vehicle_id')->label(__('Vehicle'))->required()->searchable()->live()
                        ->options(fn (): array => Vehicle::query()->where('purpose', VehiclePurpose::Rental->value)->whereNotIn('status', [VehicleStatus::Sold, VehicleStatus::Maintenance])
                            ->get()->mapWithKeys(fn (Vehicle $vehicle): array => [$vehicle->id => $vehicle->displayName().' · '.($vehicle->plate_number ?: $vehicle->vin)])->all())
                        ->afterStateUpdated($rate),
                    ClientSelect::make(),
                    DateTimePicker::make('starts_at')->label(__('Pickup'))->default(now())->required()->seconds(false),
                    DateTimePicker::make('ends_at')->label(__('Return'))->default(now()->addDay())->required()->after('starts_at')->seconds(false),
                    Textarea::make('notes')->label(__('Notes'))->columnSpanFull(),
                ]),
            ])->columnSpan(['lg' => 2]),
            Section::make(__('Pricing'))->columnSpan(['lg' => 1])->schema([
                Select::make('rate_type')->label(__('Rate'))->options(RentalRateType::class)->default(RentalRateType::Daily->value)->required()->live()
                    ->afterStateUpdated($rate),
                MoneyInput::make('rate_cents')->label(__('Rate amount'))->required(),
                MoneyInput::make('deposit_cents')->label(__('Deposit'))->default(0)->helperText(__('Held, not revenue.')),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        $currency = BusinessPanel::currency();

        return $schema->columns(['default' => 1, 'lg' => 3])->components([
            Group::make([
                Section::make(__('Contract'))->columns(['default' => 1, 'sm' => 2])->schema([
                    TextEntry::make('contract_number')->label(__('Contract #')),
                    TextEntry::make('status')->label(__('Status'))->badge(),
                    TextEntry::make('vehicle')->label(__('Vehicle'))->state(fn (RentalContract $record): string => (string) $record->vehicle?->displayName()),
                    TextEntry::make('payment_method')->label(__('Paid by'))->badge()->placeholder('—'),
                    TextEntry::make('starts_at')->label(__('Pickup'))->dateTime(),
                    TextEntry::make('ends_at')->label(__('Return'))->dateTime(),
                ]),
                Section::make(__('Client'))->columns(['default' => 1, 'sm' => 3])->schema([
                    TextEntry::make('client.name')->label(__('Name'))->formatStateUsing(fn (RentalContract $record): string => $record->client->name.' · '.$record->client->phone),
                    TextEntry::make('client.id_number')->label(__('ID number'))->placeholder('—'),
                    TextEntry::make('client.license_number')->label(__('License #'))->placeholder('—'),
                ]),
                Section::make(__('Handover'))->columns(['default' => 1, 'sm' => 3])->schema([
                    TextEntry::make('odometer_out')->label(__('Odometer out'))->numeric()->placeholder('—'),
                    TextEntry::make('odometer_in')->label(__('Odometer in'))->numeric()->placeholder('—'),
                    TextEntry::make('extra_charges_note')->label(__('Charges note'))->placeholder('—'),
                ]),
            ])->columnSpan(['lg' => 2]),
            Section::make(__('Money'))->columnSpan(['lg' => 1])->schema([
                TextEntry::make('total_cents')->label(__('Rental total'))->money($currency, divideBy: 100),
                TextEntry::make('deposit_cents')->label(__('Deposit'))->money($currency, divideBy: 100),
                TextEntry::make('extra_charges_cents')->label(__('Extra charges'))->money($currency, divideBy: 100),
                TextEntry::make('deposit_returned_cents')->label(__('Deposit returned'))->money($currency, divideBy: 100),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $currency = BusinessPanel::currency();

        return $table
            ->defaultSort('starts_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['vehicle', 'client']))
            ->columns([
                TextColumn::make('contract_number')->label(__('Contract #'))->searchable(),
                TextColumn::make('vehicle')->label(__('Vehicle'))->state(fn (RentalContract $record): string => (string) $record->vehicle?->displayName()),
                TextColumn::make('client.name')->label(__('Client'))->searchable(),
                TextColumn::make('starts_at')->label(__('Pickup'))->dateTime()->sortable(),
                TextColumn::make('ends_at')->label(__('Return'))->dateTime()->sortable()
                    ->color(fn (RentalContract $record): ?string => $record->status === RentalStatus::Active && $record->ends_at->isPast() ? 'danger' : null),
                TextColumn::make('total_cents')->label(__('Total'))->money($currency, divideBy: 100),
                TextColumn::make('deposit_cents')->label(__('Deposit'))->money($currency, divideBy: 100)->toggleable(),
                TextColumn::make('status')->label(__('Status'))->badge(),
            ])
            ->filters([SelectFilter::make('status')->options(RentalStatus::class)])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    Action::make('pickup')->label(__('Hand over & take payment'))->icon(Heroicon::OutlinedKey)->color('success')
                        ->visible(fn (RentalContract $record): bool => $record->status === RentalStatus::Reserved)
                        ->modalDescription(fn (RentalContract $record): string => __('Collect :total now (plus the :deposit deposit).', [
                            'total' => Money::format((int) $record->total_cents, $currency),
                            'deposit' => Money::format((int) $record->deposit_cents, $currency),
                        ]))
                        ->schema([
                            TextInput::make('odometer_out')->label(__('Odometer'))->integer()->minValue(0)->required(),
                            Select::make('method')->label(__('Payment'))->options([PaymentMethod::Cash->value => PaymentMethod::Cash->label(), PaymentMethod::Card->value => PaymentMethod::Card->label()])->default(PaymentMethod::Card->value)->required(),
                        ])
                        ->action(fn (RentalContract $record, array $data) => self::run(fn () => app(RentalService::class)->pickUp($record, (int) $data['odometer_out'], PaymentMethod::from($data['method']), auth()->id()))),
                    Action::make('return')->label(__('Return vehicle'))->icon(Heroicon::OutlinedArrowUturnLeft)->color('info')
                        ->visible(fn (RentalContract $record): bool => $record->status === RentalStatus::Active)
                        ->schema([
                            TextInput::make('odometer_in')->label(__('Odometer'))->integer()->minValue(0)->required(),
                            MoneyInput::make('extra_charges_cents')->label(__('Extra charges (late, damage, fuel)'))->default(0),
                            TextInput::make('extra_charges_note')->label(__('Charges note')),
                            Toggle::make('deduct_from_deposit')->label(__('Take charges from the deposit'))->default(true),
                            Select::make('method')->label(__('Payment for the rest'))->options([PaymentMethod::Cash->value => PaymentMethod::Cash->label(), PaymentMethod::Card->value => PaymentMethod::Card->label()])->default(PaymentMethod::Cash->value),
                        ])
                        ->action(fn (RentalContract $record, array $data) => self::run(fn () => app(RentalService::class)->returnVehicle(
                            $record,
                            (int) $data['odometer_in'],
                            (int) ($data['extra_charges_cents'] ?? 0),
                            $data['extra_charges_note'] ?? null,
                            (bool) ($data['deduct_from_deposit'] ?? true),
                            PaymentMethod::from($data['method'] ?? 'cash'),
                            auth()->id(),
                        ))),
                    Action::make('cancel')->label(__('Cancel booking'))->icon(Heroicon::OutlinedXMark)->color('danger')->requiresConfirmation()
                        ->visible(fn (RentalContract $record): bool => $record->status === RentalStatus::Reserved)
                        ->action(fn (RentalContract $record) => self::run(fn () => app(RentalService::class)->cancel($record))),
                ]),
            ]);
    }

    private static function run(callable $callback): void
    {
        try {
            $callback();
            Notification::make()->success()->title(__('Contract updated'))->send();
        } catch (InvalidArgumentException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();
        }
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
            'index' => Pages\ListRentalContracts::route('/'),
            'create' => Pages\CreateRentalContract::route('/create'),
            'view' => Pages\ViewRentalContract::route('/{record}'),
        ];
    }
}
