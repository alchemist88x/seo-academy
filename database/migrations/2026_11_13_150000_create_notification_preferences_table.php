<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_preferences')) {
            Schema::create('notification_preferences', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('channel')->default('in_app'); // in_app, email
                $table->string('category')->default('general'); // enrollment, support, course, system
                $table->boolean('enabled')->default(true);
                $table->timestamps();

                $table->unique(['user_id', 'channel', 'category'], 'np_user_channel_cat_unique');
                $table->index('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
