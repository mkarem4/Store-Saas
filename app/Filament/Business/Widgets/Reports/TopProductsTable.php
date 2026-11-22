<?php

namespace App\Filament\Business\Widgets\Reports;

use App\Filament\Business\BusinessPanel;
use App\Services\ReportService;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Carbon;

class TopProductsTable extends ReportTable
{
    protected static ?int $sort = 3;

    protected function heading(): string
    {
        return __('Top products');
    }

    protected function rows(ReportService $service, Carbon $from, Carbon $until): array
    {
        return $service->topProducts(BusinessPanel::business(), $from, $until)->all();
    }

    protected function columns(string $currency): array
    {
        return [
            TextColumn::make('name')->label(__('Item'))->wrap(),
            TextColumn::make('qty')->label(__('Qty'))->numeric(3)->alignEnd(),
            TextColumn::make('total_cents')->label(__('Total'))->money($currency, divideBy: 100)->alignEnd(),
        ];
    }
}
