<?php

namespace App\Services;

use App\Enums\BusinessStatus;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\BusinessType;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Support\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class BusinessProvisioner
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function provision(
        BusinessType $type,
        array $attributes,
        string $ownerName,
        string $ownerEmail,
        string $ownerPassword,
    ): Business {
        return DB::transaction(function () use ($type, $attributes, $ownerName, $ownerEmail, $ownerPassword): Business {
            $name = $attributes['name'];

            $business = Business::query()->create([
                'business_type_id' => $type->id,
                'name' => $name,
                'slug' => $attributes['slug'] ?? Str::slug(is_array($name) ? ($name['en'] ?? reset($name)) : $name).'-'.Str::lower(Str::random(5)),
                'description' => $attributes['description'] ?? null,
                'phone' => $attributes['phone'] ?? null,
                'email' => $attributes['email'] ?? null,
                'website' => $attributes['website'] ?? null,
                'address_line' => $attributes['address_line'] ?? null,
                'city' => $attributes['city'] ?? null,
                'state' => $attributes['state'] ?? null,
                'postal_code' => $attributes['postal_code'] ?? null,
                'country' => $attributes['country'] ?? 'US',
                'currency' => $attributes['currency'] ?? 'USD',
                'currency_decimals' => $attributes['currency_decimals'] ?? 2,
                'timezone' => $attributes['timezone'] ?? 'America/New_York',
                'status' => $attributes['status'] ?? BusinessStatus::Active,
                'settings' => array_merge(Business::DEFAULT_SETTINGS, $attributes['settings'] ?? []),
            ]);

            $owner = User::query()->create([
                'business_id' => $business->id,
                'name' => $ownerName,
                'email' => $ownerEmail,
                'password' => $ownerPassword,
                'role' => UserRole::BusinessOwner,
                'locale' => 'en',
                'is_active' => true,
            ]);

            $this->createRoles($business, $owner);

            foreach (['rent' => ['en' => 'Rent', 'ar' => 'إيجار'], 'salaries' => ['en' => 'Salaries', 'ar' => 'رواتب'], 'electricity' => ['en' => 'Electricity', 'ar' => 'كهرباء'], 'water' => ['en' => 'Water', 'ar' => 'مياه'], 'maintenance' => ['en' => 'Maintenance', 'ar' => 'صيانة'], 'marketing' => ['en' => 'Marketing', 'ar' => 'تسويق'], 'other' => ['en' => 'Other', 'ar' => 'أخرى']] as $key => $label) {
                ExpenseCategory::query()->firstOrCreate(
                    ['business_id' => $business->id, 'key' => $key],
                    ['name' => $label, 'is_system' => true],
                );
            }

            return $business->refresh();
        });
    }

    private function createRoles(Business $business, User $owner): void
    {
        $registrar = app(PermissionRegistrar::class);
        $previousTeam = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($business->id);

        try {
            $ownerRole = Role::findOrCreate('business_owner', 'web');
            $ownerRole->syncPermissions(Permission::businessOwner());

            $staffRole = Role::findOrCreate('staff', 'web');
            $staffRole->syncPermissions(Permission::staff());

            $owner->syncRoles([$ownerRole]);
        } finally {
            $registrar->setPermissionsTeamId($previousTeam);
        }
    }
}
