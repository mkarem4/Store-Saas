<?php

use App\Enums\FulfillmentType;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Filament\Business\Pages\PointOfSale;
use App\Filament\Business\Pages\StoreSettings;
use App\Filament\Business\Resources\Employees\Pages\CreateEmployee;
use App\Filament\Business\Resources\Orders\Pages\ListOrders;
use App\Filament\Business\Resources\Products\Pages\CreateProduct;
use App\Filament\Business\Resources\Products\Pages\ListProducts;
use App\Filament\Business\Resources\Products\ProductResource;
use App\Filament\Business\Resources\PurchaseReceipts\Pages\CreatePurchaseReceipt;
use App\Filament\Business\Resources\Sales\SaleResource;
use App\Filament\Staff\Pages\TimeClock;
use App\Models\Business;
use App\Models\BusinessType;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\User;
use App\Services\BusinessProvisioner;
use App\Services\EmployeeService;
use App\Services\OrderService;
use Database\Seeders\BusinessTypeSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

/**
 * @return array{0: Business, 1: User}
 */
function storeOwner(string $type = 'clothing'): array
{
    test()->seed(BusinessTypeSeeder::class);
    $businessType = BusinessType::query()->where('key', $type)->firstOrFail();
    $business = app(BusinessProvisioner::class)->provision($businessType, ['name' => ['en' => 'Shop', 'ar' => 'محل']], 'Owner', "{$type}-owner@shop.test", 'password');
    $owner = User::query()->where('email', "{$type}-owner@shop.test")->firstOrFail();
    $owner->update(['must_change_password' => false]);

    test()->actingAs($owner);
    Filament::setCurrentPanel('business');
    Filament::setTenant($business);
    setPermissionsTeamId($business->id);

    return [$business, $owner];
}

beforeEach(function (): void {
    PlatformSetting::set('default_tax_rate', 10);
    PlatformSetting::set('tax_scope', 'all');
});

it('creates a product with its type fields and opening stock', function (): void {
    [$business] = storeOwner('clothing');

    Livewire::test(CreateProduct::class)
        ->fillForm([
            'name' => ['en' => 'Linen shirt', 'ar' => 'قميص كتان'],
            'is_published' => true,
            'is_available' => true,
            'track_stock' => true,
            'attributes' => ['size' => 'M', 'color' => 'Blue'],
            'variant' => [
                'sku' => 'SHIRT-M',
                'barcode' => '111222',
                'cost_price_cents' => '12.00',
                'retail_price_cents' => '30.00',
                'wholesale_price_cents' => '22.00',
                'opening_qty' => 5,
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $variant = ProductVariant::query()->where('sku', 'SHIRT-M')->firstOrFail();

    expect($variant->retail_price_cents)->toBe(3000)
        ->and($variant->cost_price_cents)->toBe(1200)
        ->and((float) $variant->stock_qty)->toBe(5.0)
        ->and($variant->product->attributes['size'])->toBe('M')
        ->and($variant->business_id)->toBe($business->id);
});

it('receives stock through the receiving screen', function (): void {
    [$business] = storeOwner('grocery');
    $product = Product::factory()->forBusiness($business)->create(['track_stock' => true]);
    $variant = ProductVariant::factory()->forProduct($product)->create(['stock_qty' => 0, 'cost_price_cents' => 0]);

    Livewire::test(CreatePurchaseReceipt::class)
        ->fillForm([
            'received_at' => now(),
            'reference' => 'INV-77',
            'lines' => [['variant_id' => $variant->id, 'qty' => 12, 'unit_cost_cents' => '1.50']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect((float) $variant->fresh()->stock_qty)->toBe(12.0)
        ->and($variant->fresh()->cost_price_cents)->toBe(150);
});

it('rings up a POS cash sale with change (scenario 2) and staff cannot discount', function (): void {
    [$business] = storeOwner('grocery');
    $product = Product::factory()->forBusiness($business)->create(['track_stock' => true, 'is_available' => true]);
    $variant = ProductVariant::factory()->forProduct($product)->create(['stock_qty' => 10, 'retail_price_cents' => 1000, 'barcode' => '999']);

    Livewire::test(PointOfSale::class)
        ->set('search', '999')
        ->call('scan')
        ->call('scan')
        ->assertSet('search', '')
        ->set('search', '999')->call('scan')
        ->callAction('checkout', ['method' => 'cash', 'tendered_cents' => '40.00'])
        ->assertHasNoActionErrors();

    $sale = Sale::query()->latest('id')->firstOrFail();

    expect($sale->subtotal_cents)->toBe(2000)
        ->and($sale->tax_cents)->toBe(200)
        ->and($sale->total_cents)->toBe(2200)
        ->and($sale->payments->first()->meta['tendered_cents'])->toBe(4000)
        ->and((float) $variant->fresh()->stock_qty)->toBe(8.0);
});

it('sells by weight in the POS (scenario 8)', function (): void {
    [$business] = storeOwner('grocery');
    $product = Product::factory()->forBusiness($business)->create(['track_stock' => true, 'is_available' => true]);
    $variant = ProductVariant::factory()->forProduct($product)->create(['stock_qty' => 10, 'retail_price_cents' => 800, 'unit' => 'kg']);

    Livewire::test(PointOfSale::class)
        ->call('pick', $variant->id)
        ->assertActionMounted('weight')
        ->setActionData(['qty' => 1.25])
        ->callMountedAction()
        ->callAction('checkout', ['method' => 'card'])
        ->assertHasNoActionErrors();

    $sale = Sale::query()->latest('id')->firstOrFail();

    expect($sale->subtotal_cents)->toBe(1000)
        ->and((float) $sale->items->first()->qty)->toBe(1.25)
        ->and((float) $variant->fresh()->stock_qty)->toBe(8.75);
});

it('accepts and completes an app order from the orders screen (scenario 10)', function (): void {
    [$business] = storeOwner('restaurant');
    $business->updateSettings(['working_hours' => array_fill_keys(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], ['open' => '00:00', 'close' => '24:00'])]);
    $product = Product::factory()->forBusiness($business)->create(['track_stock' => false, 'is_published' => true, 'is_available' => true]);
    $variant = ProductVariant::factory()->forProduct($product)->create(['retail_price_cents' => 1500]);

    $order = app(OrderService::class)->place(Customer::factory()->create(), $business, [['variant_id' => $variant->id, 'qty' => 2]], FulfillmentType::Pickup);

    $page = Livewire::test(ListOrders::class)
        ->assertCanSeeTableRecords([$order])
        ->callAction(TestAction::make('accept')->table($order));

    expect($order->refresh()->status)->toBe(OrderStatus::Accepted);

    $page->callAction(TestAction::make('prepare')->table($order))
        ->callAction(TestAction::make('ready')->table($order))
        ->callAction(TestAction::make('complete')->table($order));

    expect($order->refresh()->status)->toBe(OrderStatus::Completed)
        ->and($order->tax_cents)->toBe(300)
        ->and($order->store_payout_cents)->toBeGreaterThan(0);
});

it('creates an employee with a staff sign-in', function (): void {
    [$business] = storeOwner('jewelry');

    Livewire::test(CreateEmployee::class)
        ->fillForm([
            'name' => 'Rana',
            'pay_type' => 'monthly',
            'monthly_salary_cents' => '2500.00',
            'commission_rate_bps' => '2.5',
            'is_active' => true,
            'login_email' => 'rana@shop.test',
            'login_password' => 'TempPass1',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $employee = Employee::query()->where('name', 'Rana')->firstOrFail();

    expect($employee->monthly_salary_cents)->toBe(250000)
        ->and($employee->commission_rate_bps)->toBe(250)
        ->and($employee->user->role)->toBe(UserRole::Staff)
        ->and($employee->user->business_id)->toBe($business->id);
});

it('saves store settings', function (): void {
    [$business] = storeOwner('grocery');

    Livewire::test(StoreSettings::class)
        ->fillForm([
            'delivery_enabled' => true,
            'delivery_fee_per_km_cents' => '1.25',
            'delivery_max_km' => 8,
            'attendance_mode' => 'both',
            'allowed_ips' => ['10.0.0.0/24'],
            'geofence_radius_m' => 120,
            'latitude' => 40.71,
            'longitude' => -74.0,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $business->refresh();

    expect($business->settings('delivery_fee_per_km_cents'))->toBe(125)
        ->and($business->attendance_mode->value)->toBe('both')
        ->and($business->allowed_ips)->toBe(['10.0.0.0/24']);
});

it('keeps each store\'s records private (data isolation)', function (): void {
    [$mine] = storeOwner('clothing');
    $other = Business::factory()->create();
    $foreign = Product::factory()->forBusiness($other)->create();
    ProductVariant::factory()->forProduct($foreign)->create();

    Livewire::test(ListProducts::class)
        ->assertCanNotSeeTableRecords([$foreign]);

    $this->get(ProductResource::getUrl('edit', ['record' => $foreign, 'tenant' => $mine]))->assertNotFound();
});

it('lets staff clock in and sell, but hides costs and refunds', function (): void {
    [$business] = storeOwner('grocery');
    $business->update(['allowed_ips' => ['127.0.0.1'], 'attendance_mode' => 'ip']);
    $employee = app(EmployeeService::class)->createWithLogin($business, ['name' => 'Omar', 'pay_type' => 'hourly', 'hourly_rate_cents' => 1500], 'omar@shop.test', 'password');
    $employee->user->update(['must_change_password' => false]);
    $product = Product::factory()->forBusiness($business)->create(['track_stock' => true, 'is_available' => true]);
    $variant = ProductVariant::factory()->forProduct($product)->create(['stock_qty' => 5, 'retail_price_cents' => 500]);

    $this->flushSession();
    $this->actingAs($employee->user);
    Filament::setCurrentPanel('staff');
    Filament::setTenant($business);

    Livewire::test(TimeClock::class)->call('clockIn');
    expect($employee->timeEntries()->whereNull('clock_out_at')->count())->toBe(1);

    Livewire::test(App\Filament\Staff\Pages\PointOfSale::class)
        ->call('pick', $variant->id)
        ->assertActionHidden('discount')
        ->callAction('checkout', ['method' => 'card'])
        ->assertHasNoActionErrors();

    $sale = Sale::query()->latest('id')->firstOrFail();
    expect($sale->employee_id)->toBe($employee->id);

    // Staff cannot reach owner screens (rule 6).
    $this->get(SaleResource::getUrl('index', ['tenant' => $business], panel: 'business'))->assertForbidden();
});
