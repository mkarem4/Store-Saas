<?php

namespace Database\Factories;

use App\Enums\SaleChannel;
use App\Enums\SaleStatus;
use App\Models\Business;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition(): array
    {
        $subtotal = fake()->numberBetween(1000, 100000);

        return [
            'business_id' => Business::factory(),
            'number' => 'S-'.fake()->unique()->numerify('######'),
            'channel' => SaleChannel::Pos,
            'subtotal_cents' => $subtotal,
            'discount_cents' => 0,
            'tax_cents' => 0,
            'total_cents' => $subtotal,
            'cogs_cents' => (int) round($subtotal * 0.6),
            'status' => SaleStatus::Completed,
            'sold_at' => now(),
        ];
    }
}
