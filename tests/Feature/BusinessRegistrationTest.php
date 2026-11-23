<?php

use App\Enums\BusinessStatus;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\BusinessType;
use App\Models\User;
use Database\Seeders\BusinessTypeSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(BusinessTypeSeeder::class);
});

it('lists active store types on the public registration page', function (): void {
    $grocery = BusinessType::query()->where('key', 'grocery')->firstOrFail();
    $grocery->update(['name' => ['en' => 'Hidden grocery type'], 'is_active' => false]);

    $this->withHeader('Accept-Language', 'en')->get(route('home'))
        ->assertOk()
        ->assertSee('name="business_type_id"', false)
        ->assertSee(BusinessType::query()->where('key', 'perfume')->firstOrFail()->translatedName())
        ->assertDontSee('Hidden grocery type');
});

it('provisions a tenant and an owner who can sign in after activation', function (): void {
    $type = BusinessType::query()->where('key', 'grocery')->firstOrFail();
    $data = [
        'business_name' => 'Neighborhood shop',
        'business_type_id' => $type->id,
        'owner_name' => 'Shop owner',
        'email' => 'owner@shop.test',
        'password' => 'correct-password',
        'password_confirmation' => 'correct-password',
        'status' => BusinessStatus::Active->value,
        'role' => UserRole::Owner->value,
    ];

    $this->post(route('business.registration'), $data)
        ->assertRedirect(route('home'))
        ->assertSessionHas('registration_submitted');

    $business = Business::query()->sole();
    $owner = User::query()->sole();
    expect($business->status)->toBe(BusinessStatus::Inactive)
        ->and($business->business_type_id)->toBe($type->id)
        ->and($owner->business_id)->toBe($business->id)
        ->and($owner->role)->toBe(UserRole::BusinessOwner)
        ->and(Hash::check($data['password'], $owner->password))->toBeTrue()
        ->and($owner->canAccessPanel(Filament::getPanel('business')))->toBeFalse();

    $business->update(['status' => BusinessStatus::Active]);
    expect($owner->fresh()->canAccessPanel(Filament::getPanel('business')))->toBeTrue();

    $this->post(route('business.registration'), $data)->assertSessionHasErrors('email');
    $this->assertDatabaseCount('businesses', 1);
});

it('rejects inactive types and mismatched passwords without creating a tenant', function (): void {
    $type = BusinessType::query()->where('key', 'grocery')->firstOrFail();
    $type->update(['is_active' => false]);

    $this->post(route('business.registration'), [
        'business_name' => 'Neighborhood shop',
        'business_type_id' => $type->id,
        'owner_name' => 'Shop owner',
        'email' => 'owner@shop.test',
        'password' => 'correct-password',
        'password_confirmation' => 'different-password',
    ])->assertSessionHasErrors(['business_type_id', 'password']);

    $this->assertDatabaseCount('businesses', 0);
    $this->assertDatabaseCount('users', 0);
});
