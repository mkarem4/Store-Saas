<?php

use App\Enums\PaymentMethod;
use App\Enums\ProductUnit;
use App\Enums\SaleChannel;
use App\Enums\StockMovementType;
use App\Models\Business;
use App\Models\Employee;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TaxRate;
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

beforeEach(function (): void {
    PlatformSetting::set('default_tax_rate', 15);
    PlatformSetting::set('tax_scope', 'all');
});

it('creates a POS cash sale with regional tax on top and deducts stock', function (): void {
    [$business, , $variant] = businessWithItem(stockQty: 10);
    $employee = Employee::factory()->create(['business_id' => $business->id]);

    $sale = app(SaleService::class)->createPosSale(
        $business,
        [
            ['variant_id' => $variant->id, 'qty' => 3],
        ],
        [
            ['method' => PaymentMethod::Cash->value, 'amount_cents' => 3450],
        ],
        employeeId: $employee->id,
    );

    expect($sale->status->value)->toBe('completed')
        ->and($sale->subtotal_cents)->toBe(3000)
        ->and($sale->tax_cents)->toBe(450)
        ->and($sale->total_cents)->toBe(3450)
        ->and($sale->channel)->toBe(SaleChannel::Pos)
        ->and($sale->employee_id)->toBe($employee->id)
        ->and($sale->items()->sum('tax_cents'))->toBe(450);

    expect((float) $variant->fresh()->stock_qty)->toBe(7.0);

    $movements = $variant->fresh()->stockMovements()->get();

    expect($movements->count())->toBe(1)
        ->and($movements->first()->type->value)->toBe(StockMovementType::Sale->value);
});

it('uses the state tax rate of the store region', function (): void {
    TaxRate::query()->create(['country' => 'US', 'state' => 'TX', 'rate' => 8.25]);
    TaxRate::query()->create(['country' => 'US', 'state' => null, 'rate' => 5]);

    [$business, , $variant] = businessWithItem();
    $business->update(['state' => 'tx']);

    $sale = app(SaleService::class)->createPosSale(
        $business->refresh(),
        [['variant_id' => $variant->id, 'qty' => 1]],
        [['method' => PaymentMethod::Card->value, 'amount_cents' => 1083]],
    );

    expect($sale->tax_cents)->toBe(83)
        ->and((float) $sale->tax_rate)->toBe(8.25);

    // A state without its own rate falls back to the country-wide rate.
    $business->update(['state' => 'NY']);

    expect($business->refresh()->taxRate())->toBe(5.0);
});

it('requires payments to equal the sale total', function (): void {
    [$business, , $variant] = businessWithItem();

    app(SaleService::class)->createPosSale(
        $business,
        [
            ['variant_id' => $variant->id, 'qty' => 1],
        ],
        [
            ['method' => PaymentMethod::Cash->value, 'amount_cents' => 1000],
        ],
    );
})->throws(InvalidArgumentException::class, 'Payments must equal the sale total.');

it('blocks a POS sale that exceeds available stock', function (): void {
    [$business, , $variant] = businessWithItem(stockQty: 2);

    app(SaleService::class)->createPosSale(
        $business,
        [
            ['variant_id' => $variant->id, 'qty' => 5],
        ],
        [
            ['method' => PaymentMethod::Cash->value, 'amount_cents' => 5750],
        ],
    );
})->throws(InvalidArgumentException::class, 'Not enough stock');

it('skips POS tax when the platform limits tax to app orders', function (): void {
    PlatformSetting::set('tax_scope', 'app_only');
    [$business, , $variant] = businessWithItem();

    $sale = app(SaleService::class)->createPosSale(
        $business,
        [['variant_id' => $variant->id, 'qty' => 1]],
        [['method' => PaymentMethod::Cash->value, 'amount_cents' => 1000]],
    );

    expect($sale->tax_cents)->toBe(0);
});

it('stops staff from changing prices or selling wholesale without permission', function (): void {
    [$business, , $variant] = businessWithItem();
    $variant->update(['wholesale_price_cents' => 800]);

    expect(fn () => app(SaleService::class)->createPosSale(
        $business,
        [['variant_id' => $variant->id, 'qty' => 1, 'unit_price_cents' => 500]],
        [['method' => PaymentMethod::Cash->value, 'amount_cents' => 575]],
    ))->toThrow(InvalidArgumentException::class, 'not allowed to change prices');

    expect(fn () => app(SaleService::class)->createPosSale(
        $business,
        [['variant_id' => $variant->id, 'qty' => 1, 'wholesale' => true]],
        [['method' => PaymentMethod::Cash->value, 'amount_cents' => 920]],
    ))->toThrow(InvalidArgumentException::class, 'wholesale');

    $sale = app(SaleService::class)->createPosSale(
        $business,
        [['variant_id' => $variant->id, 'qty' => 1, 'wholesale' => true]],
        [['method' => PaymentMethod::Cash->value, 'amount_cents' => 920]],
        canSellWholesale: true,
    );

    expect($sale->subtotal_cents)->toBe(800);
});

it('sells by weight with three decimals and rejects fractions for pieces', function (): void {
    [$business, , $variant] = businessWithItem(stockQty: 10);
    $variant->update(['unit' => ProductUnit::Kilogram, 'retail_price_cents' => 400]);

    $sale = app(SaleService::class)->createPosSale(
        $business,
        [['variant_id' => $variant->id, 'qty' => 1.25]],
        [['method' => PaymentMethod::Cash->value, 'amount_cents' => 575]],
    );

    expect($sale->subtotal_cents)->toBe(500)
        ->and((float) $variant->fresh()->stock_qty)->toBe(8.75);

    [, , $piece] = businessWithItem();

    expect(fn () => app(SaleService::class)->createPosSale(
        $piece->business,
        [['variant_id' => $piece->id, 'qty' => 1.5]],
        [['method' => PaymentMethod::Cash->value, 'amount_cents' => 1725]],
    ))->toThrow(InvalidArgumentException::class, 'whole units');
});

it('refunds a POS sale with its tax share, restocks and tracks the refund state', function (): void {
    [$business, , $variant] = businessWithItem(stockQty: 4);

    $sale = app(SaleService::class)->createPosSale(
        $business,
        [
            ['variant_id' => $variant->id, 'qty' => 2],
            ['variant_id' => $variant->id, 'qty' => 1],
        ],
        [
            ['method' => PaymentMethod::Cash->value, 'amount_cents' => 3450],
        ],
    );

    expect($sale->items()->count())->toBe(2);

    $firstItem = $sale->items()->first();

    // Partial refund of the first line only.
    $refund = app(SaleService::class)->refund($sale, [
        ['sale_item_id' => $firstItem->id, 'qty' => 1],
    ]);

    expect($refund->amount_cents)->toBe(1150)
        ->and($refund->tax_cents)->toBe(150)
        ->and($refund->cogs_cents)->toBe(400)
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
