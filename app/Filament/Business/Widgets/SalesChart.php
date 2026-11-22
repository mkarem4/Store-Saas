<?php

namespace App\Filament\Business\Widgets;

use App\Filament\Business\BusinessPanel;
use App\Services\ReportService;
use App\Support\Permission;
use Filament\Widgets\ChartWidget;

class SalesChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    public static function canView(): bool
    {
        return BusinessPanel::can(Permission::ViewReports);
    }

    public function getHeading(): string
    {
        return __('Sales, last 14 days');
    }

    protected function getData(): array
    {
        $business = BusinessPanel::business();
        $end = now($business->timezone);
        $start = $end->copy()->subDays(13);
        $daily = app(ReportService::class)->dailySales($business, $start, $end)->keyBy('date');

        $labels = [];
        $values = [];

        for ($day = $start->copy(); $day->lessThanOrEqualTo($end); $day->addDay()) {
            $labels[] = $day->format('M j');
            $values[] = round(($daily[$day->toDateString()]['total_cents'] ?? 0) / 100, 2);
        }

        return [
            'datasets' => [['label' => __('Revenue'), 'data' => $values, 'fill' => true]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
