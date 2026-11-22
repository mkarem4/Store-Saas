<?php

namespace App\Models;

use App\Enums\JewelryPricingMode;
use App\Enums\MakingType;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JewelryAttribute extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'business_id',
        'product_variant_id',
        'pricing_mode',
        'weight_g',
        'karat',
        'metal',
        'making_type',
        'making_value',
        'certificate_number',
    ];

    protected function casts(): array
    {
        return [
            'pricing_mode' => JewelryPricingMode::class,
            'making_type' => MakingType::class,
            'weight_g' => 'decimal:3',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function calculatedPriceCents(?int $businessId = null): int
    {
        $businessId ??= $this->business_id;

        $goldPrice = GoldPrice::priceFor($businessId, $this->karat);

        if ($goldPrice === null || $this->weight_g === null) {
            return (int) $this->variant->retail_price_cents;
        }

        $metalCents = (int) round((float) $this->weight_g * $goldPrice);

        $makingCents = match ($this->making_type) {
            MakingType::Percentage => (int) round($metalCents * ((float) $this->making_value / 10000)),
            default => (int) $this->making_value,
        };

        return $metalCents + $makingCents;
    }
}
