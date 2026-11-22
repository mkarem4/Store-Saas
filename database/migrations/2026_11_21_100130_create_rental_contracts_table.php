<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_contracts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('contract_number');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('rate_type', 16)->default('daily');
            $table->unsignedBigInteger('rate_cents');
            $table->unsignedBigInteger('deposit_cents')->default(0);
            $table->unsignedInteger('odometer_out')->nullable();
            $table->unsignedInteger('odometer_in')->nullable();
            $table->unsignedBigInteger('extra_charges_cents')->default(0);
            $table->unsignedBigInteger('total_cents');
            $table->string('status', 24)->default('active');
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['business_id', 'contract_number']);
            $table->index(['business_id', 'vehicle_id', 'starts_at', 'ends_at'], 'rental_contracts_vehicle_dates_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_contracts');
    }
};
