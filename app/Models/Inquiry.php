<?php

namespace App\Models;

use App\Enums\InquiryStatus;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inquiry extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'business_id',
        'customer_id',
        'vehicle_id',
        'type',
        'name',
        'phone',
        'email',
        'message',
        'status',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'status' => InquiryStatus::class,
            'meta' => 'array',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
