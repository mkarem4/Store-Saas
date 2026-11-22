<?php

namespace Database\Factories;

use App\Enums\FulfillmentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $subtotal = fake()->numberBetween(1000, 50000);
        $deliveryFee = 500;
        $tax = (int) round($subtotal * 0.15);

        return [
            'business_id' => Business::factory(),
            'customer_id' => Customer::factory(),
            'number' => 'ORD-'.strtoupper(Str::random(8)),
            'fulfillment' => FulfillmentType::Pickup,
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Authorized,
            'subtotal_cents' => $subtotal,
            'delivery_fee_cents' => 0,
            'discount_cents' => 0,
            'tax_cents' => $tax,
            'total_cents' => $subtotal + $tax,
            'cogs_cents' => (int) round($subtotal * 0.6),
            'idempotency_key' => (string) Str::uuid(),
            'placed_at' => now(),
            'expires_at' => now()->addMinutes(10),
        ];
    }
}
