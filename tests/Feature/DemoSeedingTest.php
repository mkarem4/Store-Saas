<?php

use App\Enums\BusinessTypeKey;
use App\Enums\RentalRateType;
use App\Enums\RentalStatus;
use App\Enums\VehiclePurpose;
use App\Models\Business;
use App\Models\BusinessType;
use Database\Seeders\DatabaseSeeder;

it('seeds the built-in modules while preserving custom business type keys', function (): void {
    BusinessType::factory()->create(['key' => 'bookstore']);

    $this->seed(DatabaseSeeder::class);

    expect(BusinessType::query()->pluck('key')->all())
        ->toContain(...array_column(BusinessTypeKey::cases(), 'value'))
        ->toContain('bookstore');

    $showroom = Business::query()->where('slug', BusinessTypeKey::CarShowroom->value)->firstOrFail();
    $rental = Business::query()->where('slug', BusinessTypeKey::CarRental->value)->firstOrFail();
    $jewelry = Business::query()->where('slug', BusinessTypeKey::Jewelry->value)->firstOrFail();

    $this->assertDatabaseHas('vehicles', ['business_id' => $showroom->id, 'purpose' => VehiclePurpose::Sale->value]);
    $this->assertDatabaseHas('vehicles', ['business_id' => $rental->id, 'purpose' => VehiclePurpose::Rental->value]);
    $this->assertDatabaseHas('rental_contracts', [
        'business_id' => $rental->id,
        'rate_type' => RentalRateType::Daily->value,
        'status' => RentalStatus::Active->value,
    ]);
    $this->assertDatabaseHas('gold_prices', ['business_id' => $jewelry->id, 'karat' => '24']);
    $this->assertDatabaseHas('businesses', ['slug' => 'bookstore']);
    $this->assertDatabaseHas('sales', ['business_id' => Business::query()->where('slug', BusinessTypeKey::Grocery->value)->firstOrFail()->id]);
});
