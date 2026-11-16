<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            if (! Schema::hasColumn('quizzes', 'course_id')) {
                $table->foreignUuid('course_id')
                    ->nullable()
                    ->after('module_id')
                    ->constrained('courses')
                    ->cascadeOnDelete();
            }

            if (! Schema::hasColumn('quizzes', 'lesson_id')) {
                $table->foreignUuid('lesson_id')
                    ->nullable()
                    ->after('course_id')
                    ->constrained('lessons')
                    ->cascadeOnDelete();
            }

            if (! Schema::hasColumn('quizzes', 'type')) {
                $table->string('type', 32)
                    ->default('quiz')
                    ->after('lesson_id');
            }

            if (! Schema::hasColumn('quizzes', 'is_required')) {
                $table->boolean('is_required')
                    ->default(true)
                    ->after('type');
            }

            if (! Schema::hasColumn('quizzes', 'attempt_limit')) {
                $table->unsignedInteger('attempt_limit')
                    ->nullable()
                    ->after('is_required');
            }

            if (! Schema::hasColumn('quizzes', 'time_limit_minutes')) {
                $table->unsignedInteger('time_limit_minutes')
                    ->nullable()
                    ->after('attempt_limit');
            }

            if (! Schema::hasColumn('quizzes', 'passing_percentage')) {
                $table->unsignedTinyInteger('passing_percentage')
                    ->default(70)
                    ->after('time_limit_minutes');
            }

            if (! Schema::hasColumn('quizzes', 'published_at')) {
                $table->timestamp('published_at')
                    ->nullable()
                    ->after('status');
            }
        });

        Schema::table('quiz_questions', function (Blueprint $table) {
            if (! Schema::hasColumn('quiz_questions', 'explanation')) {
                $table->text('explanation')
                    ->nullable()
                    ->after('display_order');
            }

            if (! Schema::hasColumn('quiz_questions', 'rubric')) {
                $table->text('rubric')
                    ->nullable()
                    ->after('explanation');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quiz_questions', function (Blueprint $table) {
            if (Schema::hasColumn('quiz_questions', 'explanation')) {
                $table->dropColumn('explanation');
            }
            if (Schema::hasColumn('quiz_questions', 'rubric')) {
                $table->dropColumn('rubric');
            }
        });

        $isMysql = DB::getDriverName() === 'mysql';

        Schema::table('quizzes', function (Blueprint $table) use ($isMysql) {
            if ($isMysql) {
                if (Schema::hasColumn('quizzes', 'course_id')) {
                    $table->dropForeign(['course_id']);
                    $table->dropColumn('course_id');
                }
                if (Schema::hasColumn('quizzes', 'lesson_id')) {
                    $table->dropForeign(['lesson_id']);
                    $table->dropColumn('lesson_id');
                }
            } else {
                if (Schema::hasColumn('quizzes', 'course_id')) {
                    $table->dropConstrainedForeignId('course_id');
                }
                if (Schema::hasColumn('quizzes', 'lesson_id')) {
                    $table->dropConstrainedForeignId('lesson_id');
                }
            }

            $columnsToDrop = [];
            foreach (['type', 'is_required', 'attempt_limit', 'time_limit_minutes', 'passing_percentage', 'published_at'] as $col) {
                if (Schema::hasColumn('quizzes', $col)) {
                    $columnsToDrop[] = $col;
                }
            }

            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
