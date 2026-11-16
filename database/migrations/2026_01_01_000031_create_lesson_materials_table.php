<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_materials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->integer('display_order')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['lesson_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_materials');
    }
};
