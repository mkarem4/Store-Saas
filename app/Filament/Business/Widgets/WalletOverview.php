<?php

namespace App\Filament\Business\Widgets;

use App\Enums\PayoutStatus;
use App\Filament\Business\BusinessPanel;
use App\Models\PayoutRequest;
use App\Services\WalletService;
use App\Support\Money;
use App\Support\PlatformConfig;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class WalletOverview extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected function getStats(): array
    {
        $business = BusinessPanel::business();
        $wallet = app(WalletService::class)->walletFor($business)->refresh();
        $currency = $business->currency;
        $inTransit = (int) PayoutRequest::query()->whereIn('status', [PayoutStatus::Requested, PayoutStatus::Approved])->sum('amount_cents');

        return [
            Stat::make(__('Available to withdraw'), Money::format($wallet->available_cents, $currency))
                ->color($wallet->available_cents < 0 ? 'danger' : 'success')
                ->description($wallet->available_cents < 0 ? __('Negative: refunds after a payout are taken from your next earnings.') : null),
            Stat::make(__('Pending'), Money::format($wallet->pending_cents, $currency))
                ->description(__('Released :days days after each order is completed.', ['days' => PlatformConfig::walletHoldDays()])),
            Stat::make(__('Withdrawals in progress'), Money::format($inTransit, $currency)),
            Stat::make(__('Lifetime net earnings'), Money::format($wallet->lifetime_earnings_cents, $currency))
                ->description(__('Paid out: :amount', ['amount' => Money::format($wallet->lifetime_payouts_cents, $currency)])),
        ];
    }
}
