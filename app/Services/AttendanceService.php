<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Employee;
use App\Models\TimeEntry;
use InvalidArgumentException;

class AttendanceService
{
    public function clockIn(Employee $employee, ?string $ip = null): TimeEntry
    {
        $open = TimeEntry::query()
            ->where('employee_id', $employee->id)
            ->whereNull('clock_out_at')
            ->first();

        if ($open !== null) {
            throw new InvalidArgumentException(__('You are already clocked in.'));
        }

        $this->assertIpAllowed($employee->business, $ip);

        return TimeEntry::query()->create([
            'business_id' => $employee->business_id,
            'employee_id' => $employee->id,
            'clock_in_at' => now(),
            'clock_in_ip' => $ip,
        ]);
    }

    public function clockOut(Employee $employee, ?string $ip = null): TimeEntry
    {
        $open = TimeEntry::query()
            ->where('employee_id', $employee->id)
            ->whereNull('clock_out_at')
            ->latest('clock_in_at')
            ->first();

        if ($open === null) {
            throw new InvalidArgumentException(__('You are not clocked in.'));
        }

        $open->update([
            'clock_out_at' => now(),
            'clock_out_ip' => $ip,
        ]);

        return $open->refresh();
    }

    public function currentOpenEntry(Employee $employee): ?TimeEntry
    {
        return TimeEntry::query()
            ->where('employee_id', $employee->id)
            ->whereNull('clock_out_at')
            ->latest('clock_in_at')
            ->first();
    }

    public function autoCloseStaleEntries(): int
    {
        $entries = TimeEntry::query()
            ->withoutGlobalScope('business')
            ->whereNull('clock_out_at')
            ->where('clock_in_at', '<', now()->startOfDay())
            ->get();

        foreach ($entries as $entry) {
            $entry->update([
                'clock_out_at' => $entry->clock_in_at->copy()->endOfDay(),
                'is_auto_closed' => true,
            ]);
        }

        return $entries->count();
    }

    public function assertIpAllowed(?Business $business, ?string $ip): void
    {
        $allowed = $business?->allowed_ips ?? [];

        if ($allowed === []) {
            return;
        }

        if ($ip === null || ! in_array($ip, $allowed, true)) {
            throw new InvalidArgumentException(__('Clock in is only allowed from the store network.'));
        }
    }
}
