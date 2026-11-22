<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();
            $table->json('attributes')->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('is_available')->default(true);
            $table->boolean('track_stock')->default(true);
            $table->boolean('has_modifiers')->default(false);
            $table->unsignedSmallInteger('prep_time_minutes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'is_published', 'is_available']);
            $table->index(['business_id', 'sku']);
            $table->index(['business_id', 'barcode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
