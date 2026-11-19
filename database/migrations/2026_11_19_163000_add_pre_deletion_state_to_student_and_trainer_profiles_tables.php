<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('student_profiles') && ! Schema::hasColumn('student_profiles', 'pre_deletion_state')) {
            Schema::table('student_profiles', function (Blueprint $table) {
                $table->json('pre_deletion_state')->nullable()->after('deleted_at');
            });
        }

        if (Schema::hasTable('trainer_profiles') && ! Schema::hasColumn('trainer_profiles', 'pre_deletion_state')) {
            Schema::table('trainer_profiles', function (Blueprint $table) {
                $table->json('pre_deletion_state')->nullable()->after('deleted_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('student_profiles') && Schema::hasColumn('student_profiles', 'pre_deletion_state')) {
            Schema::table('student_profiles', function (Blueprint $table) {
                $table->dropColumn('pre_deletion_state');
            });
        }

        if (Schema::hasTable('trainer_profiles') && Schema::hasColumn('trainer_profiles', 'pre_deletion_state')) {
            Schema::table('trainer_profiles', function (Blueprint $table) {
                $table->dropColumn('pre_deletion_state');
            });
        }
    }
};
