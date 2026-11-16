<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->uuid('module_id')->nullable()->change();
            $table->foreignUuid('academy_id')->nullable()->after('id')->constrained('academies')->cascadeOnDelete();
            $table->foreignUuid('course_id')->nullable()->after('academy_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignUuid('lesson_id')->nullable()->after('module_id')->constrained('lessons')->nullOnDelete();
            $table->longText('instructions')->nullable()->after('description');
            $table->text('submission_guidelines')->nullable()->after('instructions');
            $table->string('assignment_type', 32)->default('file_and_text')->after('submission_guidelines');
            $table->decimal('max_marks', 6, 2)->default(100.00)->after('assignment_type');
            $table->decimal('passing_marks', 6, 2)->default(50.00)->after('max_marks');
            $table->dateTime('available_from')->nullable()->after('due_date');
            $table->dateTime('available_to')->nullable()->after('available_from');
            $table->string('late_submission_policy', 32)->default('allowed_no_penalty')->after('available_to');
            $table->decimal('late_penalty_percent', 5, 2)->default(0.00)->after('late_submission_policy');
            $table->dateTime('late_hard_deadline')->nullable()->after('late_penalty_percent');
            $table->unsignedInteger('max_attempts')->nullable()->after('late_hard_deadline');
            $table->boolean('allow_resubmission')->default(true)->after('max_attempts');
            $table->boolean('allow_multiple_files')->default(true)->after('allow_resubmission');
            $table->unsignedInteger('max_file_count')->default(5)->after('allow_multiple_files');
            $table->unsignedInteger('max_file_size_mb')->default(25)->after('max_file_count');
            $table->json('allowed_file_types')->nullable()->after('max_file_size_mb');
            $table->string('rubric_mode', 32)->default('none')->after('allowed_file_types');
            $table->boolean('is_mandatory')->default(false)->after('rubric_mode');
            $table->foreignUuid('created_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();

            $table->index(['academy_id', 'status']);
            $table->index(['course_id', 'status']);
        });

        // Backfill course_id and academy_id from existing modules if rows exist
        if (Schema::hasTable('modules')) {
            $assignments = DB::table('assignments')->whereNull('course_id')->whereNotNull('module_id')->get(['id', 'module_id']);
            foreach ($assignments as $a) {
                $module = DB::table('modules')->where('id', $a->module_id)->first(['course_id', 'academy_id']);
                if ($module) {
                    $course = DB::table('courses')->where('id', $module->course_id)->first(['academy_id']);
                    $academyId = $module->academy_id ?? $course?->academy_id;
                    DB::table('assignments')->where('id', $a->id)->update([
                        'course_id' => $module->course_id,
                        'academy_id' => $academyId,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropForeign(['academy_id']);
            $table->dropForeign(['course_id']);
            $table->dropForeign(['lesson_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropIndex(['academy_id', 'status']);
            $table->dropIndex(['course_id', 'status']);
            $table->dropColumn([
                'academy_id',
                'course_id',
                'lesson_id',
                'created_by',
                'updated_by',
                'instructions',
                'submission_guidelines',
                'assignment_type',
                'max_marks',
                'passing_marks',
                'available_from',
                'available_to',
                'late_submission_policy',
                'late_penalty_percent',
                'late_hard_deadline',
                'max_attempts',
                'allow_resubmission',
                'allow_multiple_files',
                'max_file_count',
                'max_file_size_mb',
                'allowed_file_types',
                'rubric_mode',
                'is_mandatory',
            ]);
        });
    }
};
