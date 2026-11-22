<?php

namespace App\Filament\Business\Resources\Expenses\Pages;

use App\Filament\Business\Resources\Expenses\ExpenseResource;
use App\Models\Expense;
use App\Services\RecurringExpenseService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageExpenses extends ManageRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateDataUsing(fn (array $data): array => ExpenseResource::prepare($data))
                ->after(function (Expense $record): void {
                    ExpenseResource::audit($record, 'expense.created');

                    // A template due today or earlier produces its expense right away.
                    if ($record->is_recurring) {
                        app(RecurringExpenseService::class)->generateDue();
                    }
                }),
        ];
    }
}
