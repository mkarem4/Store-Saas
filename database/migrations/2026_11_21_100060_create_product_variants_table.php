<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->json('name');
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();
            $table->string('unit', 16)->default('piece');
            $table->unsignedBigInteger('cost_price_cents')->default(0);
            $table->unsignedBigInteger('wholesale_price_cents')->default(0);
            $table->unsignedBigInteger('retail_price_cents')->default(0);
            $table->decimal('stock_qty', 12, 3)->default(0);
            $table->decimal('reserved_qty', 12, 3)->default(0);
            $table->decimal('low_stock_threshold', 12, 3)->nullable();
            $table->date('expiry_date')->nullable();
            $table->boolean('is_default')->default(false);
            $table->json('attributes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['business_id', 'sku']);
            $table->unique(['business_id', 'barcode']);
            $table->index(['business_id', 'product_id']);
            $table->index(['business_id', 'expiry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
