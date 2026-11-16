<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_levels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('name'); // Beginner, Intermediate, Advanced
            $table->integer('display_order')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_levels');
    }
};
