<?php

use App\Contracts\PaymentGateway;
use App\Contracts\PushNotifier;
use App\Enums\FulfillmentType;
use App\Models\Business;
use App\Models\BusinessType;
use App\Models\Customer;
use App\Models\Inquiry;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Notifications\CustomerResetPassword;
use App\Services\OrderService;
use App\Services\Payments\FakeGateway;
use Illuminate\Support\Facades\Notification;

function apiBusinessWithVariant(int $stockQty = 100): array
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

beforeEach(function (): void {
    $this->app->bind(PaymentGateway::class, fn (): PaymentGateway => new FakeGateway);
});

it('registers a customer and returns a token', function (): void {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Layla',
        'phone' => '+15550001',
        'email' => 'layla@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['token', 'customer' => ['id', 'name', 'email', 'phone']]);

    expect(Customer::query()->where('email', 'layla@example.test')->exists())->toBeTrue();
});

it('rejects a duplicate registration email', function (): void {
    Customer::factory()->create(['email' => 'dup@example.test']);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Dup',
        'phone' => '+15550002',
        'email' => 'dup@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertStatus(422);
});

it('logs a customer in and out by phone', function (): void {
    $customer = Customer::factory()->create();

    $login = $this->postJson('/api/v1/auth/login', [
        'phone' => $customer->phone,
        'password' => 'password',
    ]);

    $login->assertOk()->assertJsonStructure(['token']);
    $token = $login->json('token');

    $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.id', $customer->id);

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

    // The guard instance is cached in the container across in-process test
    // requests; reset it so the follow-up request re-resolves authentication.
    $this->app['auth']->forgetGuards();

    $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
});

it('rejects login with the wrong password', function (): void {
    $customer = Customer::factory()->create();

    $this->postJson('/api/v1/auth/login', [
        'phone' => $customer->phone,
        'password' => 'nope',
    ])->assertStatus(422);
});

it('lists published products for a business', function (): void {
    [$business, $product, $variant] = apiBusinessWithVariant();

    $this->getJson('/api/v1/businesses/'.$business->slug.'/products')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $product->id)
        ->assertJsonCount(1, 'data.0.variants');
});

it('quotes a cart end to end', function (): void {
    [$business, , $variant] = apiBusinessWithVariant();

    $this->actingAs(Customer::factory()->create(), 'customer')->postJson('/api/v1/orders/quote', [
        'business' => $business->slug,
        'fulfillment' => 'pickup',
        'items' => [
            ['variant_id' => $variant->id, 'qty' => 2],
        ],
    ])->assertOk()
        ->assertJsonPath('data.subtotal_cents', 2000)
        ->assertJsonPath('data.tax_cents', 300)
        ->assertJsonPath('data.total_cents', 2300);
});

it('places, fetches and cancels an order as the authenticated customer', function (): void {
    [$business, , $variant] = apiBusinessWithVariant();
    $customer = Customer::factory()->create();

    $store = $this->actingAs($customer, 'customer')->postJson('/api/v1/orders', [
        'business' => $business->slug,
        'fulfillment' => 'pickup',
        'items' => [
            ['variant_id' => $variant->id, 'qty' => 2],
        ],
    ], ['Idempotency-Key' => 'test-key-1']);

    $store->assertCreated()
        ->assertJsonStructure(['data' => ['number', 'status', 'total_cents'], 'payment_client_secret']);

    $number = $store->json('data.number');

    $this->actingAs($customer, 'customer')->getJson('/api/v1/orders')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.number', $number);

    $this->actingAs($customer, 'customer')->getJson('/api/v1/orders/'.$number)
        ->assertOk()
        ->assertJsonPath('data.number', $number)
        ->assertJsonCount(1, 'data.items');

    $this->actingAs($customer, 'customer')->postJson('/api/v1/orders/'.$number.'/cancel')
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});

it('blocks unauthenticated order access', function (): void {
    $this->getJson('/api/v1/orders')->assertUnauthorized();
    $this->postJson('/api/v1/orders', [])->assertUnauthorized();
});

it('only replays an order for its customer and store', function (): void {
    [$business, , $variant] = apiBusinessWithVariant();
    [$otherBusiness, , $otherVariant] = apiBusinessWithVariant();
    $customer = Customer::factory()->create();
    $otherCustomer = Customer::factory()->create();
    $payload = [
        'business' => $business->slug,
        'fulfillment' => 'pickup',
        'items' => [['variant_id' => $variant->id, 'qty' => 1]],
    ];
    $headers = ['Idempotency-Key' => 'customer-order-retry'];

    $order = $this->actingAs($customer, 'customer')->postJson('/api/v1/orders', $payload, $headers)
        ->assertCreated();

    $this->postJson('/api/v1/orders', $payload, $headers)
        ->assertCreated()
        ->assertJsonPath('data.number', $order->json('data.number'));

    $this->postJson('/api/v1/orders', [
        ...$payload,
        'business' => $otherBusiness->slug,
        'items' => [['variant_id' => $otherVariant->id, 'qty' => 1]],
    ], $headers)->assertUnprocessable();

    $this->actingAs($otherCustomer, 'customer')->postJson('/api/v1/orders', $payload, $headers)
        ->assertUnprocessable()
        ->assertJsonMissingPath('data');

    $url = '/api/v1/orders/'.$order->json('data.number');
    $this->getJson($url)->assertNotFound();
    $this->postJson($url.'/cancel')->assertNotFound();
    $this->assertDatabaseCount('orders', 1);
});

it('uses the store to disambiguate matching order numbers for one customer', function (): void {
    [$business, , $variant] = apiBusinessWithVariant();
    [$otherBusiness, , $otherVariant] = apiBusinessWithVariant();
    $this->actingAs(Customer::factory()->create(), 'customer');

    $number = $this->postJson('/api/v1/orders', [
        'business' => $business->slug,
        'fulfillment' => 'pickup',
        'items' => [['variant_id' => $variant->id, 'qty' => 1]],
    ])->assertCreated()->json('data.number');

    $this->postJson('/api/v1/orders', [
        'business' => $otherBusiness->slug,
        'fulfillment' => 'pickup',
        'items' => [['variant_id' => $otherVariant->id, 'qty' => 1]],
    ])->assertCreated()->assertJsonPath('data.number', $number);

    $url = '/api/v1/orders/'.$number;
    $this->getJson($url)->assertConflict();
    $this->postJson($url.'/cancel')->assertConflict();
    $this->getJson($url.'?business='.$business->slug)->assertOk()->assertJsonPath('data.business', $business->slug);
    $this->postJson($url.'/cancel?business='.$otherBusiness->slug)
        ->assertOk()
        ->assertJsonPath('data.business', $otherBusiness->slug)
        ->assertJsonPath('data.status', 'cancelled');
    $this->getJson($url.'?business='.$business->slug)->assertOk()->assertJsonPath('data.status', 'pending');
});

it('manages customer addresses with ownership checks', function (): void {
    $owner = Customer::factory()->create();
    $other = Customer::factory()->create();

    $store = $this->actingAs($owner, 'customer')->postJson('/api/v1/addresses', [
        'label' => 'Home',
        'address_line1' => '12 Main St',
        'city' => 'Cairo',
    ]);

    $store->assertCreated();
    $addressId = $store->json('data.id');

    $this->actingAs($owner, 'customer')->getJson('/api/v1/addresses')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->actingAs($owner, 'customer')->patchJson("/api/v1/addresses/{$addressId}", [
        'address_line1' => '13 Main St',
        'city' => 'Cairo',
    ])->assertOk()->assertJsonPath('data.address_line1', '13 Main St');

    // A different customer cannot touch this address.
    $this->actingAs($other, 'customer')->patchJson("/api/v1/addresses/{$addressId}", [
        'address_line1' => 'hack',
        'city' => 'Cairo',
    ])->assertForbidden();

    $this->actingAs($owner, 'customer')->deleteJson("/api/v1/addresses/{$addressId}")->assertNoContent();
});

it('submits an inquiry for a business', function (): void {
    [$business] = apiBusinessWithVariant();

    $response = $this->postJson('/api/v1/inquiries', [
        'business' => $business->slug,
        'type' => 'general',
        'name' => 'Visitor',
        'phone' => '+15550009',
        'message' => 'Is this in stock?',
    ]);

    $response->assertCreated()->assertJsonPath('data.status', 'new');

    expect(Inquiry::query()->count())->toBe(1);
});

it('handles a Stripe payment_intent.succeeded webhook', function (): void {
    $business = Business::factory()->create();
    $product = Product::factory()->forBusiness($business)->create([
        'track_stock' => true,
        'is_published' => true,
        'is_available' => true,
    ]);
    $variant = ProductVariant::factory()->forProduct($product)->create(['stock_qty' => 10, 'retail_price_cents' => 1000]);

    $customer = Customer::factory()->create();

    $order = app(OrderService::class)->place(
        $customer,
        $business,
        [['variant_id' => $variant->id, 'qty' => 1]],
        FulfillmentType::Pickup,
    );

    $payload = json_encode([
        'id' => 'evt_1',
        'object' => 'event',
        'type' => 'payment_intent.succeeded',
        'data' => [
            'object' => [
                'id' => $order->payment_intent_id,
                'metadata' => ['order_id' => $order->id],
            ],
        ],
    ]);

    $response = $this->call('POST', '/webhooks/stripe', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => 't=1,v1=abc',
    ], $payload);

    $response->assertOk();

    $order->refresh();

    // Captured and accepted; the unit stays reserved until hand-over.
    expect($order->status->value)->toBe('accepted')
        ->and($order->payment_status->value)->toBe('captured')
        ->and((float) $variant->fresh()->stock_qty)->toBe(10.0)
        ->and((float) $variant->fresh()->reserved_qty)->toBe(1.0);

    // Stripe retries the same event: processed once only.
    $this->call('POST', '/webhooks/stripe', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], $payload)->assertOk()->assertJsonPath('duplicate', true);

    expect(OrderStatusHistory::query()->where('order_id', $order->id)->where('status', 'accepted')->count())->toBe(1);
});

it('resets a customer password with the e-mailed token', function (): void {
    Notification::fake();
    $customer = Customer::factory()->create(['email' => 'mia@app.test']);

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'mia@app.test'])->assertOk();
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@app.test'])->assertOk();

    $token = null;
    Notification::assertSentTo($customer, CustomerResetPassword::class, function ($notification) use (&$token): bool {
        $token = $notification->token;

        return true;
    });

    $this->postJson('/api/v1/auth/reset-password', ['email' => 'mia@app.test', 'token' => $token, 'password' => 'NewSecret99', 'password_confirmation' => 'NewSecret99'])->assertOk();

    $this->postJson('/api/v1/auth/login', ['email' => 'mia@app.test', 'password' => 'NewSecret99'])->assertOk()->assertJsonStructure(['token']);
});

it('pushes order status changes to the customer devices', function (): void {
    $push = new class implements PushNotifier
    {
        public array $sent = [];

        public function send(Customer $customer, string $title, string $body, array $data = []): int
        {
            $this->sent[] = $data['status'];

            return 1;
        }
    };
    app()->instance(PushNotifier::class, $push);

    $business = Business::factory()->create();
    $product = Product::factory()->forBusiness($business)->create(['track_stock' => false, 'is_published' => true, 'is_available' => true]);
    $variant = ProductVariant::factory()->forProduct($product)->create(['retail_price_cents' => 1000]);
    $order = app(OrderService::class)->place(Customer::factory()->create(), $business, [['variant_id' => $variant->id, 'qty' => 1]], FulfillmentType::Pickup);

    app(OrderService::class)->accept($order);
    $order->update(['expires_at' => now()->subMinute()]);

    expect($push->sent)->toBe(['accepted']);
});

it('filters stores by type, name and fulfillment, paginated (M8.1)', function (): void {
    $grocery = BusinessType::factory()->create(['key' => 'grocery']);
    Business::factory()->withSettings(['delivery_enabled' => true])->create(['business_type_id' => $grocery->id, 'name' => ['en' => 'Fresh Market', 'ar' => 'فريش']]);
    Business::factory()->withSettings(['delivery_enabled' => false])->create(['business_type_id' => $grocery->id, 'name' => ['en' => 'Corner Shop', 'ar' => 'ركن']]);
    Business::factory()->create(['name' => ['en' => 'Other', 'ar' => 'آخر']]);

    $this->getJson('/api/v1/businesses?type=grocery')->assertOk()->assertJsonPath('meta.total', 2);
    $this->getJson('/api/v1/businesses?type=grocery&fulfillment=delivery')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/businesses?q=Corner')->assertOk()->assertJsonPath('data.0.name.en', 'Corner Shop');
});

it('lists out-of-stock items as unavailable instead of hiding them (M8.2)', function (): void {
    $business = Business::factory()->create();
    $product = Product::factory()->forBusiness($business)->create(['track_stock' => true, 'is_published' => true, 'is_available' => true]);
    ProductVariant::factory()->forProduct($product)->create(['stock_qty' => 0, 'reserved_qty' => 0]);

    $this->getJson('/api/v1/businesses/'.$business->slug.'/products')
        ->assertOk()
        ->assertJsonPath('data.0.available', false)
        ->assertJsonPath('data.0.variants.0.available', false);
});
