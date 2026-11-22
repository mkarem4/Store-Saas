<?php

use App\Enums\ExpenseSource;
use App\Enums\FulfillmentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;
use App\Enums\UserRole;
use App\Enums\WalletTransactionStatus;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\User;
use App\Notifications\PayoutStatusChanged;
use App\Services\OrderService;
use App\Services\SaleService;
use App\Services\WalletService;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    PlatformSetting::set('default_tax_rate', 15);
    PlatformSetting::set('commission_rate', 3);
    PlatformSetting::set('wallet_hold_days', 7);
    PlatformSetting::set('minimum_payout_cents', 0);
});

/**
 * @return array{0: Business, 1: ProductVariant, 2: Order}
 */
function completedOrder(int $priceCents = 10000, int $qty = 1): array
{
    $business = Business::factory()->create();
    $product = Product::factory()->forBusiness($business)->create([
        'track_stock' => true, 'is_published' => true, 'is_available' => true,
    ]);
    $variant = ProductVariant::factory()->forProduct($product)->create([
        'stock_qty' => 10, 'reserved_qty' => 0, 'retail_price_cents' => $priceCents, 'cost_price_cents' => 4000,
    ]);

    $service = app(OrderService::class);
    $order = $service->place(Customer::factory()->create(), $business, [['variant_id' => $variant->id, 'qty' => $qty]], FulfillmentType::Pickup);
    $service->accept($order);
    $service->markPreparing($order->refresh());
    $service->markReady($order->refresh());
    $service->complete($order->refresh());

    return [$business, $variant, $order->refresh()];
}

it('settles a completed order: stock, sale, 3% commission, Stripe fee and pending wallet credit', function (): void {
    [$business, $variant, $order] = completedOrder();

    // 100.00 + 15% tax = 115.00 paid; fake gateway fee = 2.9% + 30¢.
    $stripeFee = (int) round(11500 * 0.029) + 30;

    expect($order->status)->toBe(OrderStatus::Completed)
        ->and($order->tax_cents)->toBe(1500)
        ->and($order->platform_fee_cents)->toBe(300)
        ->and($order->gateway_fee_cents)->toBe($stripeFee)
        ->and($order->store_payout_cents)->toBe(11500 - 300 - $stripeFee);

    expect((float) $variant->fresh()->stock_qty)->toBe(9.0)
        ->and((float) $variant->fresh()->reserved_qty)->toBe(0.0);

    $sale = Sale::query()->where('order_id', $order->id)->firstOrFail();
    expect($sale->total_cents)->toBe(11500)
        ->and($sale->tax_cents)->toBe(1500)
        ->and($sale->payments()->first()->fee_cents)->toBe($stripeFee);

    $wallet = app(WalletService::class)->walletFor($business)->refresh();
    expect($wallet->pending_cents)->toBe($order->store_payout_cents)
        ->and($wallet->available_cents)->toBe(0);

    // Fees are booked as store expenses so they reduce net profit.
    expect(Expense::query()->where('business_id', $business->id)->where('source', ExpenseSource::PlatformFee)->sum('amount_cents'))->toBe(300)
        ->and(Expense::query()->where('business_id', $business->id)->where('source', ExpenseSource::GatewayFee)->sum('amount_cents'))->toBe($stripeFee);
});

it('makes earnings available after the hold period', function (): void {
    [$business, , $order] = completedOrder();

    expect(app(WalletService::class)->releaseMatured())->toBe(0);

    $this->travel(7)->days();
    $this->travel(1)->minutes();

    expect(app(WalletService::class)->releaseMatured())->toBe(3);

    $wallet = app(WalletService::class)->walletFor($business)->refresh();
    expect($wallet->pending_cents)->toBe(0)
        ->and($wallet->available_cents)->toBe($order->store_payout_cents)
        ->and($business->walletTransactions()->where('status', WalletTransactionStatus::Pending)->count())->toBe(0);
});

it('runs a payout request through approval and transfer (scenario 18)', function (): void {
    Notification::fake();
    PlatformSetting::set('wallet_hold_days', 0);

    [$business, , $order] = completedOrder();
    $owner = User::factory()->create(['business_id' => $business->id, 'role' => UserRole::BusinessOwner]);
    $admin = User::factory()->create(['business_id' => null, 'role' => UserRole::Owner]);
    $wallet = app(WalletService::class);

    $method = $business->payoutMethods()->create([
        'type' => 'bank_transfer',
        'label' => 'Chase',
        'details' => ['account_name' => 'Store LLC', 'account_number' => '000123456789', 'routing_number' => '021000021'],
    ]);

    expect(fn () => $wallet->requestPayout($business, $order->store_payout_cents + 1, $method))
        ->toThrow(InvalidArgumentException::class, 'You can withdraw up to');

    $payout = $wallet->requestPayout($business, 5000, $method, 'Weekly', $owner->id);

    expect($payout->status)->toBe(PayoutStatus::Requested)
        ->and($payout->method_snapshot['summary'])->toBe('Chase ••••6789')
        ->and($wallet->walletFor($business)->refresh()->available_cents)->toBe($order->store_payout_cents - 5000);

    $wallet->approvePayout($payout, $admin);
    $paid = $wallet->markPayoutPaid($payout, $admin, 'WIRE-7781');

    expect($paid->status)->toBe(PayoutStatus::Paid)
        ->and($paid->reference)->toBe('WIRE-7781')
        ->and($wallet->walletFor($business)->refresh()->lifetime_payouts_cents)->toBe(5000);

    Notification::assertSentTo($owner, PayoutStatusChanged::class, fn ($n) => $n->payout->status === PayoutStatus::Paid);

    expect(fn () => $wallet->rejectPayout($paid, $admin, 'late'))
        ->toThrow(InvalidArgumentException::class, 'already');
});

it('returns held funds when a payout is rejected', function (): void {
    PlatformSetting::set('wallet_hold_days', 0);
    [$business, , $order] = completedOrder();
    $admin = User::factory()->create(['business_id' => null, 'role' => UserRole::Owner]);
    $wallet = app(WalletService::class);

    $payout = $wallet->requestPayout($business, 2000);
    $wallet->rejectPayout($payout, $admin, 'Bank details missing');

    expect($payout->refresh()->status)->toBe(PayoutStatus::Rejected)
        ->and($wallet->walletFor($business)->refresh()->available_cents)->toBe($order->store_payout_cents);
});

it('refunds a completed app order to the card and debits the wallet with the commission reversed', function (): void {
    [$business, , $order] = completedOrder(priceCents: 5000, qty: 2);
    $sale = Sale::query()->where('order_id', $order->id)->firstOrFail();
    $before = app(WalletService::class)->walletFor($business)->refresh()->pending_cents;

    $refund = app(SaleService::class)->refund($sale, [
        ['sale_item_id' => $sale->items()->first()->id, 'qty' => 1],
    ], 'Damaged');

    // One of two units: 50.00 + 7.50 tax back to the card, 1.50 commission returned.
    expect($refund->amount_cents)->toBe(5750)
        ->and($refund->gateway_ref)->toStartWith('re_fake_')
        ->and($order->refresh()->refunded_cents)->toBe(5750)
        ->and($order->payment_status)->toBe(PaymentStatus::Captured);

    $after = app(WalletService::class)->walletFor($business)->refresh()->pending_cents;
    expect($before - $after)->toBe(5750 - 150);
});

it('cancels an accepted order from the store with a full refund and releases stock', function (): void {
    $business = Business::factory()->create();
    $product = Product::factory()->forBusiness($business)->create(['track_stock' => true, 'is_published' => true, 'is_available' => true]);
    $variant = ProductVariant::factory()->forProduct($product)->create(['stock_qty' => 5, 'reserved_qty' => 0, 'retail_price_cents' => 1000]);
    $service = app(OrderService::class);

    $order = $service->place(Customer::factory()->create(), $business, [['variant_id' => $variant->id, 'qty' => 2]], FulfillmentType::Pickup);
    $service->accept($order);

    expect(fn () => $service->cancelByBusiness($order->refresh(), ''))
        ->toThrow(InvalidArgumentException::class, 'reason');

    $cancelled = $service->cancelByBusiness($order->refresh(), 'Out of stock on shelf');

    expect($cancelled->status)->toBe(OrderStatus::Cancelled)
        ->and($cancelled->payment_status)->toBe(PaymentStatus::Refunded)
        ->and($cancelled->refunded_cents)->toBe($cancelled->total_cents)
        ->and((float) $variant->fresh()->reserved_qty)->toBe(0.0)
        ->and((float) $variant->fresh()->stock_qty)->toBe(5.0);
});

it('rejects a paid order with a void so nothing is charged (scenario 12)', function (): void {
    $business = Business::factory()->create();
    $product = Product::factory()->forBusiness($business)->create(['track_stock' => true, 'is_published' => true, 'is_available' => true]);
    $variant = ProductVariant::factory()->forProduct($product)->create(['stock_qty' => 5, 'reserved_qty' => 0, 'retail_price_cents' => 1000]);

    $order = app(OrderService::class)->place(Customer::factory()->create(), $business, [['variant_id' => $variant->id, 'qty' => 1]], FulfillmentType::Pickup);
    $rejected = app(OrderService::class)->reject($order, 'Closing early');

    expect($rejected->payment_status)->toBe(PaymentStatus::Voided)
        ->and($rejected->refunded_cents)->toBe(0)
        ->and(app(WalletService::class)->walletFor($business)->refresh()->balanceCents())->toBe(0);
});

it('does not let a store accept an order whose card is not confirmed yet', function (): void {
    $business = Business::factory()->create();
    $product = Product::factory()->forBusiness($business)->create(['track_stock' => false, 'is_published' => true, 'is_available' => true]);
    $variant = ProductVariant::factory()->forProduct($product)->create(['retail_price_cents' => 1000]);

    $order = app(OrderService::class)->place(Customer::factory()->create(), $business, [['variant_id' => $variant->id, 'qty' => 1]], FulfillmentType::Pickup);
    $order->update(['payment_status' => PaymentStatus::Pending]);

    expect(fn () => app(OrderService::class)->accept($order->refresh()))
        ->toThrow(InvalidArgumentException::class, 'not completed the card payment');
});

it('keeps wallets isolated between stores', function (): void {
    [$first] = completedOrder();
    [$second] = completedOrder();

    expect($first->walletTransactions()->pluck('business_id')->unique()->all())->toBe([$first->id])
        ->and($second->walletTransactions()->pluck('business_id')->unique()->all())->toBe([$second->id]);

    expect(fn () => app(WalletService::class)->requestPayout($first, 100, $second->payoutMethods()->create([
        'type' => 'other', 'label' => 'x', 'details' => ['note' => 'x'],
    ])))->toThrow(InvalidArgumentException::class, 'Invalid payout method');
});
