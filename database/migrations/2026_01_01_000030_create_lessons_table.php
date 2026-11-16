<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('module_id')->constrained('modules')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('display_order')->default(0);
            $table->string('video_path')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->string('status')->default('Draft');
            $table->timestamps();

            $table->index(['module_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
