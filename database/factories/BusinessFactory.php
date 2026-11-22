<?php

namespace Database\Factories;

use App\Enums\BusinessStatus;
use App\Models\Business;
use App\Models\BusinessType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    protected $model = Business::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'business_type_id' => BusinessType::factory(),
            'name' => ['en' => $name, 'ar' => $name],
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'description' => ['en' => fake()->sentence(), 'ar' => fake()->sentence()],
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'address_line' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'postal_code' => fake()->postcode(),
            'country' => 'US',
            'currency' => 'USD',
            'currency_decimals' => 2,
            'timezone' => 'America/New_York',
            'status' => BusinessStatus::Active,
            'settings' => Business::DEFAULT_SETTINGS,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => BusinessStatus::Inactive]);
    }

    public function ofType(string $key): static
    {
        return $this->state(fn (): array => [
            'business_type_id' => BusinessType::query()->where('key', $key)->firstOrFail()->id,
        ]);
    }
}
