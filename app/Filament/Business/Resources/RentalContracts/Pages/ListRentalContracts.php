<?php

namespace App\Filament\Business\Resources\RentalContracts\Pages;

use App\Enums\RentalStatus;
use App\Filament\Business\Resources\RentalContracts\RentalContractResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListRentalContracts extends ListRecords
{
    protected static string $resource = RentalContractResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label(__('New booking'))];
    }

    public function getTabs(): array
    {
        return [
            'open' => Tab::make(__('Open'))->modifyQueryUsing(fn ($query) => $query->whereIn('status', RentalStatus::blocking())),
            'all' => Tab::make(__('All')),
        ];
    }
}
