<?php

namespace App\Filament\Business\Resources\Sales\Pages;

use App\Filament\Business\Resources\Sales\SaleResource;
use App\Models\Sale;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewSale extends ViewRecord
{
    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('receipt')->label(__('Print receipt'))->icon(Heroicon::OutlinedPrinter)->color('gray')
                ->url(fn (Sale $record): string => route('receipts.show', ['sale' => $record->id, 'print' => 1]), shouldOpenInNewTab: true),
            SaleResource::refundAction()->after(fn () => $this->refreshFormData([])),
        ];
    }
}
