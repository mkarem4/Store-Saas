<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'phone',
        'email',
        'id_number',
        'license_number',
        'notes',
    ];

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'buyer_client_id');
    }

    public function rentalContracts(): HasMany
    {
        return $this->hasMany(RentalContract::class);
    }
}
