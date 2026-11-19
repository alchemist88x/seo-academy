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
        if (Schema::hasTable('student_profiles') && ! Schema::hasColumn('student_profiles', 'deleted_at')) {
            Schema::table('student_profiles', function (Blueprint $table) {
                $table->softDeletes()->after('notes');
            });
        }

        if (Schema::hasTable('trainer_profiles') && ! Schema::hasColumn('trainer_profiles', 'deleted_at')) {
            Schema::table('trainer_profiles', function (Blueprint $table) {
                $table->softDeletes()->after('active');
            });
        }

        if (Schema::hasTable('courses') && ! Schema::hasColumn('courses', 'deleted_at')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->softDeletes()->after('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('student_profiles') && Schema::hasColumn('student_profiles', 'deleted_at')) {
            Schema::table('student_profiles', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasTable('trainer_profiles') && Schema::hasColumn('trainer_profiles', 'deleted_at')) {
            Schema::table('trainer_profiles', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasTable('courses') && Schema::hasColumn('courses', 'deleted_at')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
