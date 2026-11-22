<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address_line')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->nullable();
            $table->timestamp('received_at');
            $table->unsignedBigInteger('total_cost_cents')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'received_at']);
        });

        Schema::create('purchase_receipt_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->decimal('qty', 12, 3);
            $table->unsignedBigInteger('unit_cost_cents');
            $table->unsignedBigInteger('total_cost_cents');
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24);
            $table->decimal('qty', 12, 3);
            $table->unsignedBigInteger('unit_cost_cents')->nullable();
            $table->nullableMorphs('reference');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['business_id', 'product_variant_id', 'occurred_at'], 'stock_movements_variant_date_index');
        });

        Schema::create('inventory_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->string('serial_number');
            $table->string('status', 24)->default('in_stock');
            $table->foreignId('purchase_receipt_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sale_item_id')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'serial_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_units');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('purchase_receipt_items');
        Schema::dropIfExists('purchase_receipts');
        Schema::dropIfExists('suppliers');
    }
};
