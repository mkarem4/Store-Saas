<?php

namespace App\Filament\Business\Resources\Vehicles\Pages;

use App\Filament\Business\Resources\Vehicles\VehicleResource;
use App\Models\Vehicle;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVehicle extends EditRecord
{
    protected static string $resource = VehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            VehicleResource::sellAction()->after(fn () => $this->refreshFormData(['status'])),
            DeleteAction::make()->visible(fn (Vehicle $record): bool => $record->sold_at === null),
        ];
    }
}
