<?php

namespace App\Models;

use App\Enums\PayType;
use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property PayType $pay_type
 */
class Employee extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    protected $fillable = [
        'business_id',
        'user_id',
        'name',
        'phone',
        'email',
        'position',
        'pay_type',
        'monthly_salary_cents',
        'hourly_rate_cents',
        'commission_rate_bps',
        'hire_date',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'pay_type' => PayType::class,
            'hire_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<TimeEntry, $this> */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /** @return HasMany<Sale, $this> */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /** @return HasMany<Payslip, $this> */
    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function commissionRate(): float
    {
        return (float) $this->commission_rate_bps / 10000;
    }
}
