<?php

namespace App\Filament\Business\Resources\PayrollRuns\Pages;

use App\Filament\Business\Resources\PayrollRuns\PayrollRunResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPayrollRun extends ViewRecord
{
    protected static string $resource = PayrollRunResource::class;

    protected function getHeaderActions(): array
    {
        return [PayrollRunResource::approveAction()->after(fn () => $this->refreshFormData(['status']))];
    }
}
