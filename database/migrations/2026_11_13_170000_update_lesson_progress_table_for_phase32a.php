<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add enrollment_id and index on user_id
        Schema::table('lesson_progress', function (Blueprint $table) {
            if (! Schema::hasColumn('lesson_progress', 'enrollment_id')) {
                $table->foreignUuid('enrollment_id')
                    ->nullable()
                    ->after('academy_id')
                    ->constrained('enrollments')
                    ->cascadeOnDelete();
            }

            // Ensure index on user_id exists before dropping unique(user_id, lesson_id)
            // for MySQL InnoDB foreign key constraint compatibility
            $table->index('user_id', 'lesson_progress_user_id_index');
        });

        // 2. Backfill existing progress records where unambiguous
        $this->backfillEnrollmentAssociations();

        // 3. Drop old unique(user_id, lesson_id) and add new unique(enrollment_id, lesson_id)
        Schema::table('lesson_progress', function (Blueprint $table) {
            $table->dropUnique('lesson_progress_user_id_lesson_id_unique');
            $table->unique(['enrollment_id', 'lesson_id'], 'lesson_progress_enrollment_id_lesson_id_unique');
        });
    }

    public function down(): void
    {
        $isMysql = DB::getDriverName() === 'mysql';

        Schema::table('lesson_progress', function (Blueprint $table) use ($isMysql) {
            if ($isMysql) {
                try {
                    $table->dropForeign(['enrollment_id']);
                } catch (Throwable) {
                }
            }

            try {
                $table->dropUnique('lesson_progress_enrollment_id_lesson_id_unique');
            } catch (Throwable) {
            }

            try {
                $table->unique(['user_id', 'lesson_id'], 'lesson_progress_user_id_lesson_id_unique');
            } catch (Throwable) {
            }

            try {
                $table->dropIndex('lesson_progress_user_id_index');
            } catch (Throwable) {
            }
        });

        Schema::table('lesson_progress', function (Blueprint $table) use ($isMysql) {
            if (Schema::hasColumn('lesson_progress', 'enrollment_id')) {
                if ($isMysql) {
                    $table->dropColumn('enrollment_id');
                } else {
                    $table->dropConstrainedForeignId('enrollment_id');
                }
            }
        });
    }

    private function backfillEnrollmentAssociations(): void
    {
        if (! Schema::hasTable('lesson_progress') || ! Schema::hasTable('lessons') || ! Schema::hasTable('enrollments')) {
            return;
        }

        $records = DB::table('lesson_progress')
            ->whereNull('enrollment_id')
            ->get();

        foreach ($records as $record) {
            $courseId = DB::table('lessons')
                ->join('modules', 'lessons.module_id', '=', 'modules.id')
                ->where('lessons.id', $record->lesson_id)
                ->value('modules.course_id');

            if (! $courseId) {
                continue;
            }

            $profileIds = DB::table('student_profiles')
                ->where('user_id', $record->user_id)
                ->pluck('id');

            if ($profileIds->isEmpty()) {
                continue;
            }

            $enrollments = DB::table('enrollments')
                ->whereIn('student_profile_id', $profileIds)
                ->where('course_id', $courseId)
                ->pluck('id');

            // Only backfill if unambiguous (exactly 1 matching enrollment)
            // Ambiguous (> 1) or unmatched (0) records are safely preserved without arbitrary assignment
            if ($enrollments->count() === 1) {
                DB::table('lesson_progress')
                    ->where('id', $record->id)
                    ->update(['enrollment_id' => $enrollments->first()]);
            }
        }
    }
};
