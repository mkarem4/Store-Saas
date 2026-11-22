<?php

namespace App\Filament\Business\Widgets\Reports;

use App\Filament\Business\BusinessPanel;
use App\Filament\Business\ReportPeriod;
use App\Services\ReportService;
use Filament\Tables\Columns\Column;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Carbon;

/**
 * A small read-only table fed by ReportService for the selected period.
 */
abstract class ReportTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 1;

    abstract protected function heading(): string;

    /**
     * @return array<int|string, array<string, mixed>>
     */
    abstract protected function rows(ReportService $service, Carbon $from, Carbon $until): array;

    /**
     * @return array<int, Column>
     */
    abstract protected function columns(string $currency): array;

    public function table(Table $table): Table
    {
        return $table
            ->heading($this->heading())
            ->records(function (): array {
                [$from, $until] = ReportPeriod::from($this->pageFilters);

                return collect($this->rows(app(ReportService::class), $from, $until))->values()->keyBy(fn ($row, $index) => $index + 1)->all();
            })
            ->columns($this->columns(BusinessPanel::currency()))
            ->paginated(false)
            ->emptyStateHeading(__('Nothing in this period'))
            ->emptyStateIcon('heroicon-o-chart-bar');
    }
}
