<?php

namespace App\Services;

use App\Enums\ExpenseSource;
use App\Enums\PayType;
use App\Enums\SaleStatus;
use App\Models\Business;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\Sale;
use App\Models\TimeEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PayrollService
{
    public function generate(
        Business $business,
        Carbon $periodStart,
        Carbon $periodEnd,
        ?int $userId = null,
    ): PayrollRun {
        if ($periodEnd->lessThan($periodStart)) {
            throw new InvalidArgumentException(__('The period end must be after the period start.'));
        }

        return DB::transaction(function () use ($business, $periodStart, $periodEnd, $userId): PayrollRun {
            $run = PayrollRun::query()->create([
                'business_id' => $business->id,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'status' => 'draft',
            ]);

            $gross = 0;
            $commissionTotal = 0;
            $deductions = 0;

            $employees = Employee::query()
                ->where('business_id', $business->id)
                ->where('is_active', true)
                ->get();

            foreach ($employees as $employee) {
                $minutes = $this->minutesWorked($employee, $periodStart, $periodEnd);

                $base = match ($employee->pay_type) {
                    PayType::Monthly => $this->monthlyBase($employee, $periodStart, $periodEnd),
                    PayType::Hourly => (int) round($minutes / 60 * $employee->hourly_rate_cents),
                };

                $commission = $this->commission($employee, $periodStart, $periodEnd);

                $payslip = Payslip::query()->create([
                    'business_id' => $business->id,
                    'payroll_run_id' => $run->id,
                    'employee_id' => $employee->id,
                    'minutes_worked' => $minutes,
                    'base_cents' => $base,
                    'allowances_cents' => 0,
                    'deductions_cents' => 0,
                    'commission_cents' => $commission,
                    'total_cents' => $base + $commission,
                    'meta' => [
                        'pay_type' => $employee->pay_type->value,
                        'commission_rate_bps' => $employee->commission_rate_bps,
                    ],
                ]);

                $gross += $base;
                $commissionTotal += $commission;
                $deductions += $payslip->deductions_cents;
            }

            $run->update([
                'gross_cents' => $gross,
                'commission_cents' => $commissionTotal,
                'deductions_cents' => $deductions,
                'total_cents' => $gross + $commissionTotal - $deductions,
            ]);

            return $run->refresh()->load('payslips.employee');
        });
    }

    public function approve(PayrollRun $run, ?int $userId = null): PayrollRun
    {
        if ($run->isApproved()) {
            throw new InvalidArgumentException(__('This payroll run is already approved.'));
        }

        return DB::transaction(function () use ($run, $userId): PayrollRun {
            $run->update([
                'status' => 'approved',
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            $category = ExpenseCategory::query()->firstOrCreate(
                ['business_id' => $run->business_id, 'key' => 'salaries'],
                ['name' => ['en' => 'Salaries', 'ar' => 'رواتب'], 'is_system' => true],
            );

            Expense::query()->updateOrCreate(
                [
                    'business_id' => $run->business_id,
                    'reference_type' => PayrollRun::class,
                    'reference_id' => $run->id,
                ],
                [
                    'expense_category_id' => $category->id,
                    'amount_cents' => $run->total_cents,
                    'description' => __('Payroll :from to :to', [
                        'from' => $run->period_start->toDateString(),
                        'to' => $run->period_end->toDateString(),
                    ]),
                    'expense_date' => $run->period_end->toDateString(),
                    'source' => ExpenseSource::Payroll,
                    'is_recurring' => false,
                    'created_by' => $userId,
                ],
            );

            return $run->refresh();
        });
    }

    public function minutesWorked(Employee $employee, Carbon $start, Carbon $end): int
    {
        $entries = TimeEntry::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('clock_in_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->get();

        $minutes = 0;

        foreach ($entries as $entry) {
            $clockOut = $entry->clock_out_at ?? now();

            if ($clockOut->greaterThan($end->copy()->endOfDay())) {
                $clockOut = $end->copy()->endOfDay();
            }

            $minutes += max(0, (int) $entry->clock_in_at->diffInMinutes($clockOut));
        }

        return $minutes;
    }

    public function commission(Employee $employee, Carbon $start, Carbon $end): int
    {
        if ($employee->commission_rate_bps <= 0) {
            return 0;
        }

        $sales = Sale::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [SaleStatus::Completed, SaleStatus::PartiallyRefunded])
            ->whereBetween('sold_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->get();

        $net = (int) $sales->sum(fn (Sale $sale): int => max(0, (int) $sale->subtotal_cents - (int) $sale->discount_cents));

        return (int) round($net * $employee->commission_rate_bps / 10000);
    }

    private function monthlyBase(Employee $employee, Carbon $start, Carbon $end): int
    {
        $salary = (int) $employee->monthly_salary_cents;

        if ($salary === 0) {
            return 0;
        }

        $daysInMonth = $start->copy()->daysInMonth;
        $daysInPeriod = $start->diffInDays($end) + 1;

        if ($daysInPeriod >= $daysInMonth) {
            return $salary;
        }

        return (int) round($salary * $daysInPeriod / $daysInMonth);
    }
}
