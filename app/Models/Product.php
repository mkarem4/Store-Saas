<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Product extends Model implements HasMedia
{
    use BelongsToBusiness;
    use HasFactory;
    use HasTranslations;
    use InteractsWithMedia;
    use SoftDeletes;

    protected $fillable = [
        'business_id',
        'category_id',
        'name',
        'description',
        'sku',
        'barcode',
        'attributes',
        'is_published',
        'is_available',
        'track_stock',
        'has_modifiers',
        'prep_time_minutes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'attributes' => 'array',
            'is_published' => 'boolean',
            'is_available' => 'boolean',
            'track_stock' => 'boolean',
            'has_modifiers' => 'boolean',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function modifierGroups(): HasMany
    {
        return $this->hasMany(ModifierGroup::class)->orderBy('sort_order');
    }

    public function translatedName(): string
    {
        return $this->translated('name');
    }

    public function defaultVariant(): ?ProductVariant
    {
        return $this->variants->firstWhere('is_default', true) ?? $this->variants->first();
    }

    public function isAvailableForSale(): bool
    {
        if (! $this->is_available) {
            return false;
        }

        if (! $this->track_stock) {
            return true;
        }

        return $this->variants->contains(fn (ProductVariant $variant): bool => $variant->availableQty() > 0);
    }

    public function inventoryUnits(): HasManyThrough
    {
        return $this->hasManyThrough(InventoryUnit::class, ProductVariant::class, 'product_id', 'product_variant_id');
    }
}
