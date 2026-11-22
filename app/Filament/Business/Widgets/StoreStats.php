<?php

namespace App\Filament\Business\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Business\BusinessPanel;
use App\Models\Order;
use App\Models\TimeEntry;
use App\Services\ReportService;
use App\Support\Money;
use App\Support\Permission;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StoreStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '30s';

    public static function canView(): bool
    {
        return BusinessPanel::can(Permission::ViewReports);
    }

    protected function getStats(): array
    {
        $business = BusinessPanel::business();
        $now = now($business->timezone);
        $reports = app(ReportService::class);
        $today = $reports->summary($business, $now, $now);
        $month = $reports->summary($business, $now->copy()->startOfMonth(), $now);
        $currency = $business->currency;

        $stats = [
            Stat::make(__('Sales today'), Money::format($today['revenue_cents'], $currency))
                ->description(__(':count sales', ['count' => $today['sales_count']])),
            Stat::make(__('Net profit this month'), Money::format($month['net_profit_cents'], $currency))
                ->color($month['net_profit_cents'] >= 0 ? 'success' : 'danger')
                ->description(__('Revenue :revenue · expenses :expenses', ['revenue' => Money::format($month['revenue_cents'], $currency), 'expenses' => Money::format($month['expenses_cents'], $currency)])),
        ];

        if (! BusinessPanel::isType(['car_showroom', 'car_rental'])) {
            $pending = Order::query()->where('status', OrderStatus::Pending)->count();
            $stats[] = Stat::make(__('Orders waiting'), $pending)->color($pending > 0 ? 'warning' : 'gray');
        }

        if (! BusinessPanel::isType(['car_showroom', 'car_rental', 'restaurant'])) {
            $stats[] = Stat::make(__('Low stock items'), $reports->lowStock($business)->count())->color('danger');
        }

        $clockedIn = TimeEntry::query()->whereNull('clock_out_at')->count();
        $stats[] = Stat::make(__('Clocked in now'), $clockedIn);

        return $stats;
    }
}
