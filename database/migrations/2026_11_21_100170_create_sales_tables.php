<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('number');
            $table->string('channel', 16)->default('pos');
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('subtotal_cents')->default(0);
            $table->unsignedBigInteger('discount_cents')->default(0);
            $table->unsignedBigInteger('tax_cents')->default(0);
            $table->unsignedBigInteger('total_cents')->default(0);
            $table->unsignedBigInteger('cogs_cents')->default(0);
            $table->string('status', 24)->default('completed');
            $table->timestamp('sold_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'number']);
            $table->index(['business_id', 'sold_at']);
            $table->index(['business_id', 'channel']);
            $table->index('order_id');
        });

        Schema::create('sale_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->decimal('qty', 12, 3);
            $table->string('unit', 16)->default('piece');
            $table->unsignedBigInteger('unit_price_cents');
            $table->unsignedBigInteger('unit_cost_cents')->default(0);
            $table->unsignedBigInteger('discount_cents')->default(0);
            $table->unsignedBigInteger('tax_cents')->default(0);
            $table->unsignedBigInteger('total_cents');
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('sale_item_modifiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modifier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('group_name');
            $table->string('name');
            $table->bigInteger('price_delta_cents')->default(0);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->morphs('payable');
            $table->string('method', 16);
            $table->string('gateway', 24)->nullable();
            $table->string('gateway_ref')->nullable();
            $table->string('status', 24)->default('captured');
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedBigInteger('fee_cents')->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table->index(['gateway', 'gateway_ref']);
        });

        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('amount_cents');
            $table->string('reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('refunded_at');
            $table->string('gateway_ref')->nullable();
            $table->string('status', 24)->default('completed');
            $table->json('items')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'refunded_at']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('sale_item_modifiers');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
