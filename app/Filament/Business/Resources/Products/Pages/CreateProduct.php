<?php

namespace App\Filament\Business\Resources\Products\Pages;

use App\Filament\Business\Resources\Products\ProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Create another'),
        ];
    }
}
