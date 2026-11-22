<?php

namespace App\Filament\Business\Resources\Products\Pages;

use App\Filament\Business\Resources\Products\ProductResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make()
                ->visible(fn (): bool => auth()->user()?->can(\App\Support\Permission::ManageCatalog)),
        ];
    }
}
