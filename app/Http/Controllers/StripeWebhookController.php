<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    /**
     * Receive and process Stripe webhook events.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature', '');
        $secret = (string) config('services.stripe.webhook_secret');

        if ($secret === '') {
            if (! app()->environment(['local', 'testing'])) {
                return response()->json(['error' => 'Stripe webhook secret is not configured.'], 503);
            }

            $event = Webhook::constructEventWithoutVerification($payload);
        } else {
            try {
                $event = Webhook::constructEvent($payload, $sigHeader, $secret);
            } catch (SignatureVerificationException $exception) {
                return response()->json(['error' => 'Invalid signature.'], 400);
            }
        }

        match ($event->type) {
            'payment_intent.succeeded' => $this->handlePaymentSucceeded($event),
            default => null,
        };

        return response()->json(['received' => true]);
    }

    private function handlePaymentSucceeded(object $event): void
    {
        $intent = $event->data->object;
        $orderId = $intent->metadata['order_id'] ?? null;

        $order = $orderId !== null
            ? Order::query()->withoutGlobalScope('business')->find($orderId)
            : Order::query()->withoutGlobalScope('business')->where('payment_intent_id', $intent->id)->first();

        if ($order === null || $order->status !== OrderStatus::Pending) {
            return;
        }

        if ($order->isExpired() && $order->payment_intent_id === $intent->id) {
            $this->orders->expireWithRefund($order);

            return;
        }

        $this->orders->markCaptured($order);
    }
}
