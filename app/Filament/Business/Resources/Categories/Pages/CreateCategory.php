<?php

namespace App\Filament\Business\Resources\Categories\Pages;

use App\Filament\Business\Resources\Categories\CategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\CreateRecord;

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Create another'),
        ];
    }
}
