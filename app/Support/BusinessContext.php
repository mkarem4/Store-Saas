<?php

namespace App\Support;

use App\Models\Business;
use Closure;

final class BusinessContext
{
    private static ?int $businessId = null;

    public static function id(): ?int
    {
        if (self::$businessId !== null) {
            return self::$businessId;
        }

        if (app()->bound('filament') && filament()->getCurrentPanel()?->hasTenancy()) {
            $tenant = filament()->getTenant();

            if ($tenant instanceof Business) {
                return (int) $tenant->getKey();
            }
        }

        if (auth()->hasUser()) {
            $businessId = auth()->user()->business_id;

            return $businessId === null ? null : (int) $businessId;
        }

        return null;
    }

    public static function business(): ?Business
    {
        $businessId = self::id();

        return $businessId === null ? null : Business::query()->find($businessId);
    }

    public static function set(?int $businessId): void
    {
        self::$businessId = $businessId;
    }

    public static function forget(): void
    {
        self::$businessId = null;
    }

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function run(int $businessId, Closure $callback): mixed
    {
        $previous = self::$businessId;
        self::$businessId = $businessId;

        try {
            return $callback();
        } finally {
            self::$businessId = $previous;
        }
    }
}
