<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Mutable working draft table
        Schema::create('enrollment_grade_drafts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('enrollment_id')->unique()->constrained('enrollments')->cascadeOnDelete();
            $table->foreignUuid('grading_scheme_id')->constrained('course_grading_schemes')->restrictOnDelete();
            $table->decimal('current_score', 5, 2)->nullable();
            $table->string('current_letter_grade', 10)->nullable();
            $table->json('calculation_breakdown')->nullable();
            $table->boolean('is_passing')->default(false);
            $table->string('academic_status', 50)->default('in_progress'); // in_progress, complete, incomplete, exempt
            $table->boolean('missing_mandatory_assessments')->default(false);
            $table->timestamp('last_calculated_at')->nullable();
            $table->timestamps();

            $table->index(['enrollment_id', 'academic_status']);
        });

        // 2. Truly immutable published snapshot table
        Schema::create('enrollment_final_grades', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->foreignUuid('grading_scheme_id')->constrained('course_grading_schemes')->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->decimal('final_score', 5, 2);
            $table->string('letter_grade', 10)->nullable();
            $table->boolean('is_passing');
            $table->string('academic_status', 50); // passed, failed, incomplete, withdrawn
            $table->json('calculation_snapshot');
            $table->timestamp('published_at');
            $table->foreignUuid('published_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('supersedes_grade_id')->nullable()->constrained('enrollment_final_grades')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            // Composite unique constraint required for composite foreign key from enrollment_current_grades
            $table->unique(['enrollment_id', 'id'], 'efg_enrollment_id_id_unique');
            // Strict version ordering per enrollment
            $table->unique(['enrollment_id', 'version'], 'efg_enrollment_version_unique');
            $table->index(['enrollment_id', 'published_at']);
        });

        // 3. Current grade pointer table with optimistic concurrency control
        Schema::create('enrollment_current_grades', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('enrollment_id')->unique()->constrained('enrollments')->cascadeOnDelete();
            $table->uuid('published_final_grade_id')->nullable();
            $table->foreignUuid('working_draft_grade_id')->nullable()->constrained('enrollment_grade_drafts')->nullOnDelete();
            $table->string('status', 50)->default('draft'); // draft, published, superseded, withdrawn, under_review
            $table->unsignedInteger('lock_version')->default(0); // For CAS / Optimistic Concurrency
            $table->timestamps();

            // Composite foreign key: guarantees pointer can never reference a grade snapshot belonging to another enrollment
            $table->foreign(['enrollment_id', 'published_final_grade_id'], 'ecg_enrollment_grade_pointer_fk')
                ->references(['enrollment_id', 'id'])
                ->on('enrollment_final_grades')
                ->restrictOnDelete();

            $table->index(['status', 'lock_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_current_grades');
        Schema::dropIfExists('enrollment_final_grades');
        Schema::dropIfExists('enrollment_grade_drafts');
    }
};
