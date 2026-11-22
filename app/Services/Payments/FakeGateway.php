<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use Illuminate\Support\Str;

/**
 * Local development gateway that simulates Stripe's manual capture flow.
 */
class FakeGateway implements PaymentGateway
{
    public function isConfigured(): bool
    {
        return true;
    }

    public function authorize(Order $order, ?string $paymentMethodId = null): array
    {
        return [
            'intent_id' => 'pi_fake_'.Str::lower(Str::random(24)),
            'client_secret' => 'pi_fake_secret_'.Str::lower(Str::random(24)),
            'status' => 'requires_capture',
        ];
    }

    public function capture(Order $order): void
    {
        //
    }

    public function void(Order $order): void
    {
        //
    }

    public function refund(Order $order, int $amountCents): ?string
    {
        return 're_fake_'.Str::lower(Str::random(24));
    }

    /**
     * Mirrors Stripe's US card pricing (2.9% + 30¢) so wallet math is realistic.
     */
    public function feeFor(Order $order): ?int
    {
        return (int) round($order->total_cents * 0.029) + 30;
    }
}
