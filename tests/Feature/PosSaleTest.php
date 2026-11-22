<?php

use App\Enums\PaymentMethod;
use App\Enums\SaleChannel;
use App\Enums\StockMovementType;
use App\Models\Business;
use App\Models\Employee;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\SaleService;

function businessWithItem(int $stockQty = 100, bool $trackStock = true): array
{
    $business = Business::factory()->create();
    $product = Product::factory()->forBusiness($business)->create([
        'track_stock' => $trackStock,
        'is_published' => true,
        'is_available' => true,
    ]);

    $variant = ProductVariant::factory()->forProduct($product)->create([
        'stock_qty' => $stockQty,
        'reserved_qty' => 0,
        'retail_price_cents' => 1000,
        'cost_price_cents' => 400,
    ]);

    return [$business, $product, $variant];
}

it('creates a POS cash sale and deducts stock', function (): void {
    [$business, , $variant] = businessWithItem(stockQty: 10);
    $employee = Employee::factory()->create(['business_id' => $business->id]);

    $sale = app(SaleService::class)->createPosSale(
        $business,
        [
            ['variant_id' => $variant->id, 'qty' => 3, 'unit_price_cents' => 1000],
        ],
        [
            ['method' => PaymentMethod::Cash->value, 'amount_cents' => 3000],
        ],
        employeeId: $employee->id,
    );

    expect($sale->status->value)->toBe('completed')
        ->and($sale->total_cents)->toBe(3000)
        ->and($sale->tax_cents)->toBe(0)
        ->and($sale->channel)->toBe(SaleChannel::Pos)
        ->and($sale->employee_id)->toBe($employee->id);

    expect((float) $variant->fresh()->stock_qty)->toBe(7.0);

    $movements = $variant->fresh()->stockMovements()->get();

    expect($movements->count())->toBe(1)
        ->and($movements->first()->type->value)->toBe(StockMovementType::Sale->value);
});

it('requires payments to equal the sale total', function (): void {
    [$business, , $variant] = businessWithItem();

    app(SaleService::class)->createPosSale(
        $business,
        [
            ['variant_id' => $variant->id, 'qty' => 1, 'unit_price_cents' => 1000],
        ],
        [
            ['method' => PaymentMethod::Cash->value, 'amount_cents' => 5],
        ],
    );
})->throws(InvalidArgumentException::class, 'Payments must equal the sale total.');

it('blocks a POS sale that exceeds available stock', function (): void {
    [$business, , $variant] = businessWithItem(stockQty: 2);

    app(SaleService::class)->createPosSale(
        $business,
        [
            ['variant_id' => $variant->id, 'qty' => 5, 'unit_price_cents' => 1000],
        ],
        [
            ['method' => PaymentMethod::Cash->value, 'amount_cents' => 5000],
        ],
    );
})->throws(InvalidArgumentException::class, 'Not enough stock');

it('taxes POS sales only when the platform tax scope is "all"', function (): void {
    PlatformSetting::set('tax_rate', 15);
    [$business, , $variant] = businessWithItem();

    // Default app_only scope: POS is untaxed.
    $sale = app(SaleService::class)->createPosSale(
        $business,
        [['variant_id' => $variant->id, 'qty' => 1, 'unit_price_cents' => 1000]],
        [['method' => PaymentMethod::Cash->value, 'amount_cents' => 1000]],
    );

    expect($sale->tax_cents)->toBe(0);

    PlatformSetting::set('tax_scope', 'all');

    $expectedTax = (int) round(1000 * 15 / 100);

    $sale2 = app(SaleService::class)->createPosSale(
        $business,
        [['variant_id' => $variant->id, 'qty' => 1, 'unit_price_cents' => 1000]],
        [['method' => PaymentMethod::Cash->value, 'amount_cents' => 1000 + $expectedTax]],
    );

    expect($sale2->tax_cents)->toBe($expectedTax)
        ->and($sale2->total_cents)->toBe(1150);
});

it('refunds a POS sale with restock and tracks the refund state', function (): void {
    [$business, , $variant] = businessWithItem(stockQty: 4);

    $sale = app(SaleService::class)->createPosSale(
        $business,
        [
            ['variant_id' => $variant->id, 'qty' => 2, 'unit_price_cents' => 1000],
            ['variant_id' => $variant->id, 'qty' => 1, 'unit_price_cents' => 1000],
        ],
        [
            ['method' => PaymentMethod::Cash->value, 'amount_cents' => 3000],
        ],
    );

    expect($sale->items()->count())->toBe(2);

    $firstItem = $sale->items()->first();

    // Partial refund of the first line only.
    $refund = app(SaleService::class)->refund($sale, [
        ['sale_item_id' => $firstItem->id, 'qty' => 1],
    ]);

    expect($refund->amount_cents)->toBe(1000)
        ->and($sale->fresh()->status->value)->toBe('partially_refunded')
        ->and((float) $variant->fresh()->stock_qty)->toBe(2.0);

    // Refunding more than what was sold is rejected.
    expect(fn () => app(SaleService::class)->refund($sale, [
        ['sale_item_id' => $firstItem->id, 'qty' => 5],
    ]))->toThrow(InvalidArgumentException::class, 'Invalid refund quantity.');

    // Full refund of the remainder flips the sale to refunded.
    app(SaleService::class)->refund($sale, [
        ['sale_item_id' => $firstItem->id, 'qty' => 1],
        ['sale_item_id' => $sale->items()->skip(1)->first()->id, 'qty' => 1],
    ]);

    expect($sale->fresh()->status->value)->toBe('refunded');
});
