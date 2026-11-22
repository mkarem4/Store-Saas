<?php

use App\Enums\GoldPriceSource;
use App\Enums\JewelryPricingMode;
use App\Enums\MakingType;
use App\Models\Business;
use App\Models\GoldPrice;
use App\Models\JewelryAttribute;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Gold\GoldPriceService;
use Illuminate\Support\Facades\Http;

it('fetches 24K spot and derives each karat by purity', function (): void {
    config(['services.gold_api.key' => 'test']);
    Http::fake(['www.goldapi.io/*' => Http::response(['price_gram_24k' => 96.00])]);

    $business = Business::factory()->create();

    $written = app(GoldPriceService::class)->refresh($business);

    expect($written)->toBe(['18' => 7200, '21' => 8400, '22' => 8800, '24' => 9600])
        ->and(GoldPrice::query()->where('business_id', $business->id)->where('source', GoldPriceSource::Auto)->count())->toBe(4);
});

it('never overwrites a manual price for the same day', function (): void {
    config(['services.gold_api.key' => 'test']);
    Http::fake(['www.goldapi.io/*' => Http::response(['price_gram_24k' => 96.00])]);

    $business = Business::factory()->create();
    app(GoldPriceService::class)->setManual($business, '21', 9000);

    $written = app(GoldPriceService::class)->refresh($business);

    expect($written)->not->toHaveKey('21')
        ->and(GoldPrice::priceFor($business->id, '21'))->toBe(9000);
});

it('keeps the last price when the provider is down', function (): void {
    config(['services.gold_api.key' => 'test']);
    Http::fake(['www.goldapi.io/*' => Http::response([], 500)]);

    $business = Business::factory()->create();
    GoldPrice::query()->create(['business_id' => $business->id, 'karat' => '18', 'price_per_gram_cents' => 7000, 'effective_date' => now()->subDay()->toDateString()]);

    expect(app(GoldPriceService::class)->refresh($business))->toBe([])
        ->and(GoldPrice::priceFor($business->id, '18'))->toBe(7000);
});

it('prices a by-weight piece as weight × gram price + making (scenario 7)', function (): void {
    $business = Business::factory()->create();
    app(GoldPriceService::class)->setManual($business, '21', 8000);

    $product = Product::factory()->forBusiness($business)->create();
    $variant = ProductVariant::factory()->forProduct($product)->create(['retail_price_cents' => 1]);
    JewelryAttribute::query()->create([
        'business_id' => $business->id,
        'product_variant_id' => $variant->id,
        'pricing_mode' => JewelryPricingMode::ByWeight,
        'weight_g' => 5.5,
        'karat' => '21',
        'making_type' => MakingType::Fixed,
        'making_value' => 2500,
    ]);

    expect($variant->fresh()->priceFor())->toBe(46500);
});
