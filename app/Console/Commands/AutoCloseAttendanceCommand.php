<?php

namespace App\Console\Commands;

use App\Services\AttendanceService;
use Illuminate\Console\Command;

class AutoCloseAttendanceCommand extends Command
{
    protected $signature = 'attendance:auto-close';

    protected $description = 'Close time entries left open past the end of the store day and flag them for review';

    public function handle(AttendanceService $attendance): int
    {
        $closed = $attendance->autoCloseStaleEntries();

        $this->info("Auto-closed {$closed} time entr(y/ies).");

        return self::SUCCESS;
    }
}
