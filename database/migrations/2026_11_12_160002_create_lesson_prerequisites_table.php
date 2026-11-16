<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_prerequisites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('academy_id')->constrained('academies')->cascadeOnDelete();
            $table->foreignUuid('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->foreignUuid('prerequisite_lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['lesson_id', 'prerequisite_lesson_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_prerequisites');
    }
};
