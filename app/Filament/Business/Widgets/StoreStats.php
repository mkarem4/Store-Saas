<?php

namespace App\Filament\Business\Widgets;

use App\Enums\BusinessTypeKey;
use App\Enums\InquiryStatus;
use App\Enums\OrderStatus;
use App\Filament\Business\BusinessPanel;
use App\Filament\Business\Pages\Reports;
use App\Filament\Business\Resources\Inquiries\InquiryResource;
use App\Filament\Business\Resources\Orders\OrderResource;
use App\Filament\Business\Resources\TimeEntries\TimeEntryResource;
use App\Models\Inquiry;
use App\Models\Order;
use App\Models\TimeEntry;
use App\Services\ReportService;
use App\Support\Money;
use App\Support\Permission;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StoreStats extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '30s';

    public static function canView(): bool
    {
        return BusinessPanel::can(Permission::ViewReports);
    }

    /** One row on wide screens however many cards this store type shows. */
    protected function getColumns(): int|array|null
    {
        return ['md' => 3, 'xl' => max(3, min(6, count($this->getCachedStats())))];
    }

    protected function getStats(): array
    {
        $business = BusinessPanel::business();
        $now = now($business->timezone);
        $reports = app(ReportService::class);
        $today = $reports->summary($business, $now, $now);
        $month = $reports->summary($business, $now->copy()->startOfMonth(), $now);
        $currency = $business->currency;

        // What needs doing comes first and links straight to it; figures follow.
        $stats = [];

        if (! BusinessPanel::isType([BusinessTypeKey::CarShowroom->value, BusinessTypeKey::CarRental->value]) && BusinessPanel::can(Permission::ManageOrders)) {
            $pending = Order::query()->where('status', OrderStatus::Pending)->count();
            $stats[] = Stat::make(__('Orders waiting'), $pending)
                ->color($pending > 0 ? 'warning' : 'gray')
                ->description($pending > 0 ? __('Accept or reject') : __('All caught up'))
                ->url(OrderResource::getUrl());
        }

        if (BusinessPanel::isType([BusinessTypeKey::CarShowroom->value, BusinessTypeKey::CarRental->value]) && BusinessPanel::can(Permission::ManageOrders)) {
            $newInquiries = Inquiry::query()->where('status', InquiryStatus::New)->count();
            $stats[] = Stat::make(__('New inquiries'), $newInquiries)
                ->color($newInquiries > 0 ? 'warning' : 'gray')
                ->description($newInquiries > 0 ? __('Call them back') : __('All caught up'))
                ->url(InquiryResource::getUrl());
        }

        if (! BusinessPanel::isType([BusinessTypeKey::CarShowroom->value, BusinessTypeKey::CarRental->value, BusinessTypeKey::Restaurant->value]) && BusinessPanel::can(Permission::ViewReports)) {
            $low = $reports->lowStock($business)->count();
            $stats[] = Stat::make(__('Low stock items'), $low)
                ->color($low > 0 ? 'danger' : 'gray')
                ->description($low > 0 ? __('See what to reorder') : __('Stock looks fine'))
                ->url(Reports::getUrl());
        }

        if (BusinessPanel::can(Permission::ManageAttendance)) {
            $toReview = TimeEntry::query()->where('is_auto_closed', true)->whereNull('reviewed_at')->count();

            if ($toReview > 0) {
                $stats[] = Stat::make(__('Shifts to review'), $toReview)
                    ->color('warning')
                    ->description(__('Closed automatically, check the hours'))
                    ->url(TimeEntryResource::getUrl());
            }
        }

        $stats[] = Stat::make(__('Sales today'), Money::format($today['revenue_cents'], $currency))
            ->description(trans_choice('{0} No sales yet today|{1} 1 sale|[2,*] :count sales', $today['sales_count'], ['count' => $today['sales_count']]));
        $stats[] = Stat::make(__('Net profit this month'), Money::format($month['net_profit_cents'], $currency))
            ->color($month['net_profit_cents'] >= 0 ? 'success' : 'danger')
            ->description(__('Revenue :revenue, expenses :expenses', ['revenue' => Money::format($month['revenue_cents'], $currency), 'expenses' => Money::format($month['expenses_cents'], $currency)]));

        $clockedIn = TimeEntry::query()->whereNull('clock_out_at')->count();
        $stats[] = Stat::make(__('Clocked in now'), $clockedIn);

        return $stats;
    }
}
