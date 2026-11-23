<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use Stripe\StripeClient;

class StripeGateway implements PaymentGateway
{
    private ?StripeClient $client = null;

    public function __construct()
    {
        $secret = (string) config('services.stripe.secret');

        if ($secret !== '') {
            $this->client = new StripeClient($secret);
        }
    }

    public function isConfigured(): bool
    {
        return $this->client !== null;
    }

    public function authorize(Order $order, ?string $paymentMethodId = null): array
    {
        $payload = [
            'amount' => $order->total_cents,
            'currency' => strtolower($order->business?->currency ?? 'usd'),
            'capture_method' => 'manual',
            'automatic_payment_methods' => ['enabled' => true],
            'metadata' => [
                'order_id' => $order->id,
                'order_number' => $order->number,
                'business_id' => $order->business_id,
            ],
        ];

        if ($paymentMethodId !== null && $paymentMethodId !== '') {
            $payload['payment_method'] = $paymentMethodId;
            $payload['confirm'] = true;
            unset($payload['automatic_payment_methods']);
        }

        $intent = $this->client->paymentIntents->create($payload, [
            'idempotency_key' => 'order-'.$order->idempotency_key,
        ]);

        return [
            'intent_id' => $intent->id,
            'client_secret' => $intent->client_secret,
            'status' => $intent->status,
        ];
    }

    public function capture(Order $order): void
    {
        if (blank($order->payment_intent_id)) {
            return;
        }

        $this->client->paymentIntents->capture($order->payment_intent_id, [], [
            'idempotency_key' => 'capture-'.$order->payment_intent_id,
        ]);
    }

    public function void(Order $order): void
    {
        if (blank($order->payment_intent_id)) {
            return;
        }

        $this->client->paymentIntents->cancel($order->payment_intent_id, [], [
            'idempotency_key' => 'void-'.$order->payment_intent_id,
        ]);
    }

    public function refund(Order $order, int $amountCents): ?string
    {
        if (blank($order->payment_intent_id)) {
            return null;
        }

        $refund = $this->client->refunds->create([
            'payment_intent' => $order->payment_intent_id,
            'amount' => $amountCents,
        ], [
            'idempotency_key' => 'refund-'.$order->id.'-'.$amountCents.'-'.$order->refunded_cents,
        ]);

        return $refund->id;
    }

    public function feeFor(Order $order): ?int
    {
        if (blank($order->payment_intent_id)) {
            return null;
        }

        $intent = $this->client->paymentIntents->retrieve($order->payment_intent_id, [
            'expand' => ['latest_charge.balance_transaction'],
        ]);

        $balance = $intent->latest_charge->balance_transaction ?? null;

        return is_object($balance) && isset($balance->fee) ? (int) $balance->fee : null;
    }
}
