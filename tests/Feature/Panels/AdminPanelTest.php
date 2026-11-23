<?php

use App\Enums\BusinessStatus;
use App\Enums\PayoutStatus;
use App\Enums\UserRole;
use App\Filament\Admin\Pages\PlatformSettings;
use App\Filament\Admin\Resources\Businesses\Pages\CreateBusiness;
use App\Filament\Admin\Resources\Businesses\Pages\ListBusinesses;
use App\Filament\Admin\Resources\PayoutRequests\Pages\ListPayoutRequests;
use App\Filament\Admin\Resources\TaxRates\Pages\ManageTaxRates;
use App\Filament\Admin\Widgets\PlatformStats;
use App\Models\Business;
use App\Models\BusinessType;
use App\Models\ExpenseCategory;
use App\Models\PlatformSetting;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\WalletService;
use App\Support\PlatformConfig;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->admin = User::factory()->create(['business_id' => null, 'role' => UserRole::Owner, 'is_active' => true]);
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('admin');
});

it('creates a store together with its owner account (scenario 1)', function (): void {
    $type = BusinessType::factory()->create(['key' => 'clothing']);

    Livewire::test(CreateBusiness::class)
        ->fillForm([
            'business_type_id' => $type->id,
            'status' => 'active',
            'name' => ['en' => 'Blue Threads', 'ar' => 'خيوط زرقاء'],
            'country' => 'US',
            'state' => 'TX',
            'timezone' => 'America/Chicago',
            'currency' => 'USD',
            'latitude' => 29.76,
            'longitude' => -95.36,
            'owner_name' => 'Lina',
            'owner_email' => 'lina@blue.test',
            'owner_password' => 'TempPass123',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $business = Business::query()->where('state', 'TX')->firstOrFail();
    $owner = User::query()->where('email', 'lina@blue.test')->firstOrFail();

    expect($business->translated('name', 'ar'))->toBe('خيوط زرقاء')
        ->and((float) $business->latitude)->toBe(29.76)
        ->and($owner->business_id)->toBe($business->id)
        ->and($owner->role)->toBe(UserRole::BusinessOwner)
        ->and($owner->must_change_password)->toBeTrue()
        ->and($business->expenses()->getRelated()->newQuery()->count())->toBe(0)
        ->and(ExpenseCategory::query()->where('business_id', $business->id)->count())->toBe(9);
});

it('shows the inactive store count and filters the list for activation review', function (): void {
    $inactive = Business::factory()->create(['status' => BusinessStatus::Inactive]);
    $active = Business::factory()->create(['status' => BusinessStatus::Active]);

    Livewire::test(PlatformStats::class)
        ->assertSee(__('Inactive stores'))
        ->assertSee('filters%5Bstatus%5D%5Bvalue%5D=inactive', false);

    Livewire::withQueryParams(['filters' => ['status' => ['value' => BusinessStatus::Inactive->value]]])
        ->test(ListBusinesses::class)
        ->assertCanSeeTableRecords([$inactive])
        ->assertCanNotSeeTableRecords([$active]);
});

it('manages regional tax rates', function (): void {
    Livewire::test(ManageTaxRates::class)
        ->callAction('create', ['country' => 'US', 'state' => 'TX', 'rate' => 8.25, 'is_active' => true])
        ->assertHasNoFormErrors();

    expect(TaxRate::lookup('US', 'TX'))->toBe(8.25);
});

it('approves and settles a payout from the admin table (scenario 18)', function (): void {
    PlatformSetting::set('wallet_hold_days', 0);
    $business = Business::factory()->create();
    app(WalletService::class)->adjust($business, 10000, 'Opening balance');
    $payout = app(WalletService::class)->requestPayout($business, 4000);

    Livewire::test(ListPayoutRequests::class)
        ->assertCanSeeTableRecords([$payout])
        ->callAction(TestAction::make('approve')->table($payout))
        ->callAction(TestAction::make('markPaid')->table($payout), ['reference' => 'ACH-55'])
        ->assertHasNoFormErrors();

    expect($payout->refresh()->status)->toBe(PayoutStatus::Paid)
        ->and($payout->reference)->toBe('ACH-55');
});

it('saves platform settings', function (): void {
    Livewire::test(PlatformSettings::class)
        ->fillForm(['commission_rate' => 4, 'wallet_hold_days' => 3, 'default_tax_rate' => 10, 'tax_scope' => 'all', 'platform_name' => 'Yulio', 'minimum_payout_cents' => '25.00'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(PlatformConfig::commissionRate())->toBe(4.0)
        ->and(PlatformConfig::walletHoldDays())->toBe(3)
        ->and(PlatformConfig::minimumPayoutCents())->toBe(2500);
});

it('impersonates a store owner with an audit trail and returns', function (): void {
    $business = Business::factory()->create();
    $owner = User::factory()->create(['business_id' => $business->id, 'role' => UserRole::BusinessOwner, 'is_active' => true]);

    $this->get(route('impersonate.start', $owner))->assertRedirect(url('/business/'.$business->slug));
    $this->assertAuthenticatedAs($owner);

    $this->get(route('impersonate.leave'))->assertRedirect(url('/admin/businesses'));
    $this->assertAuthenticatedAs($this->admin);

    expect(Activity::query()->where('log_name', 'impersonation')->count())->toBe(2);
});

it('refuses impersonation to non-admins', function (): void {
    $business = Business::factory()->create();
    $owner = User::factory()->create(['business_id' => $business->id, 'role' => UserRole::BusinessOwner]);
    $other = User::factory()->create(['business_id' => $business->id, 'role' => UserRole::BusinessOwner]);

    $this->actingAs($owner)->get(route('impersonate.start', $other))->assertForbidden();
});
