<?php

namespace App\Filament\Business\Widgets\Reports;

use App\Filament\Business\BusinessPanel;
use App\Filament\Business\ReportPeriod;
use App\Services\ReportService;
use App\Support\Money;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ReportStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $business = BusinessPanel::business();
        $currency = $business->currency;
        [$from, $until] = ReportPeriod::from($this->pageFilters);
        [$prevFrom, $prevUntil] = ReportPeriod::previous($from, $until);
        $service = app(ReportService::class);
        $now = $service->summary($business, $from, $until);
        $before = $service->summary($business, $prevFrom, $prevUntil);

        $change = function (string $key, bool $upIsGood = true) use ($now, $before, $currency): array {
            $diff = $now[$key] - $before[$key];

            if ($diff === 0) {
                return [__('Same as the previous period'), 'gray', null];
            }

            $label = __(':amount vs previous period', ['amount' => Money::signed($diff, $currency)]);
            $good = $diff > 0 === $upIsGood;

            return [$label, $good ? 'success' : 'danger', $diff > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down'];
        };

        $stat = function (string $label, string $key, bool $upIsGood = true) use ($now, $change, $currency): Stat {
            [$description, $color, $icon] = $change($key, $upIsGood);

            return Stat::make($label, Money::format($now[$key], $currency))
                ->description($description)
                ->descriptionIcon($icon)
                ->color($color);
        };

        return [
            $stat(__('Revenue'), 'revenue_cents'),
            $stat(__('Gross profit'), 'gross_profit_cents'),
            $stat(__('Net profit'), 'net_profit_cents'),
            $stat(__('Expenses'), 'expenses_cents', upIsGood: false),
            $stat(__('Cost of goods'), 'cogs_cents', upIsGood: false),
            Stat::make(__('Tax collected'), Money::format($now['tax_cents'], $currency))
                ->description(__('Kept apart from revenue')),
        ];
    }
}
