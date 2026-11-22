<?php

namespace App\Filament\Business\Resources\PurchaseReceipts\Pages;

use App\Filament\Business\BusinessPanel;
use App\Filament\Business\Resources\PurchaseReceipts\PurchaseReceiptResource;
use App\Services\PurchaseService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CreatePurchaseReceipt extends CreateRecord
{
    protected static string $resource = PurchaseReceiptResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(PurchaseService::class)->receive(
                BusinessPanel::business(),
                array_values($data['lines']),
                $data['supplier_id'] ?? null,
                $data['reference'] ?? null,
                Carbon::parse($data['received_at']),
                $data['notes'] ?? null,
                auth()->id(),
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['data.lines' => $exception->getMessage()]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
