<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_submission_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('submission_id')->constrained('assignment_submissions')->cascadeOnDelete();
            $table->foreignUuid('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('storage_disk', 32)->default('private');
            $table->string('storage_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size_bytes')->default(0);
            $table->string('mime_type', 128);
            $table->string('checksum', 64)->nullable();
            $table->dateTime('created_at');

            $table->index(['submission_id']);
        });

        Schema::create('assignment_evaluations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('submission_id')->constrained('assignment_submissions')->restrictOnDelete();
            $table->foreignUuid('evaluator_id')->constrained('users')->restrictOnDelete();
            $table->decimal('raw_score', 6, 2)->nullable();
            $table->decimal('late_penalty_applied', 6, 2)->default(0.00);
            $table->decimal('points_awarded', 6, 2);
            $table->decimal('points_possible', 6, 2);
            $table->decimal('score_percentage', 5, 2);
            $table->boolean('is_passed')->default(false);
            $table->json('rubric_snapshot')->nullable();
            $table->text('overall_feedback')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('status', 32)->default('graded');
            $table->text('revision_reason')->nullable();
            $table->boolean('is_override')->default(false);
            $table->text('override_reason')->nullable();
            $table->dateTime('graded_at');
            $table->timestamps();

            $table->index(['submission_id', 'graded_at']);
            $table->index(['evaluator_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_evaluations');
        Schema::dropIfExists('assignment_submission_attachments');
    }
};
