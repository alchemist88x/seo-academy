<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_rubric_criteria', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assignment_id')->constrained('assignments')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('max_marks', 6, 2);
            $table->unsignedInteger('display_order')->default(0);
            $table->json('levels')->nullable();
            $table->timestamps();

            $table->index(['assignment_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_rubric_criteria');
    }
};
