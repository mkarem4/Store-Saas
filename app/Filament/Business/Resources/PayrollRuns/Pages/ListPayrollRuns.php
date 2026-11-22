<?php

namespace App\Filament\Business\Resources\PayrollRuns\Pages;

use App\Filament\Business\BusinessPanel;
use App\Filament\Business\Resources\PayrollRuns\PayrollRunResource;
use App\Services\PayrollService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class ListPayrollRuns extends ListRecords
{
    protected static string $resource = PayrollRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')->label(__('Generate payroll'))
                ->fillForm(['period_start' => now()->subMonthNoOverflow()->startOfMonth(), 'period_end' => now()->subMonthNoOverflow()->endOfMonth()])
                ->schema([
                    DatePicker::make('period_start')->label(__('From'))->required(),
                    DatePicker::make('period_end')->label(__('To'))->required()->afterOrEqual('period_start'),
                ])
                ->action(function (array $data): void {
                    try {
                        $run = app(PayrollService::class)->generate(BusinessPanel::business(), Carbon::parse($data['period_start']), Carbon::parse($data['period_end']), auth()->id());
                        $this->redirect(PayrollRunResource::getUrl('view', ['record' => $run]));
                    } catch (UniqueConstraintViolationException) {
                        Notification::make()->danger()->title(__('A payroll run already exists for this period.'))->send();
                    } catch (InvalidArgumentException $exception) {
                        Notification::make()->danger()->title($exception->getMessage())->send();
                    }
                }),
        ];
    }
}
