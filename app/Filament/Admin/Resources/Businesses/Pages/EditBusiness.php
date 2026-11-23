<?php

namespace App\Filament\Admin\Resources\Businesses\Pages;

use App\Filament\Admin\Resources\Businesses\BusinessResource;
use App\Filament\Support\MoreActions;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditBusiness extends EditRecord
{
    protected static string $resource = BusinessResource::class;

    protected function getHeaderActions(): array
    {
        return [MoreActions::make([RestoreAction::make(), DeleteAction::make(), ForceDeleteAction::make()])];
    }
}
