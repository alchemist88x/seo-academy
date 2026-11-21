<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practical_projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('academy_id')->constrained('academies')->cascadeOnDelete();
            $table->foreignUuid('course_id')->nullable()->constrained('courses')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->string('category', 50); // technical_seo, on_page_seo, keyword_research, content_optimization, link_building, local_seo, seo_auditing, analytics_and_reporting, core_web_vitals
            $table->string('difficulty', 20)->default('beginner'); // beginner, intermediate, expert
            $table->text('description')->nullable();
            $table->json('objectives')->nullable();
            $table->json('expected_deliverables')->nullable();
            $table->longText('instructions')->nullable();
            $table->dateTime('start_date')->nullable();
            $table->dateTime('due_date')->nullable();
            $table->decimal('max_marks', 6, 2)->default(100.00);
            $table->decimal('passing_marks', 6, 2)->default(50.00);
            $table->unsignedInteger('max_revisions')->default(3);
            $table->json('reference_materials')->nullable();
            $table->string('status', 20)->default('draft'); // draft, published, archived
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['academy_id', 'status']);
            $table->index(['course_id', 'status']);
            $table->index(['category', 'difficulty']);
        });

        Schema::create('practical_project_criteria', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('practical_projects')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('max_marks', 6, 2)->default(10.00);
            $table->decimal('weight', 5, 2)->default(10.00);
            $table->decimal('passing_threshold', 5, 2)->nullable();
            $table->json('required_deliverables')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'display_order']);
        });

        Schema::create('practical_project_students', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('practical_projects')->cascadeOnDelete();
            $table->foreignUuid('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->dateTime('assigned_at');
            $table->dateTime('due_date_override')->nullable();
            $table->string('status', 30)->default('assigned'); // assigned, in_progress, submitted, completed
            $table->timestamps();

            $table->unique(['project_id', 'student_profile_id'], 'pps_project_student_unique');
            $table->index(['student_profile_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practical_project_students');
        Schema::dropIfExists('practical_project_criteria');
        Schema::dropIfExists('practical_projects');
    }
};
