<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_videos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('live_class_id')->constrained('live_classes')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('video_path');
            $table->string('thumbnail_path')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->integer('display_order')->default(0);
            $table->string('status')->default('Active');
            $table->timestamps();

            $table->index(['live_class_id', 'display_order']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_videos');
    }
};
