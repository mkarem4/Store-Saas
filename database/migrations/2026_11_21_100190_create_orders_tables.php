<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('number');
            $table->string('fulfillment', 16);
            $table->string('status', 24)->default('pending');
            $table->string('payment_status', 24)->default('pending');
            $table->unsignedBigInteger('subtotal_cents')->default(0);
            $table->unsignedBigInteger('delivery_fee_cents')->default(0);
            $table->unsignedBigInteger('discount_cents')->default(0);
            $table->unsignedBigInteger('tax_cents')->default(0);
            $table->unsignedBigInteger('total_cents')->default(0);
            $table->unsignedBigInteger('cogs_cents')->default(0);
            $table->string('payment_intent_id')->nullable()->index();
            $table->string('idempotency_key')->unique();
            $table->json('delivery_address')->nullable();
            $table->text('notes')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->unsignedBigInteger('sale_id')->nullable()->index();
            $table->unsignedBigInteger('driver_id')->nullable()->index();
            $table->timestamp('placed_at');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'number']);
            $table->index(['business_id', 'status', 'placed_at']);
            $table->index(['customer_id', 'placed_at']);
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->decimal('qty', 12, 3);
            $table->string('unit', 16)->default('piece');
            $table->unsignedBigInteger('unit_price_cents');
            $table->unsignedBigInteger('unit_cost_cents')->default(0);
            $table->unsignedBigInteger('total_cents');
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('order_item_modifiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modifier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('group_name');
            $table->string('name');
            $table->bigInteger('price_delta_cents')->default(0);
            $table->timestamps();
        });

        Schema::create('order_status_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('status', 24);
            $table->string('note')->nullable();
            $table->string('changed_by_type', 32)->nullable();
            $table->unsignedBigInteger('changed_by_id')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
        });

        Schema::create('inquiries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 16)->default('general');
            $table->string('name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->text('message')->nullable();
            $table->string('status', 16)->default('new');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiries');
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('order_item_modifiers');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
