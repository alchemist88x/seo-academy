<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('academy_id')->constrained('academies')->cascadeOnDelete();
            $table->foreignUuid('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->string('applicant_name');
            $table->string('applicant_email');
            $table->string('phone')->nullable();
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['academy_id', 'status']);
            $table->index('applicant_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admissions');
    }
};
