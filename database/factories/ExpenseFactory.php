<?php

namespace Database\Factories;

use App\Enums\ExpenseSource;
use App\Models\Business;
use App\Models\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'expense_category_id' => null,
            'amount_cents' => fake()->numberBetween(1000, 100000),
            'description' => fake()->sentence(3),
            'expense_date' => fake()->dateTimeBetween('-1 month')->format('Y-m-d'),
            'source' => ExpenseSource::Manual,
            'is_recurring' => false,
        ];
    }
}
