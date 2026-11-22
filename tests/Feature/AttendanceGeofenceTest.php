<?php

use App\Enums\AttendanceMode;
use App\Models\Business;
use App\Models\Employee;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\AttendanceService;
use Spatie\Activitylog\Models\Activity;

function storeWithMode(AttendanceMode $mode, array $ips = ['10.0.0.0/24']): Employee
{
    $business = Business::factory()->create([
        'attendance_mode' => $mode,
        'allowed_ips' => $ips,
        'latitude' => 40.7128,
        'longitude' => -74.0060,
        'geofence_radius_m' => 100,
    ]);

    return Employee::factory()->create(['business_id' => $business->id]);
}

it('accepts IPs inside an allowed CIDR range', function (): void {
    $employee = storeWithMode(AttendanceMode::Ip);

    expect(app(AttendanceService::class)->clockIn($employee, '10.0.0.77')->id)->not->toBeNull();
});

it('rejects clock-in outside the geofence (scenario 3)', function (): void {
    $employee = storeWithMode(AttendanceMode::Gps);

    // ~550 m north of the store.
    expect(fn () => app(AttendanceService::class)->clockIn($employee, '1.2.3.4', 40.7178, -74.0060))
        ->toThrow(InvalidArgumentException::class, 'inside the store');

    expect(fn () => app(AttendanceService::class)->clockIn($employee, '1.2.3.4'))
        ->toThrow(InvalidArgumentException::class, 'location access');

    $entry = app(AttendanceService::class)->clockIn($employee, '1.2.3.4', 40.71285, -74.00605);

    expect((float) $entry->clock_in_lat)->toBe(40.71285);
});

it('requires both network and location in "both" mode', function (): void {
    $employee = storeWithMode(AttendanceMode::Both);

    expect(fn () => app(AttendanceService::class)->clockIn($employee, '10.0.0.5', 40.7178, -74.0060))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => app(AttendanceService::class)->clockIn($employee, '8.8.8.8', 40.7128, -74.0060))
        ->toThrow(InvalidArgumentException::class, 'store network');

    expect(app(AttendanceService::class)->clockIn($employee, '10.0.0.5', 40.7128, -74.0060)->id)->not->toBeNull();
});

it('accepts either check in "either" mode', function (): void {
    $employee = storeWithMode(AttendanceMode::Either);

    expect(app(AttendanceService::class)->clockIn($employee, '8.8.8.8', 40.7128, -74.0060)->id)->not->toBeNull();
});

it('auto-closes forgotten entries at the end of the store day and flags them', function (): void {
    $employee = storeWithMode(AttendanceMode::Ip);

    $this->travelTo(now('America/New_York')->setTime(18, 0));
    $entry = app(AttendanceService::class)->clockIn($employee, '10.0.0.5');

    $this->travel(8)->hours();

    expect(app(AttendanceService::class)->autoCloseStaleEntries())->toBe(1);

    $entry->refresh();
    expect($entry->is_auto_closed)->toBeTrue()
        ->and($entry->clock_out_at->setTimezone('America/New_York')->format('H:i'))->toBe('23:59');
});

it('audits owner corrections with a reason', function (): void {
    $employee = storeWithMode(AttendanceMode::Ip);
    $owner = User::factory()->create(['business_id' => $employee->business_id]);
    $entry = TimeEntry::query()->create([
        'business_id' => $employee->business_id,
        'employee_id' => $employee->id,
        'clock_in_at' => now()->subHours(9),
        'clock_out_at' => now(),
        'is_auto_closed' => true,
    ]);

    expect(fn () => app(AttendanceService::class)->review($entry, now()->subHours(8), now(), '', $owner->id))
        ->toThrow(InvalidArgumentException::class, 'reason');

    $reviewed = app(AttendanceService::class)->review($entry, now()->subHours(8), now(), 'Forgot to clock out', $owner->id);

    expect($reviewed->reviewed_at)->not->toBeNull()
        ->and($reviewed->review_reason)->toBe('Forgot to clock out')
        ->and(Activity::query()->where('description', 'time_entry.reviewed')->count())->toBe(1);
});
