<?php

namespace App\Filament\Business\Resources\Inquiries\Pages;

use App\Filament\Business\Resources\Inquiries\InquiryResource;
use Filament\Resources\Pages\ListRecords;

class ListInquiries extends ListRecords
{
    protected static string $resource = InquiryResource::class;
}
