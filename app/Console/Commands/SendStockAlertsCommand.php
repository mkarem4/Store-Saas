<?php

namespace App\Console\Commands;

use App\Enums\BusinessStatus;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\ProductVariant;
use App\Models\User;
use App\Notifications\StockAlert;
use App\Services\ReportService;
use Illuminate\Console\Command;

class SendStockAlertsCommand extends Command
{
    protected $signature = 'stock:alerts';

    protected $description = 'Notify store owners about low-stock and soon-to-expire items';

    public function handle(ReportService $reports): int
    {
        $sent = 0;

        Business::query()->where('status', BusinessStatus::Active)->each(function (Business $business) use ($reports, &$sent): void {
            $low = $reports->lowStock($business)->map(fn (ProductVariant $variant): string => $variant->displayName())->all();
            $expiring = ProductVariant::query()
                ->withoutGlobalScope('business')
                ->where('business_id', $business->id)
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '<=', now()->addDays(30))
                ->where('stock_qty', '>', 0)
                ->with('product')
                ->get()
                ->map(fn (ProductVariant $variant): string => $variant->displayName().' ('.$variant->expiry_date->toDateString().')')
                ->all();

            if ($low === [] && $expiring === []) {
                return;
            }

            $owners = User::query()->where('business_id', $business->id)->where('role', UserRole::BusinessOwner)->where('is_active', true)->get();

            foreach ($owners as $owner) {
                $owner->notify(new StockAlert($low, $expiring, url("/business/{$business->slug}/products?tableFilters[low_stock][isActive]=1")));
                $sent++;
            }
        });

        $this->info("Sent {$sent} stock alert(s).");

        return self::SUCCESS;
    }
}
