<?php

use App\Models\Business;
use App\Models\Employee;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Support\BusinessContext;

it('scopes products to the current business context', function (): void {
    $businessA = Business::factory()->create();
    $businessB = Business::factory()->create();

    Product::factory()->forBusiness($businessA)->create();
    Product::factory()->forBusiness($businessB)->create();

    BusinessContext::run($businessA->id, function () use ($businessA): void {
        expect(Product::query()->count())->toBe(1)
            ->and(Product::query()->first()->business_id)->toBe($businessA->id);
    });

    BusinessContext::run($businessB->id, function () use ($businessB): void {
        expect(Product::query()->count())->toBe(1)
            ->and(Product::query()->first()->business_id)->toBe($businessB->id);
    });
});

it('does not leak sales between businesses', function (): void {
    $businessA = Business::factory()->create();
    $businessB = Business::factory()->create();

    Sale::factory()->create(['business_id' => $businessA->id]);
    Sale::factory()->create(['business_id' => $businessB->id]);

    BusinessContext::run($businessA->id, function () use ($businessA): void {
        expect(Sale::query()->count())->toBe(1)
            ->and(Sale::query()->first()->business_id)->toBe($businessA->id);
    });

    BusinessContext::run($businessB->id, function () use ($businessB): void {
        expect(Sale::query()->count())->toBe(1)
            ->and(Sale::query()->first()->business_id)->toBe($businessB->id);
    });
});

it('auto-fills business_id from the business context on create', function (): void {
    $business = Business::factory()->create();

    BusinessContext::run($business->id, function () use ($business): void {
        $product = Product::factory()->create(['business_id' => null]);
        $variant = ProductVariant::factory()
            ->forProduct($product)
            ->create(['business_id' => null]);

        expect($product->fresh()->business_id)->toBe($business->id)
            ->and($variant->fresh()->business_id)->toBe($business->id);
    });
});

it('scopes employees to the current business context', function (): void {
    $businessA = Business::factory()->create();
    $businessB = Business::factory()->create();

    Employee::factory()->create(['business_id' => $businessA->id]);
    Employee::factory()->create(['business_id' => $businessB->id]);

    BusinessContext::run($businessB->id, function (): void {
        expect(Employee::query()->count())->toBe(1);
    });
});
