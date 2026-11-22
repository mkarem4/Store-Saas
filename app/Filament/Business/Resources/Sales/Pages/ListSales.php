<?php

namespace App\Filament\Business\Resources\Sales\Pages;

use App\Filament\Business\Resources\Sales\SaleResource;
use Filament\Resources\Pages\ListRecords;

class ListSales extends ListRecords
{
    protected static string $resource = SaleResource::class;
}
