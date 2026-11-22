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

        $accountId = $order->business?->stripe_account_id;

        if ($accountId !== null && $accountId !== '') {
            $payload['transfer_data'] = ['destination' => $accountId];
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

        $this->client->paymentIntents->capture($order->payment_intent_id);
    }

    public function void(Order $order): void
    {
        if (blank($order->payment_intent_id)) {
            return;
        }

        $this->client->paymentIntents->cancel($order->payment_intent_id);
    }

    public function refund(Order $order, int $amountCents): void
    {
        if (blank($order->payment_intent_id)) {
            return;
        }

        $this->client->refunds->create([
            'payment_intent' => $order->payment_intent_id,
            'amount' => $amountCents,
        ]);
    }
}
