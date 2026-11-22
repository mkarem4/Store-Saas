<?php

namespace App\Filament\Business\Widgets\Reports;

use App\Filament\Business\BusinessPanel;
use App\Services\PayrollService;
use App\Services\ReportService;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Carbon;

class TeamTable extends ReportTable
{
    protected static ?int $sort = 6;

    protected function heading(): string
    {
        return __('Team');
    }

    protected function rows(ReportService $service, Carbon $from, Carbon $until): array
    {
        $business = BusinessPanel::business();
        $rows = [];

        foreach ($service->salesByEmployee($business, $from, $until) as $row) {
            $rows[$row['employee']] = ['employee' => $row['employee'], 'sales_count' => $row['sales_count'], 'sales_cents' => $row['sales_cents'], 'commission_cents' => 0, 'minutes' => 0];
        }

        foreach ($service->summary($business, $from, $until)['commissions'] as $row) {
            $rows[$row['employee']] = array_merge($rows[$row['employee']] ?? ['employee' => $row['employee'], 'sales_count' => 0, 'sales_cents' => $row['sales_cents'], 'minutes' => 0], ['commission_cents' => $row['commission_cents']]);
        }

        foreach ($service->hoursByEmployee($business, $from, $until) as $row) {
            $rows[$row['employee']] = array_merge($rows[$row['employee']] ?? ['employee' => $row['employee'], 'sales_count' => 0, 'sales_cents' => 0, 'commission_cents' => 0], ['minutes' => $row['minutes']]);
        }

        return array_values($rows);
    }

    protected function columns(string $currency): array
    {
        return [
            TextColumn::make('employee')->label(__('Employee')),
            TextColumn::make('sales_cents')->label(__('Sales'))->money($currency, divideBy: 100)->alignEnd(),
            TextColumn::make('commission_cents')->label(__('Commission'))->money($currency, divideBy: 100)->alignEnd()
                ->visible(BusinessPanel::isType(PayrollService::COMMISSION_TYPES)),
            TextColumn::make('minutes')->label(__('Hours'))->alignEnd()
                ->formatStateUsing(fn ($state): string => number_format(((int) $state) / 60, 1)),
        ];
    }
}
