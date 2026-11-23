<?php

namespace App\Services;

use App\Enums\BusinessTypeKey;
use App\Enums\ExpenseCategoryKey;
use App\Enums\ExpenseSource;
use App\Enums\PayType;
use App\Enums\SaleStatus;
use App\Models\Business;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\Refund;
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

        return DB::transaction(function () use ($business, $periodStart, $periodEnd): PayrollRun {
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

    /**
     * Manual allowances/deductions on a draft payslip (M4.6).
     */
    public function adjustPayslip(Payslip $payslip, int $allowancesCents, int $deductionsCents, ?string $note = null, ?int $userId = null): Payslip
    {
        $run = $payslip->payrollRun;

        if ($run->isApproved()) {
            throw new InvalidArgumentException(__('Approved payroll cannot be changed; correct it in next month\'s run.'));
        }

        if ($allowancesCents < 0 || $deductionsCents < 0) {
            throw new InvalidArgumentException(__('Amounts cannot be negative.'));
        }

        return DB::transaction(function () use ($payslip, $run, $allowancesCents, $deductionsCents, $note, $userId): Payslip {
            $before = $payslip->only(['allowances_cents', 'deductions_cents']);

            $payslip->update([
                'allowances_cents' => $allowancesCents,
                'deductions_cents' => $deductionsCents,
                'total_cents' => max(0, (int) $payslip->base_cents + $allowancesCents + (int) $payslip->commission_cents - $deductionsCents),
                'meta' => array_merge($payslip->meta ?? [], ['adjustment_note' => $note]),
            ]);

            $this->recalculate($run);

            activity('payroll')->performedOn($payslip)->causedBy($userId)
                ->withProperties(['old' => $before, 'attributes' => $payslip->only(['allowances_cents', 'deductions_cents']), 'note' => $note])
                ->log('payslip.adjusted');

            return $payslip->refresh();
        });
    }

    public function recalculate(PayrollRun $run): PayrollRun
    {
        $payslips = $run->payslips()->get();

        $run->update([
            'gross_cents' => (int) $payslips->sum(fn (Payslip $slip): int => (int) $slip->base_cents + (int) $slip->allowances_cents),
            'commission_cents' => (int) $payslips->sum('commission_cents'),
            'deductions_cents' => (int) $payslips->sum('deductions_cents'),
            'total_cents' => (int) $payslips->sum('total_cents'),
        ]);

        return $run->refresh();
    }

    /**
     * Auto-closed attendance in the period that the owner has not reviewed
     * yet (rule 8 / M4.8). Approval is blocked while any remain.
     */
    public function unreviewedEntries(PayrollRun $run): int
    {
        return TimeEntry::query()
            ->withoutGlobalScope('business')
            ->where('business_id', $run->business_id)
            ->where('is_auto_closed', true)
            ->whereNull('reviewed_at')
            ->whereBetween('clock_in_at', [$run->period_start->copy()->startOfDay(), $run->period_end->copy()->endOfDay()])
            ->count();
    }

    public function approve(PayrollRun $run, ?int $userId = null): PayrollRun
    {
        if ($run->isApproved()) {
            throw new InvalidArgumentException(__('This payroll run is already approved.'));
        }

        $pending = $this->unreviewedEntries($run);

        if ($pending > 0) {
            throw new InvalidArgumentException(trans_choice(
                '{1} Review the auto-closed attendance entry before approving.|[2,*] Review the :count auto-closed attendance entries before approving.',
                $pending,
                ['count' => $pending],
            ));
        }

        return DB::transaction(function () use ($run, $userId): PayrollRun {
            $run->update([
                'status' => 'approved',
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            $category = ExpenseCategory::query()->firstOrCreate(
                ['business_id' => $run->business_id, 'key' => ExpenseCategoryKey::Salaries->value],
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

    /**
     * Business types whose staff earn sales commission (D16).
     */
    public const COMMISSION_TYPES = [BusinessTypeKey::CarShowroom->value, BusinessTypeKey::Jewelry->value];

    /**
     * Commission = employee rate × the value of their sales in the period,
     * net of discounts and returns, excluding tax (D16, rule 17).
     */
    public function commission(Employee $employee, Carbon $start, Carbon $end): int
    {
        if ($employee->commission_rate_bps <= 0) {
            return 0;
        }

        if (! in_array($employee->business?->businessType?->key, self::COMMISSION_TYPES, true)) {
            return 0;
        }

        return (int) round($this->netSales($employee, $start, $end) * $employee->commission_rate_bps / 10000);
    }

    public function netSales(Employee $employee, Carbon $start, Carbon $end): int
    {
        $timezone = $employee->business?->timezone ?: config('app.timezone');
        $from = Carbon::parse($start->toDateString(), $timezone)->startOfDay()->utc();
        $to = Carbon::parse($end->toDateString(), $timezone)->endOfDay()->utc();

        $sales = Sale::query()
            ->withoutGlobalScope('business')
            ->where('employee_id', $employee->id)
            ->where('status', '!=', SaleStatus::Void)
            ->whereBetween('sold_at', [$from, $to])
            ->get(['id', 'total_cents', 'tax_cents']);

        $refunded = (int) Refund::query()
            ->withoutGlobalScope('business')
            ->whereIn('sale_id', $sales->modelKeys())
            ->selectRaw('sum(amount_cents - tax_cents) as net')
            ->value('net');

        return max(0, (int) $sales->sum(fn (Sale $sale): int => (int) $sale->total_cents - (int) $sale->tax_cents) - $refunded);
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
