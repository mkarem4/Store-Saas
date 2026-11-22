<?php

namespace App\Filament\Business\Resources\TimeEntries\Pages;

use App\Filament\Business\Resources\TimeEntries\TimeEntryResource;
use Filament\Resources\Pages\ListRecords;

class ListTimeEntries extends ListRecords
{
    protected static string $resource = TimeEntryResource::class;
}
