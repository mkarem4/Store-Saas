<?php

use App\Models\BusinessType;
use App\Models\User;
use App\Services\BusinessProvisioner;
use App\Support\Permission;
use Database\Seeders\BusinessTypeSeeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

it('grants permissions added after a store was provisioned to its owner role', function (): void {
    $this->seed(BusinessTypeSeeder::class);
    $type = BusinessType::query()->where('key', 'grocery')->firstOrFail();
    $business = app(BusinessProvisioner::class)->provision($type, ['name' => ['en' => 'Shop', 'ar' => 'محل']], 'Owner', 'sync@shop.test', 'password');

    setPermissionsTeamId($business->id);
    $ownerRole = Role::findByName('business_owner', 'web');
    $ownerRole->revokePermissionTo(Permission::ManageWallet);
    $staffRole = Role::findByName('staff', 'web');
    $staffRole->givePermissionTo(Permission::ManageClients);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->artisan('permissions:sync')->assertSuccessful();

    $owner = User::query()->where('email', 'sync@shop.test')->firstOrFail();
    expect($owner->fresh()->can(Permission::ManageWallet))->toBeTrue()
        // the owner's own changes to the staff role are kept
        ->and($staffRole->fresh()->hasPermissionTo(Permission::ManageClients))->toBeTrue();
});
