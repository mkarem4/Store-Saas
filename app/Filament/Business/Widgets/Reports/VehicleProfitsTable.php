<?php

namespace App\Filament\Business\Widgets\Reports;

use App\Filament\Business\BusinessPanel;
use App\Services\ReportService;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Carbon;

class VehicleProfitsTable extends ReportTable
{
    protected static ?int $sort = 8;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return BusinessPanel::isType(['car_showroom']);
    }

    protected function heading(): string
    {
        return __('Profit per car');
    }

    protected function rows(ReportService $service, Carbon $from, Carbon $until): array
    {
        return $service->vehicleProfits(BusinessPanel::business(), $from, $until)->all();
    }

    protected function columns(string $currency): array
    {
        return [
            TextColumn::make('vehicle')->label(__('Vehicle'))->description(fn (array $record): string => $record['vin']),
            TextColumn::make('sold_by')->label(__('Sold by'))->placeholder('—'),
            TextColumn::make('sold_cents')->label(__('Sold for'))->money($currency, divideBy: 100)->alignEnd(),
            TextColumn::make('cost_cents')->label(__('Total cost'))->money($currency, divideBy: 100)->alignEnd(),
            TextColumn::make('profit_cents')->label(__('Profit'))->money($currency, divideBy: 100)->alignEnd()
                ->color(fn ($state): string => $state >= 0 ? 'success' : 'danger'),
        ];
    }
}
