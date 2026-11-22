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

    /**
     * Refund part or all of a captured payment. Returns the gateway refund id.
     */
    public function refund(Order $order, int $amountCents): ?string;

    /**
     * Actual processing fee charged by the gateway for the captured payment
     * (D26: borne by the store). Null when not known yet.
     */
    public function feeFor(Order $order): ?int;

    public function isConfigured(): bool;
}
