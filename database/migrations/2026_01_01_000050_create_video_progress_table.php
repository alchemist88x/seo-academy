<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_progress', function (Blueprint $table) {
            $table->uuid('id')->unique();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('lesson_key');
            $table->decimal('position_seconds', 10, 2)->default(0);
            $table->decimal('duration_seconds', 10, 2)->default(0);
            $table->decimal('view_seconds', 10, 2)->default(0);
            $table->boolean('completed')->default(false);
            $table->timestamp('updated_at')->nullable();

            $table->primary(['user_id', 'lesson_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_progress');
    }
};
