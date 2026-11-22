<?php

namespace App\Filament\Business\Widgets\Reports;

use App\Filament\Business\BusinessPanel;
use App\Services\ReportService;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Carbon;

class TaxTable extends ReportTable
{
    protected static ?int $sort = 7;

    protected function heading(): string
    {
        return __('Tax collected');
    }

    protected function rows(ReportService $service, Carbon $from, Carbon $until): array
    {
        return $service->taxReport(BusinessPanel::business(), $from, $until)->all();
    }

    protected function columns(string $currency): array
    {
        return [
            TextColumn::make('region')->label(__('Region')),
            TextColumn::make('channel')->label(__('Channel')),
            TextColumn::make('rate')->label(__('Rate'))->suffix('%')->alignEnd(),
            TextColumn::make('taxable_cents')->label(__('Taxable'))->money($currency, divideBy: 100)->alignEnd(),
            TextColumn::make('tax_cents')->label(__('Tax'))->money($currency, divideBy: 100)->alignEnd(),
        ];
    }
}
