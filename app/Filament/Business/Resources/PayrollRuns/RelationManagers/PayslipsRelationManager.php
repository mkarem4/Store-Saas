<?php

namespace App\Filament\Business\Resources\PayrollRuns\RelationManagers;

use App\Filament\Business\BusinessPanel;
use App\Filament\Forms\Components\MoneyInput;
use App\Models\Payslip;
use App\Services\PayrollService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class PayslipsRelationManager extends RelationManager
{
    protected static string $relationship = 'payslips';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Payslips');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $currency = BusinessPanel::currency();

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('employee'))
            ->columns([
                TextColumn::make('employee.name')->label(__('Employee')),
                TextColumn::make('hours')->label(__('Hours'))->state(fn (Payslip $record): string => number_format($record->minutes_worked / 60, 2)),
                TextColumn::make('base_cents')->label(__('Base'))->money($currency, divideBy: 100),
                TextColumn::make('allowances_cents')->label(__('Allowances'))->money($currency, divideBy: 100),
                TextColumn::make('commission_cents')->label(__('Commission'))->money($currency, divideBy: 100),
                TextColumn::make('deductions_cents')->label(__('Deductions'))->money($currency, divideBy: 100),
                TextColumn::make('total_cents')->label(__('Net pay'))->money($currency, divideBy: 100)->weight('bold'),
            ])
            ->recordActions([
                Action::make('adjust')->label(__('Allowances / deductions'))->icon(Heroicon::OutlinedPencilSquare)
                    ->visible(fn (): bool => ! $this->getOwnerRecord()->isApproved())
                    ->fillForm(fn (Payslip $record): array => [
                        'allowances_cents' => $record->allowances_cents,
                        'deductions_cents' => $record->deductions_cents,
                        'note' => $record->meta['adjustment_note'] ?? null,
                    ])
                    ->schema([
                        MoneyInput::make('allowances_cents')->label(__('Allowances')),
                        MoneyInput::make('deductions_cents')->label(__('Deductions')),
                        TextInput::make('note')->label(__('Note')),
                    ])
                    ->action(function (Payslip $record, array $data): void {
                        try {
                            app(PayrollService::class)->adjustPayslip($record, (int) $data['allowances_cents'], (int) $data['deductions_cents'], $data['note'] ?? null, auth()->id());
                        } catch (InvalidArgumentException $exception) {
                            Notification::make()->danger()->title($exception->getMessage())->send();
                        }
                    }),
            ]);
    }
}
