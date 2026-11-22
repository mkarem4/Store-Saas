<?php

namespace App\Services;

use App\Models\Sale;

/**
 * Sequential per-store, per-day sale numbers: S-YYYYMMDD-0001.
 */
class SaleNumber
{
    public function next(int $businessId): string
    {
        $prefix = 'S-'.now()->format('Ymd').'-';

        $last = Sale::query()
            ->withoutGlobalScope('business')
            ->where('business_id', $businessId)
            ->where('number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('number')
            ->value('number');

        $sequence = $last === null ? 1 : ((int) substr((string) $last, strlen($prefix))) + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
