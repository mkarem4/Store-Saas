<?php

namespace App\Filament\Business\Resources\PayrollRuns;

use App\Filament\Business\BusinessPanel;
use App\Filament\Business\BusinessResource;
use App\Models\PayrollRun;
use App\Services\PayrollService;
use App\Support\Permission;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use InvalidArgumentException;
use UnitEnum;

/**
 * Monthly payroll (M4.5–M4.8): generate, adjust payslips, approve → booked as
 * a "Salaries" expense dated the last day of the period. Approved runs are locked.
 */
class PayrollRunResource extends BusinessResource
{
    protected static ?string $model = PayrollRun::class;

    protected static ?string $permission = Permission::ManagePayroll;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = 'Team';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(static::$navigationGroup);
    }

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('Payroll run');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Payroll');
    }

    public static function infolist(Schema $schema): Schema
    {
        $currency = BusinessPanel::currency();

        return $schema->columns(1)->components([
            Section::make(__('Details'))->columnSpanFull()->columns(['default' => 2, 'lg' => 5])->schema([
                TextEntry::make('period')->label(__('Period'))->state(fn (PayrollRun $record): string => $record->period_start->toDateString().' → '.$record->period_end->toDateString()),
                TextEntry::make('status')->label(__('Status'))->badge()->color(fn (string $state): string => $state === 'approved' ? 'success' : 'warning'),
                TextEntry::make('gross_cents')->label(__('Base + allowances'))->money($currency, divideBy: 100),
                TextEntry::make('commission_cents')->label(__('Commissions'))->money($currency, divideBy: 100),
                TextEntry::make('total_cents')->label(__('Total'))->money($currency, divideBy: 100),
                TextEntry::make('unreviewed')->label(__('Attendance to review'))->columnSpanFull()->color('danger')
                    ->state(fn (PayrollRun $record): int => app(PayrollService::class)->unreviewedEntries($record))
                    ->visible(fn (PayrollRun $record): bool => ! $record->isApproved() && app(PayrollService::class)->unreviewedEntries($record) > 0),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $currency = BusinessPanel::currency();

        return $table
            ->defaultSort('period_start', 'desc')
            ->columns([
                TextColumn::make('period_start')->label(__('From'))->date()->sortable(),
                TextColumn::make('period_end')->label(__('To'))->date(),
                TextColumn::make('payslips_count')->label(__('Employees'))->counts('payslips'),
                TextColumn::make('commission_cents')->label(__('Commissions'))->money($currency, divideBy: 100),
                TextColumn::make('total_cents')->label(__('Total'))->money($currency, divideBy: 100),
                TextColumn::make('status')->label(__('Status'))->badge()->color(fn (string $state): string => $state === 'approved' ? 'success' : 'warning'),
            ])
            ->recordActions([ViewAction::make(), self::approveAction()]);
    }

    public static function approveAction(): Action
    {
        return Action::make('approve')->label(__('Approve'))->icon(Heroicon::OutlinedCheckBadge)->color('success')
            ->visible(fn (PayrollRun $record): bool => ! $record->isApproved())
            ->requiresConfirmation()
            ->modalDescription(__('Approved payroll is booked as a salaries expense and can no longer be changed.'))
            ->action(function (PayrollRun $record): void {
                try {
                    app(PayrollService::class)->approve($record, auth()->id());
                    Notification::make()->success()->title(__('Payroll approved'))->send();
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();
                }
            });
    }

    public static function getRelations(): array
    {
        return [RelationManagers\PayslipsRelationManager::class];
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return ! $record->isApproved();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayrollRuns::route('/'),
            'view' => Pages\ViewPayrollRun::route('/{record}'),
        ];
    }
}
