<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessType extends Model
{
    use HasFactory;
    use HasTranslations;

    protected $fillable = [
        'key',
        'name',
        'icon',
        'fields',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'fields' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class);
    }

    public function translatedName(): string
    {
        return $this->translated('name');
    }
}
