<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->string('proficiency', 20)->nullable()->default(null)->change();
            $table->string('level')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->string('proficiency', 20)->default('beginner')->change();
            $table->string('level')->default('Beginner')->change();
        });
    }
};
