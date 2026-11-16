<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('assessment_attempts')) {
            Schema::create('assessment_attempts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('academy_id')->constrained('academies')->cascadeOnDelete();
                $table->foreignUuid('assessment_id')->constrained('quizzes')->cascadeOnDelete();
                $table->foreignUuid('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
                $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedInteger('attempt_number')->default(1);
                $table->string('status', 32)->default('in_progress');
                $table->dateTime('started_at');
                $table->dateTime('submitted_at')->nullable();
                $table->decimal('total_points', 8, 2)->default(0);
                $table->decimal('earned_points', 8, 2)->default(0);
                $table->decimal('score_percentage', 5, 2)->default(0);
                $table->boolean('passed')->default(false);
                $table->boolean('requires_manual_grading')->default(false);
                $table->foreignUuid('evaluated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('evaluated_at')->nullable();
                $table->text('evaluation_notes')->nullable();
                $table->timestamps();

                $table->index(['enrollment_id', 'assessment_id']);
                $table->index(['assessment_id', 'user_id']);
                $table->index('status');
            });
        }

        if (! Schema::hasTable('assessment_attempt_answers')) {
            Schema::create('assessment_attempt_answers', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('attempt_id')->constrained('assessment_attempts')->cascadeOnDelete();
                $table->foreignUuid('question_id')->constrained('quiz_questions')->cascadeOnDelete();
                $table->longText('student_answer')->nullable();
                $table->boolean('is_correct')->nullable();
                $table->decimal('points_awarded', 8, 2)->default(0);
                $table->decimal('points_possible', 8, 2)->default(1);
                $table->text('evaluator_feedback')->nullable();
                $table->foreignUuid('evaluated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('evaluated_at')->nullable();
                $table->timestamps();

                $table->index(['attempt_id', 'question_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_attempt_answers');
        Schema::dropIfExists('assessment_attempts');
    }
};
