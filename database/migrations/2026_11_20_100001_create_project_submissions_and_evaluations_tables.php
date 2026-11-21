<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('practical_projects')->cascadeOnDelete();
            $table->foreignUuid('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('version_number')->default(1);
            $table->foreignUuid('previous_submission_id')->nullable()->constrained('project_submissions')->nullOnDelete();
            $table->string('website_url', 500)->nullable();
            $table->longText('content')->nullable();
            $table->text('student_notes')->nullable();
            $table->string('status', 32)->default('submitted'); // draft, submitted, under_review, revision_requested, resubmitted, approved, rejected
            $table->decimal('final_score', 6, 2)->nullable();
            $table->decimal('score_percentage', 5, 2)->nullable();
            $table->boolean('is_passed')->default(false);
            $table->boolean('is_late')->default(false);
            $table->dateTime('submitted_at');
            $table->timestamps();

            $table->index(['project_id', 'user_id']);
            $table->index(['student_profile_id', 'status']);
            $table->index(['status', 'submitted_at']);
        });

        Schema::create('project_submission_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('submission_id')->constrained('project_submissions')->cascadeOnDelete();
            $table->string('attachment_type', 50)->default('supporting_attachment'); // seo_audit_report, pdf_document, spreadsheet, screenshot, supporting_attachment
            $table->string('storage_disk', 32)->default('private');
            $table->string('storage_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size_bytes')->default(0);
            $table->string('mime_type', 128);
            $table->string('checksum', 64)->nullable();
            $table->dateTime('created_at');

            $table->index(['submission_id']);
        });

        Schema::create('project_evaluations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('submission_id')->constrained('project_submissions')->restrictOnDelete();
            $table->foreignUuid('evaluator_id')->constrained('users')->restrictOnDelete();
            $table->decimal('points_awarded', 6, 2);
            $table->decimal('points_possible', 6, 2);
            $table->decimal('score_percentage', 5, 2);
            $table->boolean('is_passed')->default(false);
            $table->string('status', 32)->default('approved'); // approved, revision_requested, rejected
            $table->json('criteria_feedback')->nullable();
            $table->text('trainer_feedback')->nullable();
            $table->text('improvement_recommendations')->nullable();
            $table->text('revision_reason')->nullable();
            $table->dateTime('evaluated_at');
            $table->timestamps();

            $table->index(['submission_id', 'evaluated_at']);
            $table->index(['evaluator_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_evaluations');
        Schema::dropIfExists('project_submission_attachments');
        Schema::dropIfExists('project_submissions');
    }
};
