<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Console\Command;

class ExpirePendingOrdersCommand extends Command
{
    protected $signature = 'orders:expire';

    protected $description = 'Expire pending orders whose acceptance window has passed, voiding the payment and releasing stock';

    public function handle(OrderService $orders): int
    {
        $expired = 0;

        $ids = Order::query()
            ->withoutGlobalScope('business')
            ->where('status', OrderStatus::Pending)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->pluck('id');

        foreach ($ids as $id) {
            $order = Order::query()->withoutGlobalScope('business')->findOrFail($id);
            $orders->expire($order);
            $expired++;
        }

        $this->info("Expired {$expired} pending order(s).");

        return self::SUCCESS;
    }
}
