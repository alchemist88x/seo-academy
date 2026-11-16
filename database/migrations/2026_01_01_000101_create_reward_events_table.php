<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('activity_key');
            $table->string('activity');
            $table->integer('points')->default(0);
            $table->string('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'activity_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_events');
    }
};
