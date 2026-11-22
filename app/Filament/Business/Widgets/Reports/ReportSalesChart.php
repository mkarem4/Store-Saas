<?php

namespace App\Filament\Business\Widgets\Reports;

use App\Filament\Business\BusinessPanel;
use App\Filament\Business\ReportPeriod;
use App\Services\ReportService;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class ReportSalesChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '280px';

    public function getHeading(): string
    {
        return __('Revenue by day');
    }

    protected function getData(): array
    {
        $business = BusinessPanel::business();
        [$from, $until] = ReportPeriod::from($this->pageFilters);
        $daily = app(ReportService::class)->dailySales($business, $from, $until)->keyBy('date');

        $labels = [];
        $values = [];

        for ($day = $from->copy(); $day->lessThanOrEqualTo($until); $day->addDay()) {
            $labels[] = $day->translatedFormat('j M');
            $values[] = round(($daily[$day->toDateString()]['total_cents'] ?? 0) / 100, 2);
        }

        return [
            'datasets' => [[
                'label' => __('Revenue'),
                'data' => $values,
                'borderColor' => '#1E40AF',
                'backgroundColor' => 'rgba(30, 64, 175, 0.08)',
                'pointRadius' => count($values) > 31 ? 0 : 3,
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
                'x' => ['grid' => ['display' => false], 'ticks' => ['color' => '#78716c', 'maxRotation' => 0, 'autoSkip' => true]],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
