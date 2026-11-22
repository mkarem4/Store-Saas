<?php

namespace App\Filament\Business\Resources\Employees\Pages;

use App\Filament\Business\Resources\Employees\EmployeeResource;
use Filament\Resources\Pages\EditRecord;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function afterSave(): void
    {
        $this->getRecord()->user?->update(['name' => $this->getRecord()->name, 'is_active' => $this->getRecord()->is_active]);

        if ($this->getRecord()->wasChanged(['commission_rate_bps', 'monthly_salary_cents', 'hourly_rate_cents'])) {
            activity('team')->performedOn($this->getRecord())->causedBy(auth()->user())
                ->withProperties(['attributes' => $this->getRecord()->only(['commission_rate_bps', 'monthly_salary_cents', 'hourly_rate_cents'])])
                ->log('employee.pay_changed');
        }
    }
}
