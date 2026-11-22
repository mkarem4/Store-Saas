<?php

namespace Database\Factories;

use App\Enums\ProductUnit;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        $cost = fake()->numberBetween(100, 5000);
        $retail = (int) round($cost * fake()->randomFloat(2, 1.2, 2.0));

        return [
            'business_id' => fn (array $attributes) => Product::query()->find($attributes['product_id'])?->business_id,
            'product_id' => Product::factory(),
            'name' => ['en' => 'Default', 'ar' => 'افتراضي'],
            'sku' => strtoupper(fake()->bothify('VAR-####??')),
            'barcode' => fake()->ean13(),
            'unit' => ProductUnit::Piece,
            'cost_price_cents' => $cost,
            'wholesale_price_cents' => (int) round($retail * 0.9),
            'retail_price_cents' => $retail,
            'stock_qty' => fake()->numberBetween(0, 100),
            'reserved_qty' => 0,
            'low_stock_threshold' => 5,
            'is_default' => true,
            'attributes' => [],
            'sort_order' => 0,
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (): array => [
            'business_id' => $product->business_id,
            'product_id' => $product->id,
        ]);
    }
}
