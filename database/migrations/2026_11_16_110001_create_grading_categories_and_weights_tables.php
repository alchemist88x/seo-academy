<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignUuid('grading_scheme_id')->constrained('course_grading_schemes')->cascadeOnDelete();
            $table->string('name', 100);
            $table->decimal('weight', 5, 2); // e.g. 40.00 for 40%
            $table->unsignedSmallInteger('drop_lowest_count')->default(0);
            $table->boolean('is_mandatory')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            // Scheme and name uniqueness eliminates category naming collisions
            $table->unique(['grading_scheme_id', 'name'], 'gc_scheme_name_unique');
            // Composite unique constraint for course referential integrity
            $table->unique(['course_id', 'id'], 'gc_course_id_id_unique');
            $table->index(['grading_scheme_id', 'display_order']);
        });

        Schema::create('assessment_weights', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('grading_scheme_id')->constrained('course_grading_schemes')->cascadeOnDelete();
            $table->foreignUuid('grading_category_id')->nullable()->constrained('grading_categories')->cascadeOnDelete();
            $table->string('assessment_type', 50); // quiz, assignment, exam
            $table->uuid('assessment_id');
            $table->decimal('custom_weight', 5, 2)->nullable();
            $table->boolean('is_mandatory')->default(false);
            $table->timestamps();

            // Eliminates the SQL NULL uniqueness problem by enforcing non-null tuple uniqueness
            $table->unique(
                ['grading_scheme_id', 'assessment_type', 'assessment_id'],
                'asmt_weights_scheme_asmt_unique'
            );
            $table->index(['grading_category_id']);
            $table->index(['assessment_type', 'assessment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_weights');
        Schema::dropIfExists('grading_categories');
    }
};
