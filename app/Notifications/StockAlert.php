<?php

namespace App\Notifications;

use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Daily low-stock / expiry alert for the store owner (M2.4, D12).
 */
class StockAlert extends Notification
{
    use Queueable;

    /**
     * @param  array<int, string>  $lowStock
     * @param  array<int, string>  $expiring
     */
    public function __construct(public readonly array $lowStock, public readonly array $expiring, public readonly string $productsUrl) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $lines = [];

        if ($this->lowStock !== []) {
            $lines[] = __('Low stock: :items', ['items' => implode('، ', array_slice($this->lowStock, 0, 8))]).(count($this->lowStock) > 8 ? ' …' : '');
        }

        if ($this->expiring !== []) {
            $lines[] = __('Expiring within 30 days: :items', ['items' => implode('، ', array_slice($this->expiring, 0, 8))]).(count($this->expiring) > 8 ? ' …' : '');
        }

        return FilamentNotification::make()
            ->title(trans_choice('{1} 1 item needs attention|[2,*] :count items need attention', count($this->lowStock) + count($this->expiring), ['count' => count($this->lowStock) + count($this->expiring)]))
            ->body(implode("\n", $lines))
            ->warning()
            ->actions([Action::make('view')->label(__('View products'))->url($this->productsUrl)])
            ->getDatabaseMessage();
    }
}
