<?php

namespace App\Filament\Admin\Resources\Businesses\Pages;

use App\Filament\Admin\Resources\Businesses\BusinessResource;
use App\Models\BusinessType;
use App\Models\User;
use App\Services\BusinessProvisioner;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Creating a store provisions it in one step (S1/S2, M1.2): the store, its
 * owner account with a temporary password, roles and expense categories.
 */
class CreateBusiness extends CreateRecord
{
    protected static string $resource = BusinessResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $raw = $this->form->getRawState();

        $business = app(BusinessProvisioner::class)->provision(
            BusinessType::query()->findOrFail($data['business_type_id']),
            $data,
            (string) $raw['owner_name'],
            (string) $raw['owner_email'],
            (string) $raw['owner_password'],
        );

        $business->fill([
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
        ])->save();

        User::query()->where('email', $raw['owner_email'])->update(['must_change_password' => true]);

        activity('stores')->performedOn($business)->causedBy(auth()->user())->log('business.created');

        return $business;
    }
}
