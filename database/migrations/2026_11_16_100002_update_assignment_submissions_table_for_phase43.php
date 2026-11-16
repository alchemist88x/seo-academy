<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add new columns and performance indexes without the unique constraint
        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->foreignUuid('enrollment_id')->nullable()->after('assignment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->unsignedInteger('version_number')->default(1)->after('user_id');
            $table->foreignUuid('previous_submission_id')->nullable()->after('version_number')->constrained('assignment_submissions')->nullOnDelete();
            $table->longText('content')->nullable()->after('file_path');
            $table->text('student_notes')->nullable()->after('content');
            $table->boolean('is_late')->default(false)->after('status');
            $table->dateTime('submitted_at')->nullable()->after('is_late');

            $table->index(['assignment_id', 'status']);
            $table->index(['enrollment_id', 'assignment_id']);
        });

        // 2. Deterministically backfill sequential version_number and previous_submission_id for existing records
        $groups = DB::table('assignment_submissions')
            ->select('assignment_id', 'user_id')
            ->distinct()
            ->get();

        foreach ($groups as $group) {
            $submissions = DB::table('assignment_submissions')
                ->where('assignment_id', $group->assignment_id)
                ->where('user_id', $group->user_id)
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $version = 1;
            $prevId = null;

            foreach ($submissions as $sub) {
                $updateData = [
                    'version_number' => $version,
                    'previous_submission_id' => $prevId,
                ];

                if ($sub->submitted_at === null) {
                    $updateData['submitted_at'] = $sub->created_at ?? now();
                }

                // Backfill enrollment_id if missing and tables exist
                if ($sub->enrollment_id === null && Schema::hasTable('enrollments') && Schema::hasTable('assignments')) {
                    $assignment = DB::table('assignments')->where('id', $sub->assignment_id)->first(['course_id']);
                    if ($assignment && $assignment->course_id) {
                        $studentProfile = DB::table('student_profiles')->where('user_id', $sub->user_id)->first(['id']);
                        if ($studentProfile) {
                            $enrollment = DB::table('enrollments')
                                ->where('student_profile_id', $studentProfile->id)
                                ->where('course_id', $assignment->course_id)
                                ->first(['id']);
                            if ($enrollment) {
                                $updateData['enrollment_id'] = $enrollment->id;
                            }
                        }
                    }
                }

                DB::table('assignment_submissions')->where('id', $sub->id)->update($updateData);

                $prevId = $sub->id;
                $version++;
            }
        }

        // 3. Verify no duplicates exist prior to applying the unique constraint
        $hasDuplicates = DB::table('assignment_submissions')
            ->select('assignment_id', 'user_id', 'version_number', DB::raw('COUNT(*) as cnt'))
            ->groupBy('assignment_id', 'user_id', 'version_number')
            ->having('cnt', '>', 1)
            ->exists();

        if ($hasDuplicates) {
            throw new RuntimeException('Failed to resolve sequential version numbers for historical assignment submissions.');
        }

        // 4. Safely apply unique constraint on (assignment_id, user_id, version_number)
        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->unique(['assignment_id', 'user_id', 'version_number'], 'assign_sub_user_version_unique');
        });
    }

    public function down(): void
    {
        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->dropForeign(['enrollment_id']);
            $table->dropForeign(['previous_submission_id']);
            $table->dropUnique('assign_sub_user_version_unique');
            $table->dropIndex(['assignment_id', 'status']);
            $table->dropIndex(['enrollment_id', 'assignment_id']);
            $table->dropColumn([
                'enrollment_id',
                'version_number',
                'previous_submission_id',
                'content',
                'student_notes',
                'is_late',
                'submitted_at',
            ]);
        });
    }
};
