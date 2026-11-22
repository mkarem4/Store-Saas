<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec v1.3 (2026-11-22): regional tax, delivery by distance, geofenced
 * attendance, automatic gold prices, platform commission, store wallets and
 * payout requests. Payments settle into the platform account (no Connect).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table): void {
            $table->id();
            $table->char('country', 2);
            $table->string('state', 64)->nullable();
            $table->string('name')->nullable();
            $table->decimal('rate', 6, 3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['country', 'state']);
        });

        Schema::table('businesses', function (Blueprint $table): void {
            $table->decimal('latitude', 10, 7)->nullable()->after('country');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('attendance_mode', 16)->default('ip')->after('allowed_ips');
            $table->unsignedInteger('geofence_radius_m')->default(150)->after('attendance_mode');
        });

        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropColumn(['stripe_account_id', 'stripe_onboarding_complete']);
        });

        Schema::table('customer_addresses', function (Blueprint $table): void {
            $table->decimal('latitude', 10, 7)->nullable()->after('postal_code');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->decimal('delivery_distance_km', 8, 2)->nullable()->after('delivery_fee_cents');
            $table->decimal('tax_rate', 6, 3)->default(0)->after('discount_cents');
            $table->unsignedBigInteger('platform_fee_cents')->default(0)->after('cogs_cents');
            $table->unsignedBigInteger('gateway_fee_cents')->default(0)->after('platform_fee_cents');
            $table->bigInteger('store_payout_cents')->default(0)->after('gateway_fee_cents');
            $table->unsignedBigInteger('refunded_cents')->default(0)->after('store_payout_cents');
            $table->timestamp('cancelled_at')->nullable()->after('completed_at');
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->decimal('tax_rate', 6, 3)->default(0)->after('discount_cents');
        });

        Schema::table('refunds', function (Blueprint $table): void {
            $table->unsignedBigInteger('tax_cents')->default(0)->after('amount_cents');
            $table->unsignedBigInteger('cogs_cents')->default(0)->after('tax_cents');
        });

        Schema::table('time_entries', function (Blueprint $table): void {
            $table->decimal('clock_in_lat', 10, 7)->nullable()->after('clock_in_ip');
            $table->decimal('clock_in_lng', 10, 7)->nullable()->after('clock_in_lat');
            $table->decimal('clock_out_lat', 10, 7)->nullable()->after('clock_out_ip');
            $table->decimal('clock_out_lng', 10, 7)->nullable()->after('clock_out_lat');
            $table->string('review_reason')->nullable()->after('reviewed_by');
        });

        Schema::table('gold_prices', function (Blueprint $table): void {
            $table->string('source', 16)->default('manual')->after('price_per_gram_cents');
        });

        Schema::create('business_wallets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();
            $table->bigInteger('pending_cents')->default(0);
            $table->bigInteger('available_cents')->default(0);
            $table->bigInteger('lifetime_earnings_cents')->default(0);
            $table->bigInteger('lifetime_payouts_cents')->default(0);
            $table->timestamps();
        });

        Schema::create('payout_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24)->default('bank_transfer');
            $table->string('label');
            $table->text('details');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('payout_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payout_method_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->string('status', 16)->default('requested');
            $table->json('method_snapshot')->nullable();
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->text('admin_note')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('wallet_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24);
            $table->bigInteger('amount_cents');
            $table->string('status', 16)->default('pending');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payout_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description')->nullable();
            $table->timestamp('available_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'created_at']);
            $table->index(['status', 'available_at']);
        });

        Schema::create('webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('gateway', 24);
            $table->string('event_id');
            $table->string('type');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('payout_requests');
        Schema::dropIfExists('payout_methods');
        Schema::dropIfExists('business_wallets');

        Schema::table('gold_prices', fn (Blueprint $table) => $table->dropColumn('source'));
        Schema::table('time_entries', fn (Blueprint $table) => $table->dropColumn([
            'clock_in_lat', 'clock_in_lng', 'clock_out_lat', 'clock_out_lng', 'review_reason',
        ]));
        Schema::table('refunds', fn (Blueprint $table) => $table->dropColumn(['tax_cents', 'cogs_cents']));
        Schema::table('sales', fn (Blueprint $table) => $table->dropColumn('tax_rate'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn([
            'delivery_distance_km', 'tax_rate', 'platform_fee_cents', 'gateway_fee_cents',
            'store_payout_cents', 'refunded_cents', 'cancelled_at',
        ]));
        Schema::table('customer_addresses', fn (Blueprint $table) => $table->dropColumn(['latitude', 'longitude']));

        Schema::table('businesses', function (Blueprint $table): void {
            $table->string('stripe_account_id')->nullable();
            $table->boolean('stripe_onboarding_complete')->default(false);
            $table->dropColumn(['latitude', 'longitude', 'attendance_mode', 'geofence_radius_m']);
        });

        Schema::dropIfExists('tax_rates');
    }
};
