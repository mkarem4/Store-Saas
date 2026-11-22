<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'business_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Staff,
            'locale' => 'en',
            'is_active' => true,
            'must_change_password' => false,
            'remember_token' => Str::random(10),
        ];
    }

    public function owner(): static
    {
        return $this->state(fn (): array => [
            'business_id' => null,
            'role' => UserRole::Owner,
        ]);
    }

    public function businessOwner(?Business $business = null): static
    {
        return $this->state(fn (): array => [
            'business_id' => $business?->id ?? Business::factory(),
            'role' => UserRole::BusinessOwner,
        ]);
    }

    public function staff(?Business $business = null): static
    {
        return $this->state(fn (): array => [
            'business_id' => $business?->id ?? Business::factory(),
            'role' => UserRole::Staff,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (): array => [
            'email_verified_at' => null,
        ]);
    }
}
