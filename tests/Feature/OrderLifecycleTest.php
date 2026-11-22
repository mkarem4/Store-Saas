<?php

use App\Enums\FulfillmentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Services\OrderService;

function businessWithVariant(int $stockQty = 100): array
{
    $business = Business::factory()->create();
    $product = Product::factory()->forBusiness($business)->create([
        'track_stock' => true,
        'is_published' => true,
        'is_available' => true,
    ]);
    $variant = ProductVariant::factory()->forProduct($product)->create([
        'stock_qty' => $stockQty,
        'reserved_qty' => 0,
        'retail_price_cents' => 1000,
    ]);

    return [$business, $product, $variant];
}

it('places an order, reserves stock and applies the 15% app tax', function (): void {
    [$business, , $variant] = businessWithVariant();
    $customer = Customer::factory()->create();

    $order = app(OrderService::class)->place(
        $customer,
        $business,
        [['variant_id' => $variant->id, 'qty' => 2]],
        FulfillmentType::Pickup,
    );

    expect($order->status)->toBe(OrderStatus::Pending)
        ->and($order->payment_status)->toBe(PaymentStatus::Authorized)
        ->and($order->subtotal_cents)->toBe(2000)
        ->and($order->tax_cents)->toBe(300)
        ->and($order->total_cents)->toBe(2300)
        ->and($order->paymentClientSecret)->toStartWith('pi_fake_secret_');

    $variant->refresh();

    // Reservation holds the stock without deducting it yet.
    expect((float) $variant->reserved_qty)->toBe(2.0)
        ->and((float) $variant->stock_qty)->toBe(100.0);
});

it('is idempotent when the same idempotency key is reused', function (): void {
    [$business, , $variant] = businessWithVariant();
    $customer = Customer::factory()->create();

    $first = app(OrderService::class)->place(
        $customer,
        $business,
        [['variant_id' => $variant->id, 'qty' => 1]],
        FulfillmentType::Pickup,
        null,
        null,
        'key-123',
    );

    $second = app(OrderService::class)->place(
        $customer,
        $business,
        [['variant_id' => $variant->id, 'qty' => 1]],
        FulfillmentType::Pickup,
        null,
        null,
        'key-123',
    );

    expect($second->id)->toBe($first->id)
        ->and((float) $variant->fresh()->reserved_qty)->toBe(1.0);
});

it('accepts a pending order, captures and records the sale', function (): void {
    [$business, , $variant] = businessWithVariant();
    $customer = Customer::factory()->create();

    $order = app(OrderService::class)->place(
        $customer,
        $business,
        [['variant_id' => $variant->id, 'qty' => 2]],
        FulfillmentType::Pickup,
    );

    app(OrderService::class)->accept($order);

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Accepted)
        ->and($order->payment_status)->toBe(PaymentStatus::Captured)
        ->and($order->accepted_at)->not->toBeNull();

    $variant->refresh();

    expect((float) $variant->stock_qty)->toBe(98.0)
        ->and((float) $variant->reserved_qty)->toBe(0.0);

    expect(Order::query()->where('id', $order->id)->value('sale_id'))->not->toBeNull();

    $sale = Sale::query()->where('order_id', $order->id)->first();

    expect($sale)->not->toBeNull()
        ->and($sale->total_cents)->toBe($order->total_cents);
});

it('rejects an already accepted order', function (): void {
    [$business, , $variant] = businessWithVariant();
    $customer = Customer::factory()->create();

    $order = app(OrderService::class)->place(
        $customer,
        $business,
        [['variant_id' => $variant->id, 'qty' => 1]],
        FulfillmentType::Pickup,
    );

    app(OrderService::class)->accept($order);

    expect(fn () => app(OrderService::class)->reject($order->refresh()))
        ->toThrow(InvalidArgumentException::class, 'Only pending orders can be rejected.');
});

it('cancels by customer, voids and releases stock', function (): void {
    [$business, , $variant] = businessWithVariant();
    $customer = Customer::factory()->create();

    $order = app(OrderService::class)->place(
        $customer,
        $business,
        [['variant_id' => $variant->id, 'qty' => 3]],
        FulfillmentType::Pickup,
    );

    app(OrderService::class)->cancelByCustomer($order);

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Cancelled)
        ->and($order->payment_status)->toBe(PaymentStatus::Voided)
        ->and((float) $variant->fresh()->reserved_qty)->toBe(0.0)
        ->and((float) $variant->fresh()->stock_qty)->toBe(100.0);
});

it('refuses to cancel an order that is no longer pending', function (): void {
    [$business, , $variant] = businessWithVariant();
    $customer = Customer::factory()->create();

    $order = app(OrderService::class)->place(
        $customer,
        $business,
        [['variant_id' => $variant->id, 'qty' => 1]],
        FulfillmentType::Pickup,
    );

    app(OrderService::class)->accept($order);

    expect(fn () => app(OrderService::class)->cancelByCustomer($order->refresh()))
        ->toThrow(InvalidArgumentException::class, 'can no longer be cancelled');
});

it('expires a pending order after the acceptance window', function (): void {
    [$business, , $variant] = businessWithVariant();
    $customer = Customer::factory()->create();

    $order = app(OrderService::class)->place(
        $customer,
        $business,
        [['variant_id' => $variant->id, 'qty' => 1]],
        FulfillmentType::Pickup,
    );

    $order->update(['expires_at' => now()->subMinute()]);

    expect($order->fresh()->isExpired())->toBeTrue();

    app(OrderService::class)->expire($order->refresh());

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Expired)
        ->and($order->payment_status)->toBe(PaymentStatus::Voided)
        ->and((float) $variant->fresh()->reserved_qty)->toBe(0.0);

    // Expired orders cannot be accepted.
    expect(fn () => app(OrderService::class)->accept($order->refresh()))
        ->toThrow(InvalidArgumentException::class, 'Only pending orders can be accepted.');
});

it('refunds the captured amount when payment succeeds after the window closed', function (): void {
    [$business, , $variant] = businessWithVariant();
    $customer = Customer::factory()->create();

    $order = app(OrderService::class)->place(
        $customer,
        $business,
        [['variant_id' => $variant->id, 'qty' => 1]],
        FulfillmentType::Pickup,
    );

    $order->update(['expires_at' => now()->subMinute()]);
    $order = $order->refresh();

    app(OrderService::class)->expireWithRefund($order);

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Expired)
        ->and($order->payment_status)->toBe(PaymentStatus::Refunded)
        ->and((float) $variant->fresh()->reserved_qty)->toBe(0.0)
        ->and((float) $variant->fresh()->stock_qty)->toBe(100.0);
});

it('does not place orders that exceed available stock', function (): void {
    [$business, , $variant] = businessWithVariant(stockQty: 1);

    expect(fn () => app(OrderService::class)->place(
        Customer::factory()->create(),
        $business,
        [['variant_id' => $variant->id, 'qty' => 5]],
        FulfillmentType::Pickup,
    ))->toThrow(InvalidArgumentException::class, 'Not enough stock');
});
