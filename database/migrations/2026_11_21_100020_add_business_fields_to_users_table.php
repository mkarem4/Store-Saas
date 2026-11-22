<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('business_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('role')->default('staff')->after('password');
            $table->string('locale', 5)->default('en')->after('role');
            $table->boolean('is_active')->default(true)->after('locale');
            $table->boolean('must_change_password')->default(false)->after('is_active');
            $table->timestamp('last_login_at')->nullable()->after('must_change_password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('business_id');
            $table->dropColumn(['role', 'locale', 'is_active', 'must_change_password', 'last_login_at']);
        });
    }
};
