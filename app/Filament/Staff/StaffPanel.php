<?php

namespace App\Filament\Staff;

use App\Models\Employee;

final class StaffPanel
{
    public static function employee(): ?Employee
    {
        $userId = auth()->id();

        return $userId === null ? null : Employee::query()->where('user_id', $userId)->first();
    }
}
