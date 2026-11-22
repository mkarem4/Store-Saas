<?php

namespace App\Console\Commands;

use App\Services\RecurringExpenseService;
use Illuminate\Console\Command;

class GenerateRecurringExpensesCommand extends Command
{
    protected $signature = 'expenses:recurring';

    protected $description = 'Create the dated expenses for recurring templates that are due';

    public function handle(RecurringExpenseService $service): int
    {
        $created = $service->generateDue();

        $this->info("Created {$created} recurring expense(s).");

        return self::SUCCESS;
    }
}
