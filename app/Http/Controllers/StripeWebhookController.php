<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\WebhookEvent;
use App\Services\OrderService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    /**
     * Receive Stripe events (M9.6): verified signature, processed once per
     * event id, order state always re-read from the database.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = (string) $request->header('Stripe-Signature', '');
        $secret = (string) config('services.stripe.webhook_secret');

        if ($secret === '') {
            if (! app()->environment(['local', 'testing'])) {
                return response()->json(['error' => 'Stripe webhook secret is not configured.'], 503);
            }

            $event = Event::constructFrom(json_decode($payload, true) ?? []);
        } else {
            try {
                $event = Webhook::constructEvent($payload, $sigHeader, $secret);
            } catch (SignatureVerificationException|\UnexpectedValueException) {
                return response()->json(['error' => 'Invalid signature.'], 400);
            }
        }

        if (blank($event->id ?? null)) {
            return response()->json(['error' => 'Invalid event.'], 400);
        }

        try {
            $record = WebhookEvent::query()->create([
                'gateway' => 'stripe',
                'event_id' => $event->id,
                'type' => (string) $event->type,
            ]);
        } catch (UniqueConstraintViolationException) {
            $record = WebhookEvent::query()->where('gateway', 'stripe')->where('event_id', $event->id)->first();

            if ($record?->processed_at !== null) {
                return response()->json(['received' => true, 'duplicate' => true]);
            }
        }

        try {
            DB::transaction(function () use ($event): void {
                match ($event->type) {
                    'payment_intent.amount_capturable_updated' => $this->withOrder($event, fn (Order $order) => $this->orders->markAuthorized($order)),
                    'payment_intent.succeeded' => $this->withOrder($event, fn (Order $order) => $this->handlePaymentSucceeded($order, $event)),
                    'payment_intent.payment_failed' => $this->withOrder($event, fn (Order $order) => $this->orders->markPaymentFailed($order)),
                    default => null,
                };
            });
        } catch (\Throwable $exception) {
            Log::error('Stripe webhook processing failed.', [
                'event_id' => $event->id,
                'type' => $event->type,
                'message' => $exception->getMessage(),
            ]);

            // Not marked processed: Stripe retries and we try again.
            return response()->json(['error' => 'Processing failed.'], 500);
        }

        $record?->update(['processed_at' => now()]);

        return response()->json(['received' => true]);
    }

    private function withOrder(object $event, \Closure $callback): void
    {
        $intent = $event->data->object;
        $orderId = $intent->metadata['order_id'] ?? null;

        $query = Order::query()->withoutGlobalScope('business');

        $order = $orderId !== null
            ? $query->where('id', $orderId)->where('payment_intent_id', $intent->id)->first()
            : $query->where('payment_intent_id', $intent->id)->first();

        if ($order !== null) {
            $callback($order);
        }
    }

    private function handlePaymentSucceeded(Order $order, object $event): void
    {
        if ($order->status !== OrderStatus::Pending) {
            return;
        }

        // Funds were captured outside the accept flow after the window
        // closed: give the money back.
        if ($order->isExpired()) {
            $this->orders->expireWithRefund($order);

            return;
        }

        $this->orders->markCaptured($order);
    }
}
