<?php

namespace App\Filament\Business\Pages;

use App\Enums\GoldPriceSource;
use App\Filament\Business\BusinessPanel;
use App\Filament\Forms\Components\MoneyInput;
use App\Models\GoldPrice;
use App\Services\Gold\GoldPriceService;
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
use UnitEnum;

/**
 * Daily gram price per karat (D5, M2.11). Fetched automatically every hour;
 * a manual price for today overrides the automatic one.
 */
class GoldPrices extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(static::$navigationGroup);
    }

    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        return __('Gold prices');
    }

    public function getTitle(): string
    {
        return __('Gold prices');
    }

    public static function canAccess(): bool
    {
        return BusinessPanel::isType(['jewelry']) && BusinessPanel::can(Permission::ManageGoldPrices);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(GoldPrice::query())
            ->modelLabel(__('gold price'))->pluralModelLabel(__('gold prices'))
            ->defaultSort('effective_date', 'desc')
            ->columns([
                TextColumn::make('effective_date')->label(__('Date'))->date()->sortable(),
                TextColumn::make('karat')->label(__('Karat'))->suffix('K')->badge()->color('warning'),
                TextColumn::make('price_per_gram_cents')->label(__('Price per gram'))->money(BusinessPanel::currency(), divideBy: 100),
                TextColumn::make('source')->label(__('Source'))->badge()->color(fn ($state) => $state === GoldPriceSource::Auto ? 'info' : 'gray'),
            ]);
    }

    protected function getHeaderActions(): array
    {
        $karats = app(GoldPriceService::class)->karats(BusinessPanel::business());

        return [
            Action::make('fetch')
                ->label(__('Fetch now'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->action(function (): void {
                    $written = app(GoldPriceService::class)->refresh(BusinessPanel::business());

                    $written === []
                        ? Notification::make()->warning()->title(__('No automatic price available right now. Enter today\'s price manually.'))->send()
                        : Notification::make()->success()->title(__('Prices updated'))->send();
                }),
            Action::make('manual')
                ->label(__('Set today\'s price'))
                ->icon(Heroicon::OutlinedPencilSquare)
                ->schema([
                    Select::make('karat')->label(__('Karat'))->options(array_combine($karats, array_map(fn ($k) => $k.'K', $karats)))->required(),
                    MoneyInput::make('price_per_gram_cents')->label(__('Price per gram'))->required(),
                ])
                ->action(function (array $data): void {
                    app(GoldPriceService::class)->setManual(BusinessPanel::business(), (string) $data['karat'], (int) $data['price_per_gram_cents']);
                    Notification::make()->success()->title(__('Saved'))->send();
                }),
        ];
    }
}
