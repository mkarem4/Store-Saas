<?php

namespace App\Filament\Business\Widgets\Reports;

use App\Filament\Business\BusinessPanel;
use App\Services\ReportService;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Carbon;

class ExpensesTable extends ReportTable
{
    protected static ?int $sort = 4;

    protected function heading(): string
    {
        return __('Expenses by category');
    }

    protected function rows(ReportService $service, Carbon $from, Carbon $until): array
    {
        return collect($service->summary(BusinessPanel::business(), $from, $until)['expenses_by_category'])
            ->map(fn (int $amount, string $name): array => ['name' => $name, 'amount_cents' => $amount])
            ->values()
            ->all();
    }

    protected function columns(string $currency): array
    {
        return [
            TextColumn::make('name')->label(__('Category')),
            TextColumn::make('amount_cents')->label(__('Amount'))->money($currency, divideBy: 100)->alignEnd(),
        ];
    }
}
