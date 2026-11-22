<?php

namespace App\Filament\Business\Resources\Orders\Pages;

use App\Filament\Business\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return array_map(
            fn ($action) => $action->after(fn () => $this->refreshFormData([])),
            [...OrderResource::workflowActions(), OrderResource::cancelAction()],
        );
    }
}
