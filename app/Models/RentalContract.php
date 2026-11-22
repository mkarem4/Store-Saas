<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\RentalStatus;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalContract extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'business_id',
        'vehicle_id',
        'client_id',
        'contract_number',
        'starts_at',
        'ends_at',
        'rate_type',
        'rate_cents',
        'deposit_cents',
        'deposit_returned_cents',
        'odometer_out',
        'odometer_in',
        'extra_charges_cents',
        'extra_charges_note',
        'total_cents',
        'status',
        'paid_at',
        'payment_method',
        'picked_up_at',
        'returned_at',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'paid_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'returned_at' => 'datetime',
            'payment_method' => PaymentMethod::class,
            'status' => RentalStatus::class,
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
