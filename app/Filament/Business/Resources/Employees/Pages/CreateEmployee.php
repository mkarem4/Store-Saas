<?php

namespace App\Filament\Business\Resources\Employees\Pages;

use App\Filament\Business\BusinessPanel;
use App\Filament\Business\Resources\Employees\EmployeeResource;
use App\Services\EmployeeService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $raw = $this->form->getRawState();
        $email = filled($raw['login_email'] ?? null) ? (string) $raw['login_email'] : null;

        return app(EmployeeService::class)->createWithLogin(BusinessPanel::business(), $data, $email, $email ? (string) $raw['login_password'] : null);
    }
}
