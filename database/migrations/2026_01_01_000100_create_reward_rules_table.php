<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_rules', function (Blueprint $table) {
            $table->string('rule')->primary();
            $table->integer('points')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_rules');
    }
};
