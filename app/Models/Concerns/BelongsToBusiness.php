<?php

namespace App\Models\Concerns;

use App\Models\Business;
use App\Support\BusinessContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToBusiness
{
    public static function bootBelongsToBusiness(): void
    {
        static::addGlobalScope('business', function (Builder $builder): void {
            $businessId = BusinessContext::id();

            if ($businessId === null) {
                return;
            }

            $builder->where(
                $builder->getModel()->qualifyColumn('business_id'),
                $businessId,
            );
        });

        static::creating(function (self $model): void {
            if ($model->business_id === null) {
                $model->business_id = BusinessContext::id();
            }
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
