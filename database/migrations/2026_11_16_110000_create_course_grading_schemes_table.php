<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_grading_schemes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('name', 150);
            $table->unsignedInteger('version')->default(1);
            $table->decimal('passing_grade', 5, 2)->default(70.00);
            $table->string('calculation_method', 50)->default('weighted_categories');
            $table->string('status', 30)->default('draft'); // draft, active, archived
            $table->boolean('is_active')->default(false);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Composite unique constraint required for composite foreign key from courses
            $table->unique(['course_id', 'id'], 'cgs_course_id_id_unique');
            // Version sequence per course
            $table->unique(['course_id', 'version'], 'cgs_course_version_unique');
            $table->index(['course_id', 'status']);
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->uuid('active_grading_scheme_id')->nullable()->after('status');

            // Composite foreign key: guarantees course can only reference a scheme belonging to itself
            $table->foreign(['id', 'active_grading_scheme_id'], 'courses_active_grading_scheme_fk')
                ->references(['course_id', 'id'])
                ->on('course_grading_schemes')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                $table->dropForeign(['id', 'active_grading_scheme_id']);
            } else {
                $table->dropForeign('courses_active_grading_scheme_fk');
            }
            $table->dropColumn('active_grading_scheme_id');
        });

        Schema::dropIfExists('course_grading_schemes');
    }
};
