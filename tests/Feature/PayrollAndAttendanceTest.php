<?php

use App\Enums\PayType;
use App\Enums\SaleStatus;
use App\Models\Business;
use App\Models\BusinessType;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\PayrollRun;
use App\Models\Sale;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\PayrollService;
use Illuminate\Support\Carbon;

function createClosedEntry(Business $business, Employee $employee, Carbon $in, Carbon $out): TimeEntry
{
    return TimeEntry::query()->create([
        'business_id' => $business->id,
        'employee_id' => $employee->id,
        'clock_in_at' => $in,
        'clock_out_at' => $out,
        'clock_in_ip' => '10.0.0.5',
    ]);
}

it('computes a full monthly salary for a full-month period', function (): void {
    $business = Business::factory()->create();
    $employee = Employee::factory()->create([
        'business_id' => $business->id,
        'pay_type' => PayType::Monthly,
        'monthly_salary_cents' => 400000,
    ]);

    $run = app(PayrollService::class)->generate(
        $business,
        now()->startOfMonth(),
        now()->endOfMonth(),
    );

    expect($run->status)->toBe('draft')
        ->and($run->gross_cents)->toBe(400000)
        ->and($run->total_cents)->toBe(400000);

    $payslip = $run->payslips->firstWhere('employee_id', $employee->id);

    expect($payslip)->not->toBeNull()
        ->and($payslip->base_cents)->toBe(400000);
});

it('prorates a monthly salary for a partial period', function (): void {
    $business = Business::factory()->create();
    $employee = Employee::factory()->create([
        'business_id' => $business->id,
        'pay_type' => PayType::Monthly,
        'monthly_salary_cents' => 300000,
    ]);

    $start = now()->startOfMonth();
    $end = $start->copy()->addDays(15);

    $run = app(PayrollService::class)->generate($business, $start, $end);

    $payslip = $run->payslips->firstWhere('employee_id', $employee->id);
    $expected = (int) round(300000 * 16 / $start->copy()->daysInMonth);

    expect($payslip->base_cents)->toBe($expected);
});

it('pays hourly wages from timed entries within the period', function (): void {
    $business = Business::factory()->create();
    $employee = Employee::factory()->hourly()->create([
        'business_id' => $business->id,
        'hourly_rate_cents' => 1200,
    ]);

    $day = now()->startOfDay();
    createClosedEntry($business, $employee, $day->copy()->setTime(9, 0), $day->copy()->setTime(10, 0));
    createClosedEntry($business, $employee, $day->copy()->setTime(12, 0), $day->copy()->setTime(12, 30));

    $run = app(PayrollService::class)->generate($business, $day, $day);

    $payslip = $run->payslips->firstWhere('employee_id', $employee->id);

    expect($payslip->minutes_worked)->toBe(90)
        ->and($payslip->base_cents)->toBe(1800)
        ->and($run->gross_cents)->toBe(1800);
});

it('adds commission on net completed sales for car showroom staff', function (): void {
    $type = BusinessType::factory()->create(['key' => 'car_showroom']);
    $business = Business::factory()->create(['business_type_id' => $type->id]);
    $employee = Employee::factory()->withCommission(500)->create([
        'business_id' => $business->id,
    ]);

    $day = now()->startOfDay();

    Sale::factory()->create([
        'business_id' => $business->id,
        'employee_id' => $employee->id,
        'subtotal_cents' => 10000,
        'discount_cents' => 1000,
        'total_cents' => 9000,
        'status' => SaleStatus::Completed,
        'sold_at' => $day->copy()->setTime(11, 0),
    ]);

    $run = app(PayrollService::class)->generate($business, $day, $day);

    $payslip = $run->payslips->firstWhere('employee_id', $employee->id);

    expect($payslip->commission_cents)->toBe(450); // 5% of net 9000
});

it('marks an approved run and records a payroll expense', function (): void {
    $business = Business::factory()->create();
    $employee = Employee::factory()->create([
        'business_id' => $business->id,
        'monthly_salary_cents' => 100000,
    ]);

    $run = app(PayrollService::class)->generate($business, now()->startOfMonth(), now()->endOfMonth());

    $approver = User::factory()->businessOwner($business)->create();

    app(PayrollService::class)->approve($run, $approver->id);

    $run->refresh();

    expect($run->status)->toBe('approved')
        ->and($run->approved_by)->toBe($approver->id);

    $expense = Expense::query()
        ->where('reference_type', PayrollRun::class)
        ->where('reference_id', $run->id)
        ->first();

    expect($expense)->not->toBeNull()
        ->and($expense->amount_cents)->toBe($run->total_cents);
});

it('prevents approving the same run twice', function (): void {
    $business = Business::factory()->create();

    $run = app(PayrollService::class)->generate($business, now()->startOfMonth(), now()->endOfMonth());
    app(PayrollService::class)->approve($run);

    expect(fn () => app(PayrollService::class)->approve($run->refresh()))
        ->toThrow(InvalidArgumentException::class, 'already approved');
});

it('clocks an employee in and out from an allowed IP', function (): void {
    $business = Business::factory()->create(['allowed_ips' => ['10.0.0.5']]);
    $employee = Employee::factory()->create(['business_id' => $business->id]);

    $service = app(AttendanceService::class);

    $entry = $service->clockIn($employee, '10.0.0.5');

    expect($entry->clock_in_at)->not->toBeNull()
        ->and($entry->clock_out_at)->toBeNull();

    // A second clock-in while open is rejected.
    expect(fn () => $service->clockIn($employee, '10.0.0.5'))
        ->toThrow(InvalidArgumentException::class, 'already clocked in');

    $closed = $service->clockOut($employee, '10.0.0.5');

    expect($closed->clock_out_at)->not->toBeNull();

    // Clocking out again is rejected.
    expect(fn () => $service->clockOut($employee, '10.0.0.5'))
        ->toThrow(InvalidArgumentException::class, 'not clocked in');
});

it('blocks clock-in from a non-allowed business IP', function (): void {
    $business = Business::factory()->create(['allowed_ips' => ['10.0.0.5']]);
    $employee = Employee::factory()->create(['business_id' => $business->id]);

    expect(fn () => app(AttendanceService::class)->clockIn($employee, '203.0.113.9'))
        ->toThrow(InvalidArgumentException::class, 'store network');
});

it('allows clock-in when the business has no IP restriction', function (): void {
    $business = Business::factory()->create(['allowed_ips' => null]);
    $employee = Employee::factory()->create(['business_id' => $business->id]);

    $entry = app(AttendanceService::class)->clockIn($employee, '203.0.113.9');

    expect($entry->id)->not->toBeNull();
});

it('pays no commission in store types other than car showroom and jewelry', function (): void {
    $business = Business::factory()->create();
    $employee = Employee::factory()->withCommission(500)->create(['business_id' => $business->id]);

    Sale::factory()->create([
        'business_id' => $business->id,
        'employee_id' => $employee->id,
        'subtotal_cents' => 10000,
        'total_cents' => 10000,
        'status' => SaleStatus::Completed,
        'sold_at' => now()->startOfDay()->setTime(15, 0),
    ]);

    expect(app(PayrollService::class)->commission($employee, now($employee->business->timezone), now($employee->business->timezone)))->toBe(0);
});

it('blocks approval while auto-closed attendance is unreviewed, and adjusts draft payslips', function (): void {
    $business = Business::factory()->create();
    $employee = Employee::factory()->create(['business_id' => $business->id, 'pay_type' => PayType::Monthly, 'monthly_salary_cents' => 300000]);

    TimeEntry::query()->create([
        'business_id' => $business->id,
        'employee_id' => $employee->id,
        'clock_in_at' => now()->startOfMonth()->setTime(14, 0),
        'clock_out_at' => now()->startOfMonth()->endOfDay(),
        'is_auto_closed' => true,
    ]);

    $service = app(PayrollService::class);
    $run = $service->generate($business, now()->startOfMonth(), now()->endOfMonth());

    expect(fn () => $service->approve($run))->toThrow(InvalidArgumentException::class, 'auto-closed');

    $slip = $run->payslips->first();
    $service->adjustPayslip($slip, 20000, 5000, 'Transport / advance');

    expect($run->refresh()->total_cents)->toBe(315000);

    TimeEntry::query()->update(['reviewed_at' => now()]);
    $service->approve($run->refresh());

    expect(fn () => $service->adjustPayslip($slip->refresh(), 0, 0))->toThrow(InvalidArgumentException::class, 'next month');
});
