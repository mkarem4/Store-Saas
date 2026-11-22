<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/*
 * Stores provisioned before wallet.manage and sell_wholesale existed have owner
 * roles without them, which hides Wallet, Withdrawals and Payout methods.
 */
return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('permissions:sync');
    }

    public function down(): void {}
};
