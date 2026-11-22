<?php

namespace Database\Factories;

use App\Enums\PayType;
use App\Models\Business;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'user_id' => null,
            'name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'position' => fake()->jobTitle(),
            'pay_type' => PayType::Monthly,
            'monthly_salary_cents' => fake()->numberBetween(200000, 600000),
            'hourly_rate_cents' => 0,
            'commission_rate_bps' => 0,
            'hire_date' => fake()->dateTimeBetween('-2 years')->format('Y-m-d'),
            'is_active' => true,
        ];
    }

    public function hourly(): static
    {
        return $this->state(fn (): array => [
            'pay_type' => PayType::Hourly,
            'monthly_salary_cents' => 0,
            'hourly_rate_cents' => fake()->numberBetween(1200, 3500),
        ]);
    }

    public function withCommission(int $basisPoints = 500): static
    {
        return $this->state(fn (): array => ['commission_rate_bps' => $basisPoints]);
    }
}
