<?php

namespace App\Filament\Business\Resources\Vehicles;

use App\Enums\BusinessTypeKey;
use App\Enums\PaymentMethod;
use App\Enums\VehiclePurpose;
use App\Enums\VehicleStatus;
use App\Filament\Business\BusinessPanel;
use App\Filament\Business\BusinessResource;
use App\Filament\Forms\Components\MoneyInput;
use App\Filament\Support\ClientSelect;
use App\Models\Employee;
use App\Models\Vehicle;
use App\Services\VehicleService;
use App\Support\Permission;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use InvalidArgumentException;
use UnitEnum;

/**
 * Showroom stock (S10, M2.7, M2.9) or rental fleet (M2.8) depending on the
 * store type. Each car is a unique unit identified by its VIN.
 */
class VehicleResource extends BusinessResource
{
    protected static ?string $model = Vehicle::class;

    protected static ?string $permission = Permission::ManageVehicles;

    protected static array $types = [BusinessTypeKey::CarShowroom->value, BusinessTypeKey::CarRental->value];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return BusinessPanel::typeKey() === BusinessTypeKey::CarRental->value ? __('Rentals') : __('Vehicles');
    }

    public static function getModelLabel(): string
    {
        return __('Vehicle');
    }

    public static function getPluralModelLabel(): string
    {
        return BusinessPanel::typeKey() === BusinessTypeKey::CarRental->value ? __('Fleet') : __('Vehicles');
    }

    public static function purpose(): string
    {
        return BusinessPanel::typeKey() === BusinessTypeKey::CarRental->value ? VehiclePurpose::Rental->value : VehiclePurpose::Sale->value;
    }

    public static function form(Schema $schema): Schema
    {
        $isRental = self::purpose() === VehiclePurpose::Rental->value;
        $businessId = BusinessPanel::business()?->id;

        return $schema->columns(3)->components([
            Section::make(__('Vehicle'))->columnSpan(2)->columns(3)->schema([
                TextInput::make('vin')->label(__('VIN'))->required()->maxLength(32)
                    ->dehydrateStateUsing(fn (?string $state): string => strtoupper(trim((string) $state)))
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('business_id', $businessId)),
                TextInput::make('make')->label(__('Make'))->required(),
                TextInput::make('model')->label(__('Model'))->required(),
                TextInput::make('year')->label(__('Year'))->integer()->minValue(1950)->maxValue((int) now()->addYear()->format('Y'))->required(),
                TextInput::make('color')->label(__('Color')),
                TextInput::make('mileage')->label(__('Mileage'))->integer()->minValue(0),
                TextInput::make('plate_number')->label(__('Plate number')),
                Select::make('status')->label(__('Status'))->options(VehicleStatus::class)->default(VehicleStatus::Available)->required()
                    ->disableOptionWhen(fn (string $value): bool => in_array($value, [VehicleStatus::Sold->value, VehicleStatus::Rented->value], true)),
            ]),
            Section::make($isRental ? __('Rates') : __('Prices'))->columnSpan(1)->schema($isRental ? [
                MoneyInput::make('purchase_price_cents')->label(__('Purchase price')),
                MoneyInput::make('attributes.daily_rate_cents')->label(__('Daily rate'))->required(),
                MoneyInput::make('attributes.weekly_rate_cents')->label(__('Weekly rate')),
                MoneyInput::make('attributes.monthly_rate_cents')->label(__('Monthly rate')),
            ] : [
                MoneyInput::make('purchase_price_cents')->label(__('Purchase price'))->required(),
                MoneyInput::make('listing_price_cents')->label(__('Listing price'))->required(),
            ]),
            Section::make(__('Photos'))->columnSpan(2)->schema([
                SpatieMediaLibraryFileUpload::make('photos')->hiddenLabel()->collection('photos')->multiple()->reorderable()->image()->maxFiles(12)
                    ->helperText(__('The first photo is shown in the list.')),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $currency = BusinessPanel::currency();
        $isRental = self::purpose() === VehiclePurpose::Rental->value;

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('purpose', self::purpose())->withSum('costs', 'amount_cents'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                SpatieMediaLibraryImageColumn::make('photos')->collection('photos')->limit(1)->label('')->square(),
                TextColumn::make('name')->label(__('Vehicle'))->state(fn (Vehicle $record): string => $record->displayName())
                    ->description(fn (Vehicle $record): string => $record->vin)
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(fn (Builder $q) => $q->where('vin', 'like', "%{$search}%")->orWhere('make', 'like', "%{$search}%")->orWhere('model', 'like', "%{$search}%")->orWhere('plate_number', 'like', "%{$search}%"))),
                TextColumn::make('plate_number')->label(__('Plate'))->toggleable(),
                TextColumn::make('mileage')->label(__('Mileage'))->numeric()->toggleable(),
                TextColumn::make('total_cost')->label(__('Total cost'))->visible(! $isRental)
                    ->state(fn (Vehicle $record): int => (int) $record->purchase_price_cents + (int) $record->costs_sum_amount_cents)
                    ->money($currency, divideBy: 100),
                TextColumn::make('listing_price_cents')->label(__('Listing price'))->visible(! $isRental)->money($currency, divideBy: 100),
                TextColumn::make('sold_price_cents')->label(__('Sold for'))->visible(! $isRental)->money($currency, divideBy: 100)->placeholder('—'),
                TextColumn::make('profit')->label(__('Profit'))->visible(! $isRental)
                    ->state(fn (Vehicle $record): ?int => $record->sold_price_cents === null ? null : (int) $record->sold_price_cents - (int) $record->purchase_price_cents - (int) $record->costs_sum_amount_cents)
                    ->money($currency, divideBy: 100)->placeholder('—')
                    ->color(fn (?int $state): ?string => $state === null ? null : ($state >= 0 ? 'success' : 'danger')),
                TextColumn::make('attributes.daily_rate_cents')->label(__('Daily rate'))->visible($isRental)->money($currency, divideBy: 100)->placeholder('—'),
                TextColumn::make('status')->label(__('Status'))->badge(),
            ])
            ->filters([SelectFilter::make('status')->options(VehicleStatus::class)])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    self::sellAction(),
                    Action::make('reserve')->label(__('Reserve'))->icon(Heroicon::OutlinedLockClosed)
                        ->visible(fn (Vehicle $record): bool => ! $isRental && $record->status === VehicleStatus::Available)
                        ->action(fn (Vehicle $record) => app(VehicleService::class)->reserve($record)),
                    Action::make('release')->label(__('Release reservation'))->icon(Heroicon::OutlinedLockOpen)
                        ->visible(fn (Vehicle $record): bool => $record->status === VehicleStatus::Reserved)
                        ->action(fn (Vehicle $record) => app(VehicleService::class)->release($record)),
                ]),
            ]);
    }

    public static function sellAction(): Action
    {
        return Action::make('sell')
            ->label(__('Sell'))
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success')
            ->visible(fn (Vehicle $record): bool => $record->purpose === VehiclePurpose::Sale->value && in_array($record->status, [VehicleStatus::Available, VehicleStatus::Reserved], true))
            ->fillForm(fn (Vehicle $record): array => ['sold_price_cents' => $record->listing_price_cents])
            ->schema([
                ClientSelect::make()->label(__('Buyer')),
                MoneyInput::make('sold_price_cents')->label(__('Selling price'))->required(),
                Select::make('employee_id')->label(__('Sold by'))
                    ->options(fn (): array => Employee::query()->where('is_active', true)->pluck('name', 'id')->all())
                    ->default(fn (): ?int => Employee::query()->where('user_id', auth()->id())->value('id')),
                Select::make('method')->label(__('Payment'))->options([PaymentMethod::Cash->value => PaymentMethod::Cash->label(), PaymentMethod::Card->value => PaymentMethod::Card->label()])->default(PaymentMethod::Card->value)->required(),
            ])
            ->action(function (Vehicle $record, array $data): void {
                try {
                    app(VehicleService::class)->sell(
                        $record,
                        (int) $data['sold_price_cents'],
                        (int) $data['client_id'],
                        [['method' => $data['method'], 'amount_cents' => (int) $data['sold_price_cents']]],
                        $data['employee_id'] ?? null,
                        auth()->id(),
                    );
                    Notification::make()->success()->title(__('Vehicle sold'))->send();
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();
                }
            });
    }

    public static function getRelations(): array
    {
        return [RelationManagers\CostsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVehicles::route('/'),
            'create' => Pages\CreateVehicle::route('/create'),
            'edit' => Pages\EditVehicle::route('/{record}/edit'),
        ];
    }
}
