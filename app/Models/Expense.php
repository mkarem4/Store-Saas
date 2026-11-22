<?php

namespace App\Models;

use App\Enums\ExpenseSource;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Expense extends Model implements HasMedia
{
    use BelongsToBusiness;
    use HasFactory;
    use InteractsWithMedia;

    protected $fillable = [
        'business_id',
        'expense_category_id',
        'amount_cents',
        'description',
        'expense_date',
        'source',
        'reference_type',
        'reference_id',
        'created_by',
        'is_recurring',
        'recurrence',
        'next_due_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'next_due_date' => 'date',
            'source' => ExpenseSource::class,
            'is_recurring' => 'boolean',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('receipt')->singleFile();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
