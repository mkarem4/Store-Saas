<?php

namespace App\Listeners;

use App\Contracts\PushNotifier;
use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Every order status change is pushed to the customer (§6.1, M8.7).
 */
class SendOrderStatusPush implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly PushNotifier $push) {}

    public function handle(OrderStatusChanged $event): void
    {
        $order = $event->order->loadMissing(['customer', 'business']);

        if ($order->customer === null || $event->status === OrderStatus::Pending) {
            return;
        }

        $locale = $order->customer->locale ?: config('app.locale');
        $previous = app()->getLocale();
        app()->setLocale($locale);

        try {
            $title = $order->business?->translated('name', $locale) ?? config('app.name');
            $body = match ($event->status) {
                OrderStatus::Accepted => __('Your order :number was accepted.', ['number' => $order->number]),
                OrderStatus::Preparing => __('Your order :number is being prepared.', ['number' => $order->number]),
                OrderStatus::ReadyForPickup => __('Your order :number is ready for pickup.', ['number' => $order->number]),
                OrderStatus::OutForDelivery => __('Your order :number is on its way.', ['number' => $order->number]),
                OrderStatus::Completed => __('Order :number completed. Thank you!', ['number' => $order->number]),
                OrderStatus::Rejected => __('Order :number was declined. You were not charged.', ['number' => $order->number]),
                OrderStatus::Expired => __('Order :number was not accepted in time. You were not charged.', ['number' => $order->number]),
                OrderStatus::Cancelled => __('Order :number was cancelled.', ['number' => $order->number]),
                default => $event->status->label(),
            };

            $this->push->send($order->customer, $title, $body, [
                'type' => 'order_status',
                'order_number' => $order->number,
                'status' => $event->status->value,
            ]);
        } finally {
            app()->setLocale($previous);
        }
    }
}
