<?php

namespace App\Filament\Business\Widgets;

use App\Filament\Business\BusinessPanel;
use App\Services\ReportService;
use App\Support\Permission;
use Filament\Widgets\ChartWidget;

class SalesChart extends ChartWidget
{
    protected static bool $isLazy = false;

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
            $labels[] = $day->translatedFormat('j M');
            $values[] = round(($daily[$day->toDateString()]['total_cents'] ?? 0) / 100, 2);
        }

        // One accent per chart: today. The other days stay in the neutral ramp.
        $points = array_fill(0, count($values), '#a8a29e');
        $points[count($points) - 1] = '#1E40AF';

        return [
            'datasets' => [[
                'label' => __('Revenue'),
                'data' => $values,
                'borderColor' => '#a8a29e',
                'backgroundColor' => 'rgba(168, 162, 158, 0.12)',
                'pointBackgroundColor' => $points,
                'pointBorderColor' => $points,
                'pointRadius' => array_map(fn (string $color): int => $color === '#1E40AF' ? 5 : 2, $points),
                'cubicInterpolationMode' => 'monotone',
                'fill' => true,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'y' => ['beginAtZero' => true, 'grid' => ['color' => 'rgba(120, 113, 108, 0.12)'], 'ticks' => ['color' => '#78716c']],
                'x' => ['grid' => ['display' => false], 'ticks' => ['color' => '#78716c', 'maxRotation' => 0]],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
