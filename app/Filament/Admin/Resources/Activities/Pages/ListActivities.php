<?php

namespace App\Filament\Admin\Resources\Activities\Pages;

use App\Filament\Admin\Resources\Activities\ActivityResource;
use Filament\Resources\Pages\ListRecords;

class ListActivities extends ListRecords
{
    protected static string $resource = ActivityResource::class;

    /**
     * @return array<int, string>
     */
    public function tabEmptyState(): ?array
    {
        return [__('Nothing recorded yet'), __('Store sign-ins, price changes, returns and settings changes appear here.')];
    }
}
