<?php

namespace App\Filament\Business\Resources\RentalContracts\Pages;

use App\Filament\Business\Resources\RentalContracts\RentalContractResource;
use App\Models\Vehicle;
use App\Services\RentalService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CreateRentalContract extends CreateRecord
{
    protected static string $resource = RentalContractResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(RentalService::class)->book(
                Vehicle::query()->findOrFail($data['vehicle_id']),
                (int) $data['client_id'],
                Carbon::parse($data['starts_at']),
                Carbon::parse($data['ends_at']),
                $data['rate_type'],
                (int) $data['rate_cents'],
                (int) ($data['deposit_cents'] ?? 0),
                $data['notes'] ?? null,
                auth()->id(),
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['data.vehicle_id' => $exception->getMessage()]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
