<?php

namespace App\Filament\Business\Resources\Products\Pages;

use App\Filament\Business\BusinessPanel;
use App\Filament\Business\Resources\Products\ProductResource;
use App\Services\CatalogService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $variant = Arr::pull($data, 'variant', []);

        if (BusinessPanel::typeKey() === 'restaurant') {
            $data['track_stock'] = false;
        }

        return app(CatalogService::class)->createProduct(BusinessPanel::business(), $data, $variant, auth()->id());
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
