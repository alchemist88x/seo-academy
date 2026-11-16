<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_streaks', function (Blueprint $table) {
            $table->foreignUuid('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->integer('current_streak')->default(0);
            $table->integer('best_streak')->default(0);
            $table->date('last_active_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_streaks');
    }
};
