<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            if (! Schema::hasColumn('enrollments', 'last_active_lesson_id')) {
                $table->foreignUuid('last_active_lesson_id')
                    ->nullable()
                    ->after('course_id')
                    ->constrained('lessons')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('enrollments', 'last_active_at')) {
                $table->timestamp('last_active_at')
                    ->nullable()
                    ->after('last_active_lesson_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            if (Schema::hasColumn('enrollments', 'last_active_lesson_id')) {
                $table->dropConstrainedForeignId('last_active_lesson_id');
            }

            if (Schema::hasColumn('enrollments', 'last_active_at')) {
                $table->dropColumn('last_active_at');
            }
        });
    }
};
