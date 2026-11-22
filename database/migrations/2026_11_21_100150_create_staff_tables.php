<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->timestamp('clock_in_at');
            $table->timestamp('clock_out_at')->nullable();
            $table->string('clock_in_ip', 45)->nullable();
            $table->string('clock_out_ip', 45)->nullable();
            $table->boolean('is_auto_closed')->default(false);
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'employee_id', 'clock_in_at'], 'time_entries_employee_date_index');
        });

        Schema::create('payroll_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 16)->default('draft');
            $table->unsignedBigInteger('gross_cents')->default(0);
            $table->unsignedBigInteger('commission_cents')->default(0);
            $table->unsignedBigInteger('deductions_cents')->default(0);
            $table->unsignedBigInteger('total_cents')->default(0);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'period_start', 'period_end']);
        });

        Schema::create('payslips', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('minutes_worked')->default(0);
            $table->unsignedBigInteger('base_cents')->default(0);
            $table->unsignedBigInteger('allowances_cents')->default(0);
            $table->unsignedBigInteger('deductions_cents')->default(0);
            $table->unsignedBigInteger('commission_cents')->default(0);
            $table->unsignedBigInteger('total_cents')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('time_entries');
    }
};
