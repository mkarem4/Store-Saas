<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Store owners create their staff accounts (§3): an employee record plus an
 * optional sign-in for the staff panel with the `staff` role of that store.
 */
class EmployeeService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createWithLogin(Business $business, array $attributes, ?string $email = null, ?string $password = null): Employee
    {
        return DB::transaction(function () use ($business, $attributes, $email, $password): Employee {
            $employee = Employee::query()->create(array_merge(Arr::except($attributes, ['business_id', 'user_id']), [
                'business_id' => $business->id,
                'email' => $email ?? ($attributes['email'] ?? null),
            ]))->refresh();

            if ($email !== null && $password !== null) {
                $this->grantLogin($employee, $email, $password);
            }

            return $employee->refresh()->load('user');
        });
    }

    public function grantLogin(Employee $employee, string $email, string $password): User
    {
        return DB::transaction(function () use ($employee, $email, $password): User {
            $user = $employee->user ?? new User;
            $user->fill([
                'business_id' => $employee->business_id,
                'name' => $employee->name,
                'email' => $email,
                'role' => UserRole::Staff,
                'locale' => $user->locale ?? 'ar',
                'is_active' => (bool) ($employee->is_active ?? true),
            ]);
            $user->password = $password;
            $user->must_change_password = true;
            $user->save();

            $employee->update(['user_id' => $user->id, 'email' => $email]);

            $registrar = app(PermissionRegistrar::class);
            $previous = $registrar->getPermissionsTeamId();
            $registrar->setPermissionsTeamId($employee->business_id);

            try {
                if ($user->roles()->doesntExist()) {
                    $user->assignRole(Role::findOrCreate('staff', 'web'));
                }
            } finally {
                $registrar->setPermissionsTeamId($previous);
            }

            return $user;
        });
    }

    /**
     * Deactivating an employee also blocks their sign-in.
     */
    public function setActive(Employee $employee, bool $active): Employee
    {
        $employee->update(['is_active' => $active]);
        $employee->user?->update(['is_active' => $active]);

        return $employee->refresh();
    }
}
