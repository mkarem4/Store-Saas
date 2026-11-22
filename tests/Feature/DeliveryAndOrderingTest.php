<?php

use App\Enums\FulfillmentType;
use App\Models\Business;
use App\Models\Customer;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\OrderService;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    PlatformSetting::set('default_tax_rate', 15);
});

function deliveryStore(array $settings = []): array
{
    $business = Business::factory()->withSettings(array_merge([
        'delivery_enabled' => true,
        'delivery_fee_per_km_cents' => 150,
        'delivery_base_fee_cents' => 0,
        'delivery_max_km' => 10,
    ], $settings))->create(['latitude' => 40.7128, 'longitude' => -74.0060]);

    $product = Product::factory()->forBusiness($business)->create(['track_stock' => false, 'is_published' => true, 'is_available' => true]);
    $variant = ProductVariant::factory()->forProduct($product)->create(['retail_price_cents' => 2000]);

    return [$business, $variant];
}

it('charges delivery by distance and never taxes the delivery fee (scenario 16)', function (): void {
    [$business, $variant] = deliveryStore();

    // ~4.4 km straight-line from the store (Haversine without a Maps key).
    $quote = app(OrderService::class)->quote($business, [['variant_id' => $variant->id, 'qty' => 1]], FulfillmentType::Delivery, [
        'line1' => '1 Test St', 'city' => 'NY', 'phone' => '555', 'latitude' => 40.7528, 'longitude' => -74.0060,
    ]);

    expect($quote['delivery_distance_km'])->toBeGreaterThan(4.3)->toBeLessThan(4.5)
        ->and($quote['delivery_fee_cents'])->toBe((int) round($quote['delivery_distance_km'] * 150))
        ->and($quote['tax_cents'])->toBe(300)
        ->and($quote['total_cents'])->toBe(2000 + 300 + $quote['delivery_fee_cents']);
});

it('refuses delivery beyond the store maximum distance', function (): void {
    [$business, $variant] = deliveryStore(['delivery_max_km' => 3]);

    app(OrderService::class)->quote($business, [['variant_id' => $variant->id, 'qty' => 1]], FulfillmentType::Delivery, [
        'line1' => '1 Test St', 'city' => 'NY', 'phone' => '555', 'latitude' => 40.7528, 'longitude' => -74.0060,
    ]);
})->throws(InvalidArgumentException::class, 'outside the delivery area');

it('uses the Google distance when a Maps key is configured', function (): void {
    config(['services.google_maps.key' => 'test-key']);
    Http::fake(['maps.googleapis.com/*' => Http::response([
        'status' => 'OK',
        'rows' => [['elements' => [['status' => 'OK', 'distance' => ['value' => 6200]]]]],
    ])]);

    [$business, $variant] = deliveryStore();

    $quote = app(OrderService::class)->quote($business, [['variant_id' => $variant->id, 'qty' => 1]], FulfillmentType::Delivery, [
        'line1' => '1 Test St', 'city' => 'NY', 'phone' => '555', 'latitude' => 40.7528, 'longitude' => -74.0060,
    ]);

    expect($quote['delivery_distance_km'])->toBe(6.2)
        ->and($quote['delivery_fee_cents'])->toBe(930);
});

it('quotes delivery from a saved address through the API', function (): void {
    [$business, $variant] = deliveryStore();
    $customer = Customer::factory()->create();
    $address = $customer->addresses()->create([
        'address_line1' => '5 Park Ave', 'city' => 'NY', 'phone' => '555', 'latitude' => 40.7228, 'longitude' => -74.0060,
    ]);

    Sanctum::actingAs($customer, [], 'customer');

    $this->postJson('/api/v1/orders/quote', [
        'business' => $business->slug,
        'fulfillment' => 'delivery',
        'address_id' => $address->id,
        'items' => [['variant_id' => $variant->id, 'qty' => 1]],
    ])->assertOk()
        ->assertJsonPath('data.tax_cents', 300)
        ->assertJsonPath('data.delivery_fee_cents', fn (int $fee) => $fee > 150 && $fee < 180);
});

it('does not take orders while the store is closed', function (): void {
    $business = Business::factory()->closed()->create();
    $product = Product::factory()->forBusiness($business)->create(['track_stock' => false, 'is_published' => true, 'is_available' => true]);
    $variant = ProductVariant::factory()->forProduct($product)->create(['retail_price_cents' => 1000]);

    app(OrderService::class)->quote($business, [['variant_id' => $variant->id, 'qty' => 1]], FulfillmentType::Pickup);
})->throws(InvalidArgumentException::class, 'closed');
