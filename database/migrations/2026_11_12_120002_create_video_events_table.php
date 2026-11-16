<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('event_id');
            $table->string('lesson_key');
            $table->string('event_type');
            $table->decimal('position_seconds', 10, 2)->default(0);
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'event_id']);
            $table->index(['user_id', 'lesson_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_events');
    }
};
