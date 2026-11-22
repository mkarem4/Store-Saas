<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'business_id' => Business::factory(),
            'category_id' => null,
            'name' => ['en' => $name, 'ar' => $name],
            'description' => ['en' => fake()->sentence(), 'ar' => fake()->sentence()],
            'sku' => strtoupper(fake()->bothify('SKU-####??')),
            'barcode' => fake()->ean13(),
            'attributes' => [],
            'is_published' => true,
            'is_available' => true,
            'track_stock' => true,
            'has_modifiers' => false,
            'sort_order' => 0,
        ];
    }

    public function forBusiness(Business $business): static
    {
        return $this->state(fn (): array => ['business_id' => $business->id]);
    }

    public function inCategory(Category $category): static
    {
        return $this->state(fn (): array => [
            'business_id' => $category->business_id,
            'category_id' => $category->id,
        ]);
    }

    public function restaurantItem(): static
    {
        return $this->state(fn (): array => [
            'track_stock' => false,
            'has_modifiers' => true,
            'prep_time_minutes' => fake()->numberBetween(5, 30),
        ]);
    }
}
