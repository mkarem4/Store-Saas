<?php

namespace App\Services;

use App\Enums\AttendanceMode;
use App\Models\Business;
use App\Models\Employee;
use App\Models\TimeEntry;
use App\Support\Geo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Clock in/out inside the store only (D15, M4.9). The store owner picks the
 * check per store: network (static IP / CIDR list), location (geofence around
 * the store's coordinates), both, or either. Times always come from the server.
 */
class AttendanceService
{
    public function clockIn(Employee $employee, ?string $ip = null, mixed $latitude = null, mixed $longitude = null): TimeEntry
    {
        return DB::transaction(function () use ($employee, $ip, $latitude, $longitude): TimeEntry {
            $open = TimeEntry::query()
                ->where('employee_id', $employee->id)
                ->whereNull('clock_out_at')
                ->lockForUpdate()
                ->first();

            if ($open !== null) {
                throw new InvalidArgumentException(__('You are already clocked in.'));
            }

            $this->assertInsideStore($employee->business, $ip, $latitude, $longitude);

            return TimeEntry::query()->create([
                'business_id' => $employee->business_id,
                'employee_id' => $employee->id,
                'clock_in_at' => now(),
                'clock_in_ip' => $ip,
                'clock_in_lat' => Geo::isValidCoordinate($latitude, $longitude) ? (float) $latitude : null,
                'clock_in_lng' => Geo::isValidCoordinate($latitude, $longitude) ? (float) $longitude : null,
            ]);
        });
    }

    public function clockOut(Employee $employee, ?string $ip = null, mixed $latitude = null, mixed $longitude = null): TimeEntry
    {
        $open = $this->currentOpenEntry($employee);

        if ($open === null) {
            throw new InvalidArgumentException(__('You are not clocked in.'));
        }

        $open->update([
            'clock_out_at' => now(),
            'clock_out_ip' => $ip,
            'clock_out_lat' => Geo::isValidCoordinate($latitude, $longitude) ? (float) $latitude : null,
            'clock_out_lng' => Geo::isValidCoordinate($latitude, $longitude) ? (float) $longitude : null,
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

    /**
     * Close entries left open past the end of the store's local day and flag
     * them for review (rule 7). Payroll cannot be approved until they are.
     */
    public function autoCloseStaleEntries(): int
    {
        $closed = 0;

        $entries = TimeEntry::query()
            ->withoutGlobalScope('business')
            ->with('business')
            ->whereNull('clock_out_at')
            ->where('clock_in_at', '<', now()->subHours(1))
            ->get();

        foreach ($entries as $entry) {
            $timezone = $entry->business?->timezone ?? config('app.timezone');
            $endOfDay = $entry->clock_in_at->copy()->setTimezone($timezone)->endOfDay();

            if ($endOfDay->isFuture()) {
                continue;
            }

            $entry->update([
                'clock_out_at' => $endOfDay->setTimezone(config('app.timezone')),
                'is_auto_closed' => true,
            ]);

            $closed++;
        }

        return $closed;
    }

    /**
     * Owner correction of an entry (M4.4). Always audited with a reason.
     */
    public function review(TimeEntry $entry, Carbon $clockIn, ?Carbon $clockOut, string $reason, int $userId): TimeEntry
    {
        if (blank($reason)) {
            throw new InvalidArgumentException(__('Enter a reason for the change.'));
        }

        if ($clockOut !== null && $clockOut->lessThanOrEqualTo($clockIn)) {
            throw new InvalidArgumentException(__('Clock out must be after clock in.'));
        }

        $before = $entry->only(['clock_in_at', 'clock_out_at']);

        $entry->update([
            'clock_in_at' => $clockIn,
            'clock_out_at' => $clockOut,
            'reviewed_at' => now(),
            'reviewed_by' => $userId,
            'review_reason' => $reason,
        ]);

        activity('attendance')
            ->performedOn($entry)
            ->causedBy($userId)
            ->withProperties([
                'old' => array_map(fn ($value) => $value?->toIso8601String(), $before),
                'attributes' => [
                    'clock_in_at' => $clockIn->toIso8601String(),
                    'clock_out_at' => $clockOut?->toIso8601String(),
                ],
                'reason' => $reason,
            ])
            ->log('time_entry.reviewed');

        return $entry->refresh();
    }

    public function assertInsideStore(?Business $business, ?string $ip, mixed $latitude = null, mixed $longitude = null): void
    {
        if ($business === null) {
            throw new InvalidArgumentException(__('Your store could not be found.'));
        }

        $mode = $business->attendance_mode ?? AttendanceMode::Ip;
        $network = $this->networkCheck($business, $ip);
        $location = $this->locationCheck($business, $latitude, $longitude);

        $allowed = match ($mode) {
            AttendanceMode::Ip => $network !== false,
            AttendanceMode::Gps => $location !== false,
            AttendanceMode::Both => $network !== false && $location !== false,
            AttendanceMode::Either => ($network === null && $location === null) || $network === true || $location === true,
        };

        if ($allowed) {
            return;
        }

        throw new InvalidArgumentException(match (true) {
            $mode === AttendanceMode::Ip, $network === false && $location !== false => __('Clock in is only allowed from the store network.'),
            ! Geo::isValidCoordinate($latitude, $longitude) => __('Allow location access to clock in.'),
            default => __('Clock in is only allowed inside the store.'),
        });
    }

    /** @deprecated use assertInsideStore() */
    public function assertIpAllowed(?Business $business, ?string $ip): void
    {
        if ($this->networkCheck($business, $ip) === false) {
            throw new InvalidArgumentException(__('Clock in is only allowed from the store network.'));
        }
    }

    /**
     * true = inside, false = outside, null = not configured for this store.
     */
    private function networkCheck(?Business $business, ?string $ip): ?bool
    {
        $allowed = array_values(array_filter(array_map('trim', $business?->allowed_ips ?? [])));

        if ($allowed === []) {
            return null;
        }

        return $ip !== null && IpUtils::checkIp($ip, $allowed);
    }

    private function locationCheck(Business $business, mixed $latitude, mixed $longitude): ?bool
    {
        if (! $business->hasCoordinates()) {
            return null;
        }

        if (! Geo::isValidCoordinate($latitude, $longitude)) {
            return false;
        }

        $meters = Geo::distanceMeters(
            (float) $business->latitude,
            (float) $business->longitude,
            (float) $latitude,
            (float) $longitude,
        );

        return $meters <= max(10, (int) $business->geofence_radius_m);
    }
}
