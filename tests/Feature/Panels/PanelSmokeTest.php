<?php

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\BusinessType;
use App\Models\User;
use App\Services\BusinessProvisioner;
use App\Services\EmployeeService;
use Database\Seeders\BusinessTypeSeeder;
use Illuminate\Support\Facades\Route;

function panelGetRoutes(string $panel): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => in_array('GET', $route->methods(), true))
        ->filter(fn ($route) => str_starts_with((string) $route->getName(), "filament.{$panel}."))
        ->reject(fn ($route) => str_contains((string) $route->getName(), '.auth.'))
        ->reject(fn ($route) => str_contains($route->uri(), '{record}'))
        ->reject(fn ($route) => str_ends_with((string) $route->getName(), '.tenant'))
        ->map(fn ($route) => $route->getName())
        ->values()
        ->all();
}

it('renders every admin page for the platform owner', function (): void {
    $owner = User::factory()->create(['business_id' => null, 'role' => UserRole::Owner, 'is_active' => true]);
    Business::factory()->create();

    foreach (panelGetRoutes('admin') as $name) {
        $response = $this->actingAs($owner)->get(route($name));
        expect($response->status())->toBeLessThan(300, "{$name} returned {$response->status()}");
    }
})->group('panels');

it('renders every business page for the store types it belongs to', function (): void {
    $this->seed(BusinessTypeSeeder::class);
    $rendered = [];

    foreach (BusinessType::query()->get() as $type) {
        $business = app(BusinessProvisioner::class)->provision($type, ['name' => ['en' => $type->key, 'ar' => $type->key]], 'Owner', "{$type->key}@shop.test", 'password');
        $owner = User::query()->where('email', "{$type->key}@shop.test")->firstOrFail();
        $owner->update(['must_change_password' => false]);
        $this->flushSession();

        foreach (panelGetRoutes('business') as $name) {
            $response = $this->actingAs($owner)->get(route($name, ['tenant' => $business->slug]));
            $status = $response->status();

            expect(in_array($status, [200, 403], true))->toBeTrue("{$type->key}: {$name} returned {$status}: ".substr(strip_tags((string) $response->getContent()), 0, 400));

            if ($status === 200) {
                $rendered[$name] = true;
            }
        }
    }

    $never = array_diff(panelGetRoutes('business'), array_keys($rendered));
    expect($never)->toBe([], 'Never rendered: '.implode(', ', $never));
})->group('panels');

it('renders every staff page for an employee', function (): void {
    $this->seed(BusinessTypeSeeder::class);
    $type = BusinessType::query()->where('key', 'car_showroom')->firstOrFail();
    $business = app(BusinessProvisioner::class)->provision($type, ['name' => ['en' => 'Cars', 'ar' => 'سيارات']], 'Owner', 'cars@shop.test', 'password');
    $staff = app(EmployeeService::class)->createWithLogin($business, ['name' => 'Sam', 'pay_type' => 'hourly', 'hourly_rate_cents' => 1500], 'sam@shop.test', 'password');
    $staff->user->update(['must_change_password' => false]);

    foreach (panelGetRoutes('staff') as $name) {
        $response = $this->actingAs($staff->user)->get(route($name, ['tenant' => $business->slug]));
        // The showroom has no POS (cars are sold from "Sell a car").
        $expected = match (true) {
            str_ends_with($name, '.pos') => 403, str_ends_with($name, '.home') => 302, default => 200
        };
        expect($response->status())->toBe($expected, "{$name} returned {$response->status()}: ".substr(strip_tags((string) $response->getContent()), 0, 400));
    }
})->group('panels');
