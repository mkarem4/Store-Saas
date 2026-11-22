<?php

namespace App\Filament\Staff\Pages;

use App\Enums\PayType;
use App\Filament\Business\BusinessPanel;
use App\Filament\Staff\StaffPanel;
use App\Models\TimeEntry;
use App\Services\PayrollService;
use App\Support\Money;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * The employee's own hours, attendance and commission (M4.3, M4.10). Read only.
 */
class MyHours extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return __('My hours');
    }

    public function getTitle(): string
    {
        return __('My hours');
    }

    public static function canAccess(): bool
    {
        return StaffPanel::employee() !== null;
    }

    public function content(Schema $schema): Schema
    {
        $employee = StaffPanel::employee();
        $timezone = BusinessPanel::business()?->timezone;
        $start = now($timezone)->startOfMonth();
        $end = now($timezone);
        $payroll = app(PayrollService::class);
        $minutes = $payroll->minutesWorked($employee, $start, $end);
        $commission = $payroll->commission($employee, $start, $end);
        $currency = BusinessPanel::currency();

        $cards = [
            Section::make(__('Hours this month'))->schema([Text::make(number_format($minutes / 60, 2).' h')->size('lg')->weight('bold')]),
        ];

        if ($employee->pay_type === PayType::Hourly) {
            $cards[] = Section::make(__('Earned so far'))->schema([Text::make(Money::format((int) round($minutes / 60 * $employee->hourly_rate_cents), $currency))->size('lg')->weight('bold')]);
        }

        if ($employee->commission_rate_bps > 0 && BusinessPanel::isType(PayrollService::COMMISSION_TYPES)) {
            $cards[] = Section::make(__('Commission this month'))->schema([
                Text::make(Money::format($commission, $currency))->size('lg')->weight('bold'),
                Text::make(__('On sales of :amount', ['amount' => Money::format($payroll->netSales($employee, $start, $end), $currency)])),
            ]);
        }

        return $schema->components([
            Grid::make(count($cards))->schema($cards),
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        $timezone = BusinessPanel::business()?->timezone;

        return $table
            ->query(TimeEntry::query()->where('employee_id', StaffPanel::employee()?->id))
            ->modelLabel(__('shift'))->pluralModelLabel(__('shifts'))
            ->defaultSort('clock_in_at', 'desc')
            ->columns([
                TextColumn::make('clock_in_at')->label(__('Clock in'))->dateTime(timezone: $timezone),
                TextColumn::make('clock_out_at')->label(__('Clock out'))->dateTime(timezone: $timezone)->placeholder(__('Still in')),
                TextColumn::make('hours')->label(__('Hours'))->state(fn (TimeEntry $record): string => number_format($record->minutesWorked() / 60, 2)),
                IconColumn::make('is_auto_closed')->label(__('Auto-closed'))->boolean()->falseIcon(''),
            ]);
    }
}
