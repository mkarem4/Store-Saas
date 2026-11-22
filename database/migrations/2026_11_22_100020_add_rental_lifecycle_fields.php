<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rental_contracts', function (Blueprint $table): void {
            $table->string('payment_method', 16)->nullable()->after('paid_at');
            $table->timestamp('picked_up_at')->nullable()->after('payment_method');
            $table->timestamp('returned_at')->nullable()->after('picked_up_at');
            $table->unsignedBigInteger('deposit_returned_cents')->default(0)->after('deposit_cents');
            $table->string('extra_charges_note')->nullable()->after('extra_charges_cents');
        });
    }

    public function down(): void
    {
        Schema::table('rental_contracts', function (Blueprint $table): void {
            $table->dropColumn(['payment_method', 'picked_up_at', 'returned_at', 'deposit_returned_cents', 'extra_charges_note']);
        });
    }
};
