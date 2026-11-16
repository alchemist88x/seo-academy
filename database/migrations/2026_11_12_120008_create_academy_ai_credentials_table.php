<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academy_ai_credentials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('academy_id')->constrained('academies')->cascadeOnDelete();
            $table->string('driver', 50);
            $table->string('endpoint')->nullable();
            $table->string('model')->nullable();
            $table->text('api_key')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->unique(['academy_id', 'driver']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_ai_credentials');
    }
};
