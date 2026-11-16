<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('academy_id')->constrained('academies')->cascadeOnDelete();
            $table->string('student_code')->unique();
            $table->string('batch')->nullable();
            $table->date('enrolled_at')->nullable();
            $table->string('level')->default('Beginner');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['academy_id', 'batch']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};
