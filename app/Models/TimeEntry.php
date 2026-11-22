<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEntry extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'business_id',
        'employee_id',
        'clock_in_at',
        'clock_out_at',
        'clock_in_ip',
        'clock_in_lat',
        'clock_in_lng',
        'clock_out_ip',
        'clock_out_lat',
        'clock_out_lng',
        'is_auto_closed',
        'reviewed_at',
        'reviewed_by',
        'review_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'clock_in_at' => 'datetime',
            'clock_out_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'is_auto_closed' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function minutesWorked(): int
    {
        if ($this->clock_out_at === null) {
            return 0;
        }

        return (int) $this->clock_in_at->diffInMinutes($this->clock_out_at);
    }
}
