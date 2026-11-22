<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jewelry_attributes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('pricing_mode', 16)->default('fixed');
            $table->decimal('weight_g', 10, 3)->nullable();
            $table->string('karat', 8)->nullable();
            $table->string('metal', 32)->nullable();
            $table->string('making_type', 16)->default('fixed');
            $table->unsignedBigInteger('making_value')->default(0);
            $table->string('certificate_number')->nullable();
            $table->timestamps();
        });

        Schema::create('gold_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('karat', 8);
            $table->unsignedBigInteger('price_per_gram_cents');
            $table->date('effective_date');
            $table->timestamps();

            $table->unique(['business_id', 'karat', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gold_prices');
        Schema::dropIfExists('jewelry_attributes');
    }
};
