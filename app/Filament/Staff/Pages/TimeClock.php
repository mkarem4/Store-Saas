<?php

namespace App\Filament\Staff\Pages;

use App\Enums\AttendanceMode;
use App\Filament\Business\BusinessPanel;
use App\Filament\Staff\StaffPanel;
use App\Models\TimeEntry;
use App\Services\AttendanceService;
use App\Support\Permission;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use InvalidArgumentException;

/**
 * Clock in/out from inside the store only (S4, M4.2, M4.9). Time is the
 * server's; location is sent from the browser when the store requires it.
 */
class TimeClock extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.staff.time-clock';

    protected static ?string $slug = 'clock';

    public static function getNavigationLabel(): string
    {
        return __('Time clock');
    }

    public function getTitle(): string
    {
        return __('Time clock');
    }

    public static function canAccess(): bool
    {
        return BusinessPanel::can(Permission::ClockAttendance) && StaffPanel::employee() !== null;
    }

    public function needsLocation(): bool
    {
        $mode = BusinessPanel::business()?->attendance_mode ?? AttendanceMode::Ip;

        return $mode !== AttendanceMode::Ip && BusinessPanel::business()?->hasCoordinates();
    }

    public function openEntry(): ?TimeEntry
    {
        $employee = StaffPanel::employee();

        return $employee === null ? null : app(AttendanceService::class)->currentOpenEntry($employee);
    }

    public function todayMinutes(): int
    {
        $employee = StaffPanel::employee();
        $timezone = BusinessPanel::business()?->timezone;

        return (int) TimeEntry::query()
            ->where('employee_id', $employee?->id)
            ->where('clock_in_at', '>=', now($timezone)->startOfDay()->utc())
            ->get()
            ->sum(fn (TimeEntry $entry): int => $entry->clock_out_at === null ? (int) $entry->clock_in_at->diffInMinutes(now()) : $entry->minutesWorked());
    }

    public function clockIn(mixed $latitude = null, mixed $longitude = null): void
    {
        $this->attempt(fn () => app(AttendanceService::class)->clockIn(StaffPanel::employee(), request()->ip(), $latitude, $longitude), __('Clocked in'));
    }

    public function clockOut(mixed $latitude = null, mixed $longitude = null): void
    {
        $this->attempt(fn () => app(AttendanceService::class)->clockOut(StaffPanel::employee(), request()->ip(), $latitude, $longitude), __('Clocked out'));
    }

    private function attempt(callable $callback, string $message): void
    {
        try {
            $callback();
            Notification::make()->success()->title($message)->send();
        } catch (InvalidArgumentException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();
        }
    }
}
