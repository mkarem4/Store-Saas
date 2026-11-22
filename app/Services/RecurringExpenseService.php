<?php

namespace App\Services;

use App\Enums\ExpenseSource;
use App\Models\Expense;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Recurring expenses (M5.2), e.g. monthly rent. A template row (is_recurring)
 * spawns one dated expense per period when next_due_date arrives.
 */
class RecurringExpenseService
{
    public function generateDue(?Carbon $today = null): int
    {
        $today ??= now();
        $created = 0;

        $templates = Expense::query()
            ->withoutGlobalScope('business')
            ->where('is_recurring', true)
            ->whereNotNull('next_due_date')
            ->whereDate('next_due_date', '<=', $today->toDateString())
            ->get();

        foreach ($templates as $template) {
            $created += DB::transaction(function () use ($template, $today): int {
                $count = 0;
                $due = $template->next_due_date->copy();

                while ($due->lessThanOrEqualTo($today)) {
                    Expense::query()->create([
                        'business_id' => $template->business_id,
                        'expense_category_id' => $template->expense_category_id,
                        'amount_cents' => $template->amount_cents,
                        'description' => $template->description,
                        'expense_date' => $due->toDateString(),
                        'source' => ExpenseSource::Recurring,
                        'reference_type' => $template->getMorphClass(),
                        'reference_id' => $template->id,
                        'created_by' => $template->created_by,
                        'is_recurring' => false,
                    ]);

                    $due = $this->advance($due, (string) $template->recurrence);
                    $count++;
                }

                $template->update(['next_due_date' => $due->toDateString()]);

                return $count;
            });
        }

        return $created;
    }

    public function advance(Carbon $date, string $recurrence): Carbon
    {
        return match ($recurrence) {
            'weekly' => $date->copy()->addWeek(),
            'yearly' => $date->copy()->addYearNoOverflow(),
            default => $date->copy()->addMonthNoOverflow(),
        };
    }
}
