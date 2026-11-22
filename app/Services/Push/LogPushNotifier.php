<?php

namespace App\Services\Push;

use App\Contracts\PushNotifier;
use App\Models\Customer;
use Illuminate\Support\Facades\Log;

/**
 * Used until Firebase credentials are configured.
 */
class LogPushNotifier implements PushNotifier
{
    public function send(Customer $customer, string $title, string $body, array $data = []): int
    {
        Log::info('Push (not sent, FCM not configured)', ['customer_id' => $customer->id, 'title' => $title, 'data' => $data]);

        return 0;
    }
}
