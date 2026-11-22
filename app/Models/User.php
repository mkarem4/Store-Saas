<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property int|null $business_id
 * @property string $name
 * @property string $email
 * @property string $role
 * @property string $locale
 * @property bool $is_active
 * @property bool $must_change_password
 * @property Carbon|null $last_login_at
 * @property Carbon|null $email_verified_at
 * @property string $password
 */
#[Fillable([
    'business_id',
    'name',
    'email',
    'password',
    'role',
    'locale',
    'is_active',
    'must_change_password',
    'last_login_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Setting a new password yourself clears the temporary-password flag;
        // an admin reset sets both fields and keeps it.
        static::saving(function (self $user): void {
            if ($user->exists && $user->isDirty('password') && ! $user->isDirty('must_change_password')) {
                $user->must_change_password = false;
            }
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return match ($panel->getId()) {
            'admin' => $this->role === UserRole::Owner,
            'business' => $this->role === UserRole::BusinessOwner && $this->business?->isActive(),
            'staff' => $this->role === UserRole::Staff && $this->business?->isActive(),
            default => false,
        };
    }

    public function isOwner(): bool
    {
        return $this->role === UserRole::Owner;
    }

    /**
     * @return Collection<int, Business>
     */
    public function getTenants(Panel $panel): Collection
    {
        return $this->business === null
            ? collect()
            : collect([$this->business]);
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->business_id !== null && (int) $tenant->getKey() === (int) $this->business_id;
    }

    public function isBusinessOwner(): bool
    {
        return $this->role === UserRole::BusinessOwner;
    }

    public function isStaff(): bool
    {
        return $this->role === UserRole::Staff;
    }

    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
