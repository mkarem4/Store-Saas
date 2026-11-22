<?php

namespace App\Filament\Staff\Pages;

use App\Enums\PaymentMethod;
use App\Enums\VehicleStatus;
use App\Filament\Business\BusinessPanel;
use App\Filament\Forms\Components\MoneyInput;
use App\Filament\Staff\StaffPanel;
use App\Filament\Support\ClientSelect;
use App\Models\Vehicle;
use App\Services\VehicleService;
use App\Support\Permission;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use InvalidArgumentException;

/**
 * Car sale by a showroom employee (S4, M2.9): the sale and the commission are
 * attributed to them. Costs and profit stay hidden (rule 6).
 */
class SellVehicle extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('Sell a car');
    }

    public function getTitle(): string
    {
        return __('Sell a car');
    }

    public static function canAccess(): bool
    {
        return BusinessPanel::isType(['car_showroom']) && BusinessPanel::can(Permission::SellVehicles) && StaffPanel::employee() !== null;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Vehicle::query()->where('purpose', 'sale')->whereIn('status', [VehicleStatus::Available, VehicleStatus::Reserved]))
            ->columns([
                TextColumn::make('name')->label(__('Vehicle'))->state(fn (Vehicle $record): string => $record->displayName())->description(fn (Vehicle $record): string => $record->vin)
                    ->searchable(['vin', 'make', 'model']),
                TextColumn::make('color')->label(__('Color')),
                TextColumn::make('mileage')->label(__('Mileage'))->numeric(),
                TextColumn::make('listing_price_cents')->label(__('Listing price'))->money(BusinessPanel::currency(), divideBy: 100),
                TextColumn::make('status')->label(__('Status'))->badge(),
            ])
            ->recordActions([
                Action::make('sell')->label(__('Sell'))->icon(Heroicon::OutlinedBanknotes)->color('success')->button()
                    ->fillForm(fn (Vehicle $record): array => ['sold_price_cents' => $record->listing_price_cents, 'method' => PaymentMethod::Card->value])
                    ->schema([
                        ClientSelect::make()->label(__('Buyer')),
                        MoneyInput::make('sold_price_cents')->label(__('Selling price'))->required(),
                        Select::make('method')->label(__('Payment'))->required()->options([PaymentMethod::Cash->value => PaymentMethod::Cash->label(), PaymentMethod::Card->value => PaymentMethod::Card->label()]),
                    ])
                    ->action(function (Vehicle $record, array $data): void {
                        try {
                            app(VehicleService::class)->sell($record, (int) $data['sold_price_cents'], (int) $data['client_id'], [['method' => $data['method'], 'amount_cents' => (int) $data['sold_price_cents']]], StaffPanel::employee()?->id, auth()->id());
                            Notification::make()->success()->title(__('Vehicle sold'))->send();
                        } catch (InvalidArgumentException $exception) {
                            Notification::make()->danger()->title($exception->getMessage())->send();
                        }
                    }),
            ]);
    }
}
