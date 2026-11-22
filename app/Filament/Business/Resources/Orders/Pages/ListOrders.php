<?php

namespace App\Filament\Business\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Filament\Business\BusinessPanel;
use App\Filament\Business\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

/**
 * Live order screen (M7.1): the table polls every 10 s and a chime plays when
 * a new pending order arrives.
 */
class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    public int $lastPendingId = 0;

    public function mount(): void
    {
        parent::mount();

        $this->lastPendingId = (int) Order::query()->where('status', OrderStatus::Pending)->max('id');
    }

    public function checkForNewOrders(): void
    {
        $latest = (int) Order::query()->where('status', OrderStatus::Pending)->max('id');

        if ($latest > $this->lastPendingId) {
            $this->dispatch('new-app-order');
        }

        $this->lastPendingId = max($this->lastPendingId, $latest);
    }

    public function getHeader(): ?View
    {
        return view('filament.business.orders-chime');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggleIntake')
                ->label(fn (): string => BusinessPanel::business()->settings('orders_paused') ? __('Resume taking orders') : __('Pause new orders'))
                ->color(fn (): string => BusinessPanel::business()->settings('orders_paused') ? 'success' : 'gray')
                ->action(function (): void {
                    $business = BusinessPanel::business();
                    $business->updateSettings(['orders_paused' => ! $business->settings('orders_paused')]);
                }),
        ];
    }

    public function getTabs(): array
    {
        $inProgress = [OrderStatus::Accepted, OrderStatus::Preparing, OrderStatus::ReadyForPickup, OrderStatus::OutForDelivery];

        return [
            'needs_action' => Tab::make(__('Needs action'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::Pending))
                ->badge(fn (): int => Order::query()->where('status', OrderStatus::Pending)->count())
                ->badgeColor('warning'),
            'in_progress' => Tab::make(__('In progress'))
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', $inProgress))
                ->badge(fn (): int => Order::query()->whereIn('status', $inProgress)->count())
                ->badgeColor('gray'),
            'completed' => Tab::make(__('Completed'))->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::Completed)),
            'closed' => Tab::make(__('Rejected / cancelled'))->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [OrderStatus::Rejected, OrderStatus::Cancelled, OrderStatus::Expired])),
            'all' => Tab::make(__('All')),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return Order::query()->where('status', OrderStatus::Pending)->exists() ? 'needs_action' : 'in_progress';
    }

    /**
     * @return array<int, string>|null
     */
    public function tabEmptyState(): ?array
    {
        return match ($this->activeTab) {
            'needs_action' => [__('No orders waiting'), __('New app orders appear here with a chime.')],
            'in_progress' => [__('Nothing in progress'), __('Accepted orders stay here until they are completed.')],
            default => null,
        };
    }
}
