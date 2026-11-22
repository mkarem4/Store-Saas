<?php

namespace App\Filament\Business\Pages;

use App\Exports\ReportExport;
use App\Filament\Business\BusinessPanel;
use App\Services\ReportService;
use App\Support\Permission;
use BackedEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use UnitEnum;

/**
 * Income, expenses and net profit (S9, M6) for a day, week, month or custom
 * period in the store's timezone, with PDF/Excel export (M6.5).
 *
 * @property-read Schema $form
 */
class Reports extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(static::$navigationGroup);
    }

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.business.pages.reports';

    /** @var array<string, mixed> */
    public ?array $filters = [];

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

    public function mount(): void
    {
        $this->form->fill(['preset' => 'month', ...$this->presetRange('month')]);
    }

    /**
     * @return array{from: string, until: string}
     */
    private function presetRange(string $preset): array
    {
        $now = now(BusinessPanel::business()?->timezone);

        return match ($preset) {
            'today' => ['from' => $now->toDateString(), 'until' => $now->toDateString()],
            'week' => ['from' => $now->copy()->startOfWeek()->toDateString(), 'until' => $now->toDateString()],
            default => ['from' => $now->copy()->startOfMonth()->toDateString(), 'until' => $now->toDateString()],
        };
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('filters')->components([
            Grid::make(3)->schema([
                ToggleButtons::make('preset')->label(__('Period'))->inline()->live()
                    ->options(['today' => __('Today'), 'week' => __('This week'), 'month' => __('This month'), 'custom' => __('Custom')])
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        if ($state !== 'custom') {
                            foreach ($this->presetRange((string) $state) as $key => $value) {
                                $set($key, $value);
                            }
                        }
                    }),
                DatePicker::make('from')->label(__('From'))->live()->disabled(fn (Get $get): bool => $get('preset') !== 'custom'),
                DatePicker::make('until')->label(__('Until'))->live()->disabled(fn (Get $get): bool => $get('preset') !== 'custom'),
            ]),
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function period(): array
    {
        $from = Carbon::parse($this->filters['from'] ?? now()->startOfMonth());
        $until = Carbon::parse($this->filters['until'] ?? now());

        return $from->greaterThan($until) ? [$until, $from] : [$from, $until];
    }

    /**
     * @return array<string, mixed>
     */
    public function reportData(): array
    {
        $business = BusinessPanel::business();
        [$from, $until] = $this->period();
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
            Action::make('pdf')->label(__('PDF'))->icon(Heroicon::OutlinedDocumentArrowDown)->color('gray')
                ->action(function () {
                    $data = $this->reportData();
                    $pdf = Pdf::loadView('reports.pdf', $data)->setPaper('a4');

                    return response()->streamDownload(fn () => print ($pdf->output()), 'report-'.$data['from']->toDateString().'-'.$data['until']->toDateString().'.pdf');
                }),
            Action::make('excel')->label(__('Excel'))->icon(Heroicon::OutlinedTableCells)->color('gray')
                ->action(function () {
                    $data = $this->reportData();

                    return Excel::download(new ReportExport($data), 'report-'.$data['from']->toDateString().'-'.$data['until']->toDateString().'.xlsx');
                }),
        ];
    }
}
