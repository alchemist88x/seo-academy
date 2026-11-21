<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_portfolios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_profile_id')->unique()->constrained('student_profiles')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('headline', 255)->nullable();
            $table->text('bio')->nullable();
            $table->json('custom_skills')->nullable();
            $table->boolean('is_public')->default(false);
            $table->string('public_token', 64)->nullable()->unique();
            $table->dateTime('public_token_generated_at')->nullable();
            $table->boolean('public_identity_consent')->default(false);
            $table->dateTime('consent_recorded_at')->nullable();
            $table->timestamps();

            $table->index(['is_public', 'public_token']);
        });

        Schema::create('student_portfolio_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('portfolio_id')->constrained('student_portfolios')->cascadeOnDelete();
            $table->foreignUuid('submission_id')->constrained('project_submissions')->cascadeOnDelete();
            $table->string('title');
            $table->text('case_study')->nullable();
            $table->json('skills_demonstrated')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->json('included_attachments')->nullable();
            $table->timestamps();

            $table->index(['portfolio_id', 'display_order']);
        });

        Schema::create('project_achievements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('practical_projects')->cascadeOnDelete();
            $table->foreignUuid('submission_id')->constrained('project_submissions')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('achievement_number', 64)->unique();
            $table->string('verification_code', 64)->unique();
            $table->string('title');
            $table->string('category', 50);
            $table->decimal('score_percentage', 5, 2);
            $table->json('skills_awarded')->nullable();
            $table->dateTime('issued_at');
            $table->string('status', 20)->default('issued');
            $table->timestamps();

            $table->index(['user_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_achievements');
        Schema::dropIfExists('student_portfolio_items');
        Schema::dropIfExists('student_portfolios');
    }
};
