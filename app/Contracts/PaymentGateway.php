<?php

namespace App\Contracts;

use App\Models\Order;

interface PaymentGateway
{
    /**
     * Create an authorization (manual capture) for an order.
     *
     * @return array{intent_id: string, client_secret: string|null, status: string}
     */
    public function authorize(Order $order, ?string $paymentMethodId = null): array;

    public function capture(Order $order): void;

    public function void(Order $order): void;

    public function refund(Order $order, int $amountCents): void;

    public function isConfigured(): bool;
}
