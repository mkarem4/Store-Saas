<?php

namespace App\Filament\Admin\Resources\BusinessTypes\Pages;

use App\Filament\Admin\Resources\BusinessTypes\BusinessTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;

class ManageBusinessTypes extends ManageRecords
{
    protected static string $resource = BusinessTypeResource::class;

    protected function getHeaderActions(): array
    {
        // The fields list needs room, so the dialog is wider than a short form.
        return [CreateAction::make()->modalWidth(Width::FiveExtraLarge)];
    }
}
