<?php

namespace App\Models;

use App\Enums\GoldPriceSource;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoldPrice extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'business_id',
        'karat',
        'price_per_gram_cents',
        'source',
        'effective_date',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'source' => GoldPriceSource::class,
        ];
    }

    public static function priceFor(int $businessId, ?string $karat, ?string $date = null): ?int
    {
        if (blank($karat)) {
            return null;
        }

        $date ??= now()->toDateString();

        return self::query()
            ->withoutGlobalScope('business')
            ->where('business_id', $businessId)
            ->where('karat', $karat)
            ->whereDate('effective_date', '<=', $date)
            ->orderByDesc('effective_date')
            ->value('price_per_gram_cents');
    }
}
