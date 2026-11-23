<?php

namespace App\Models;

use App\Enums\VehicleStatus;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Vehicle extends Model implements HasMedia
{
    use BelongsToBusiness;
    use HasFactory;
    use InteractsWithMedia;
    use SoftDeletes;

    protected $fillable = [
        'business_id',
        'vin',
        'make',
        'model',
        'year',
        'color',
        'mileage',
        'plate_number',
        'purchase_price_cents',
        'listing_price_cents',
        'sold_price_cents',
        'status',
        'purpose',
        'buyer_client_id',
        'sold_by_employee_id',
        'sold_at',
        'attributes',
    ];

    protected function casts(): array
    {
        return [
            'status' => VehicleStatus::class,
            'sold_at' => 'datetime',
            'attributes' => 'array',
        ];
    }

    public function costs(): HasMany
    {
        return $this->hasMany(VehicleCost::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'buyer_client_id');
    }

    public function soldBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'sold_by_employee_id');
    }

    public function rentalContracts(): HasMany
    {
        return $this->hasMany(RentalContract::class);
    }

    public function totalCostCents(): int
    {
        return (int) $this->purchase_price_cents + (int) $this->costs()->sum('amount_cents');
    }

    public function profitCents(): int
    {
        return (int) ($this->sold_price_cents ?? 0) - $this->totalCostCents();
    }

    public function displayName(): string
    {
        return trim("{$this->year} {$this->make} {$this->model}");
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos');
    }
}
