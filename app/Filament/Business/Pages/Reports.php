<?php

namespace App\Filament\Business\Pages;

use App\Exports\ReportExport;
use App\Filament\Business\BusinessPanel;
use App\Filament\Business\ReportPeriod;
use App\Filament\Business\Widgets\Reports\ChannelsTable;
use App\Filament\Business\Widgets\Reports\ExpensesTable;
use App\Filament\Business\Widgets\Reports\LowStockTable;
use App\Filament\Business\Widgets\Reports\ReportSalesChart;
use App\Filament\Business\Widgets\Reports\ReportStats;
use App\Filament\Business\Widgets\Reports\TaxTable;
use App\Filament\Business\Widgets\Reports\TeamTable;
use App\Filament\Business\Widgets\Reports\TopProductsTable;
use App\Filament\Business\Widgets\Reports\VehicleProfitsTable;
use App\Services\ReportService;
use App\Support\Permission;
use BackedEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Maatwebsite\Excel\Facades\Excel;
use UnitEnum;

/**
 * Income, expenses and net profit (S9, M6) for today, this week, this month
 * or a custom period in the store's timezone, with PDF/Excel export (M6.5).
 */
class Reports extends Dashboard
{
    use HasFiltersForm;

    protected static string $routePath = '/reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(static::$navigationGroup);
    }

    public static function getNavigationLabel(): string
    {
        return __('Reports');
    }

    public function getTitle(): string
    {
        return __('Reports');
    }

    public static function canAccess(): bool
    {
        return BusinessPanel::can(Permission::ViewReports);
    }

    public function getColumns(): int|array
    {
        return ['md' => 2];
    }

    public function getWidgets(): array
    {
        return [
            ReportStats::class,
            ReportSalesChart::class,
            TopProductsTable::class,
            ExpensesTable::class,
            ChannelsTable::class,
            TeamTable::class,
            TaxTable::class,
            LowStockTable::class,
            VehicleProfitsTable::class,
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        $custom = fn (Get $get): bool => $get('preset') === 'custom';

        return $schema->columns(['md' => 3])->components([
            ToggleButtons::make('preset')->label(__('Period'))->grouped()->default('month')
                ->options(['today' => __('Today'), 'week' => __('This week'), 'month' => __('This month'), 'custom' => __('Custom')]),
            DatePicker::make('from')->label(__('From'))->visible($custom)->native(false),
            DatePicker::make('until')->label(__('Until'))->visible($custom)->native(false),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function reportData(): array
    {
        $business = BusinessPanel::business();
        [$from, $until] = ReportPeriod::from($this->filters);
        $service = app(ReportService::class);

        return [
            'business' => $business,
            'from' => $from,
            'until' => $until,
            'summary' => $service->summary($business, $from, $until),
            'byEmployee' => $service->salesByEmployee($business, $from, $until),
            'hours' => $service->hoursByEmployee($business, $from, $until),
            'tax' => $service->taxReport($business, $from, $until),
            'vehicles' => BusinessPanel::isType(['car_showroom']) ? $service->vehicleProfits($business, $from, $until) : collect(),
            'lowStock' => BusinessPanel::isType(['car_showroom', 'car_rental', 'restaurant']) ? collect() : $service->lowStock($business),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('pdf')->label(__('PDF'))->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->action(function () {
                        $data = $this->reportData();
                        $pdf = Pdf::loadView('reports.pdf', $data)->setPaper('a4');

                        return response()->streamDownload(fn () => print ($pdf->output()), 'report-'.$data['from']->toDateString().'-'.$data['until']->toDateString().'.pdf');
                    }),
                Action::make('excel')->label(__('Excel'))->icon(Heroicon::OutlinedTableCells)
                    ->action(function () {
                        $data = $this->reportData();

                        return Excel::download(new ReportExport($data), 'report-'.$data['from']->toDateString().'-'.$data['until']->toDateString().'.xlsx');
                    }),
            ])->label(__('Export'))->icon(Heroicon::OutlinedArrowDownTray)->button()->color('gray'),
        ];
    }
}
