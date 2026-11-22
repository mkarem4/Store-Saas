<?php

namespace App\Contracts;

use App\Models\Customer;

interface PushNotifier
{
    /**
     * Send a push to every registered device of the customer.
     *
     * @param  array<string, string>  $data
     */
    public function send(Customer $customer, string $title, string $body, array $data = []): int;
}
