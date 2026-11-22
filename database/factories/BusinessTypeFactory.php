<?php

namespace Database\Factories;

use App\Models\BusinessType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BusinessType>
 */
class BusinessTypeFactory extends Factory
{
    protected $model = BusinessType::class;

    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'key' => Str::slug($name),
            'name' => ['en' => Str::title($name), 'ar' => $name],
            'icon' => 'heroicon-o-building-storefront',
            'fields' => [],
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
