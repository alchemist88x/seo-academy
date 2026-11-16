<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('password');
            $table->string('phone', 50)->nullable()->after('is_active');
            $table->timestamp('suspended_at')->nullable()->after('phone');
        });

        Schema::table('academy_users', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('role');
            $table->timestamp('suspended_at')->nullable()->after('status');
            $table->timestamp('updated_at')->nullable()->after('created_at');

            $table->index(['academy_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('academy_users', function (Blueprint $table) {
            $table->dropIndex(['academy_id', 'status']);
            $table->dropColumn(['status', 'suspended_at', 'updated_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'phone', 'suspended_at']);
        });
    }
};
