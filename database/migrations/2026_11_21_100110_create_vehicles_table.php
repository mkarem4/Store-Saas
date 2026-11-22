<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('vin');
            $table->string('make');
            $table->string('model');
            $table->unsignedSmallInteger('year');
            $table->string('color')->nullable();
            $table->unsignedInteger('mileage')->nullable();
            $table->string('plate_number')->nullable();
            $table->unsignedBigInteger('purchase_price_cents')->default(0);
            $table->unsignedBigInteger('listing_price_cents')->default(0);
            $table->unsignedBigInteger('sold_price_cents')->nullable();
            $table->string('status', 24)->default('available');
            $table->string('purpose', 16)->default('sale');
            $table->foreignId('buyer_client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('sold_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('sold_at')->nullable();
            $table->json('attributes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'vin']);
            $table->index(['business_id', 'status', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
