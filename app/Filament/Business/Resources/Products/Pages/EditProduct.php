<?php

namespace App\Filament\Business\Resources\Products\Pages;

use App\Filament\Business\Resources\Products\ProductResource;
use App\Filament\Support\MoreActions;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [MoreActions::make([DeleteAction::make()])];
    }
}
