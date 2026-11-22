<?php

use App\Enums\PaymentMethod;
use App\Enums\RentalStatus;
use App\Enums\SaleChannel;
use App\Enums\VehicleStatus;
use App\Models\Business;
use App\Models\BusinessType;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Sale;
use App\Models\Vehicle;
use App\Services\PayrollService;
use App\Services\RentalService;
use App\Services\ReportService;
use App\Services\VehicleService;

function showroomVehicle(string $type = 'car_showroom', string $purpose = 'sale'): array
{
    $businessType = BusinessType::query()->firstWhere('key', $type) ?? BusinessType::factory()->create(['key' => $type]);
    $business = Business::factory()->create(['business_type_id' => $businessType->id]);
    $vehicle = Vehicle::query()->create([
        'business_id' => $business->id,
        'vin' => '1HGCM82633A'.random_int(100000, 999999),
        'make' => 'Toyota',
        'model' => 'Camry',
        'year' => 2021,
        'purchase_price_cents' => 1500000,
        'listing_price_cents' => 2000000,
        'status' => VehicleStatus::Available,
        'purpose' => $purpose,
    ]);
    $client = Client::query()->create(['business_id' => $business->id, 'name' => 'Sam Buyer', 'phone' => '555-0100', 'id_number' => 'D1234']);

    return [$business, $vehicle, $client];
}

it('sells a car: total cost, profit and the seller commission (scenario 6)', function (): void {
    [$business, $vehicle, $client] = showroomVehicle();
    $seller = Employee::factory()->withCommission(100)->create(['business_id' => $business->id]);
    $service = app(VehicleService::class);

    $service->addCost($vehicle, 80000, 'Brakes');
    $service->addCost($vehicle, 20000, 'Detailing');

    expect($vehicle->totalCostCents())->toBe(1600000);

    $sale = $service->sell($vehicle, 1900000, $client->id, [['method' => 'card', 'amount_cents' => 1900000]], $seller->id);

    $vehicle->refresh();
    expect($vehicle->status)->toBe(VehicleStatus::Sold)
        ->and($vehicle->profitCents())->toBe(300000)
        ->and($sale->channel)->toBe(SaleChannel::Vehicle)
        ->and($sale->cogs_cents)->toBe(1600000)
        ->and($sale->tax_cents)->toBe(0);

    expect(app(PayrollService::class)->commission($seller, now(), now()))->toBe(19000);

    $profits = app(ReportService::class)->vehicleProfits($business, now(), now());
    expect($profits->first()['profit_cents'])->toBe(300000);

    // Rule 3: sold only once.
    expect(fn () => $service->sell($vehicle, 1, $client->id, [['method' => 'cash', 'amount_cents' => 1]]))
        ->toThrow(InvalidArgumentException::class, 'cannot be sold');
});

it('rents a car paid in full at pickup with the deposit kept out of revenue (scenario 13)', function (): void {
    [$business, $vehicle, $client] = showroomVehicle('car_rental', 'rental');
    $service = app(RentalService::class);

    $contract = $service->book($vehicle, $client->id, now(), now()->addDays(3), 'daily', 5000, depositCents: 30000);

    expect($contract->total_cents)->toBe(15000)
        ->and($contract->status)->toBe(RentalStatus::Reserved);

    // Rule 4: no overlapping contract for the same car.
    expect(fn () => $service->book($vehicle, $client->id, now()->addDay(), now()->addDays(5), 'daily', 5000))
        ->toThrow(InvalidArgumentException::class, 'already has a contract');

    $service->pickUp($contract, 42000, PaymentMethod::Card);

    expect($vehicle->refresh()->status)->toBe(VehicleStatus::Rented);

    $summary = app(ReportService::class)->summary($business, now(), now());
    expect($summary['revenue_cents'])->toBe(15000);

    $returned = $service->returnVehicle($contract->refresh(), 42850, extraChargesCents: 7500, extraChargesNote: 'Late return');

    expect($returned->status)->toBe(RentalStatus::Completed)
        ->and($returned->deposit_returned_cents)->toBe(22500)
        ->and($vehicle->refresh()->status)->toBe(VehicleStatus::Available)
        ->and($vehicle->mileage)->toBe(42850);

    // Revenue = rent + extra charges; the deposit never counts.
    expect(app(ReportService::class)->summary($business, now(), now())['revenue_cents'])->toBe(22500)
        ->and(Sale::query()->where('business_id', $business->id)->where('channel', SaleChannel::Rental)->count())->toBe(2);
});

it('rejects a return odometer lower than at pickup', function (): void {
    [, $vehicle, $client] = showroomVehicle('car_rental', 'rental');
    $service = app(RentalService::class);
    $contract = $service->book($vehicle, $client->id, now(), now()->addDay(), 'daily', 5000);
    $service->pickUp($contract, 1000, PaymentMethod::Cash);

    expect(fn () => $service->returnVehicle($contract->refresh(), 900))
        ->toThrow(InvalidArgumentException::class, 'odometer');
});
