<?php

namespace App\Console\Commands;

use App\Enums\BusinessStatus;
use App\Enums\BusinessTypeKey;
use App\Models\Business;
use App\Services\Gold\GoldPriceService;
use Illuminate\Console\Command;

class FetchGoldPricesCommand extends Command
{
    protected $signature = 'gold:fetch';

    protected $description = 'Fetch today\'s gold gram prices for every active jewelry store';

    public function handle(GoldPriceService $prices): int
    {
        $updated = 0;

        Business::query()
            ->where('status', BusinessStatus::Active)
            ->whereHas('businessType', fn ($query) => $query->where('key', BusinessTypeKey::Jewelry->value))
            ->each(function (Business $business) use ($prices, &$updated): void {
                if ($prices->refresh($business) !== []) {
                    $updated++;
                }
            });

        $this->info("Updated gold prices for {$updated} store(s).");

        return self::SUCCESS;
    }
}
