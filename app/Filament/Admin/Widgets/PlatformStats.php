<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\BusinessStatus;
use App\Enums\OrderStatus;
use App\Enums\PayoutStatus;
use App\Enums\WalletTransactionType;
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

        return [
            Stat::make(__('Active stores'), Business::query()->where('status', BusinessStatus::Active)->count())
                ->description(__(':count total', ['count' => Business::query()->count()])),
            Stat::make(__('App orders this month'), Order::query()->withoutGlobalScope('business')->where('placed_at', '>=', $monthStart)
                ->where('status', OrderStatus::Completed)->count()),
            Stat::make(__('Store sales this month'), Money::format($sales)),
            Stat::make(__('Commission this month'), Money::format($commission)),
            Stat::make(__('Open payout requests'), (clone $openPayouts)->count())
                ->description(Money::format((int) (clone $openPayouts)->sum('amount_cents')))
                ->color('warning'),
        ];
    }
}
