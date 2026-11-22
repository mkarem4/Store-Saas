<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_type_id')->constrained()->restrictOnDelete();
            $table->json('name');
            $table->string('slug')->unique();
            $table->json('description')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('address_line')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country', 2)->default('US');
            $table->char('currency', 3)->default('USD');
            $table->unsignedTinyInteger('currency_decimals')->default(2);
            $table->string('timezone')->default('America/New_York');
            $table->string('status')->default('active');
            $table->json('allowed_ips')->nullable();
            $table->string('stripe_account_id')->nullable();
            $table->boolean('stripe_onboarding_complete')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'business_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
