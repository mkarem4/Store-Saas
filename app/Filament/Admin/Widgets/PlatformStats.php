<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\BusinessStatus;
use App\Enums\OrderStatus;
use App\Enums\PayoutStatus;
use App\Enums\WalletTransactionType;
use App\Filament\Admin\Resources\Businesses\BusinessResource;
use App\Filament\Admin\Resources\PayoutRequests\PayoutRequestResource;
use App\Models\Business;
use App\Models\Order;
use App\Models\PayoutRequest;
use App\Models\Sale;
use App\Models\WalletTransaction;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected function getColumns(): int|array|null
    {
        return ['md' => 3, 'xl' => 5];
    }

    protected function getStats(): array
    {
        $monthStart = now()->startOfMonth();

        $sales = (int) Sale::query()->withoutGlobalScope('business')->where('sold_at', '>=', $monthStart)
            ->selectRaw('sum(total_cents - tax_cents) as revenue')->value('revenue');

        $commission = -(int) WalletTransaction::query()->withoutGlobalScope('business')
            ->whereIn('type', [WalletTransactionType::PlatformFee, WalletTransactionType::PlatformFeeReversal])
            ->where('created_at', '>=', $monthStart)
            ->sum('amount_cents');

        $openPayouts = PayoutRequest::query()->withoutGlobalScope('business')->whereIn('status', [PayoutStatus::Requested, PayoutStatus::Approved]);
        $openCount = (clone $openPayouts)->count();

        // Payout requests are the platform's to-do list, so they come first and link to the review screen.
        return [
            Stat::make(__('Payouts to review'), $openCount)
                ->description($openCount > 0 ? Money::format((int) (clone $openPayouts)->sum('amount_cents')) : __('All caught up'))
                ->color($openCount > 0 ? 'warning' : 'gray')
                ->url(PayoutRequestResource::getUrl()),
            Stat::make(__('Active stores'), Business::query()->where('status', BusinessStatus::Active)->count())
                ->description(__(':count total', ['count' => Business::query()->count()]))
                ->url(BusinessResource::getUrl()),
            Stat::make(__('App orders this month'), Order::query()->withoutGlobalScope('business')->where('placed_at', '>=', $monthStart)
                ->where('status', OrderStatus::Completed)->count()),
            Stat::make(__('Store sales this month'), Money::format($sales)),
            Stat::make(__('Commission this month'), Money::format($commission)),
        ];
    }
}
