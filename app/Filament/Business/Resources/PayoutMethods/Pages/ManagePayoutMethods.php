<?php

namespace App\Filament\Business\Resources\PayoutMethods\Pages;

use App\Filament\Business\Resources\PayoutMethods\PayoutMethodResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePayoutMethods extends ManageRecords
{
    protected static string $resource = PayoutMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
