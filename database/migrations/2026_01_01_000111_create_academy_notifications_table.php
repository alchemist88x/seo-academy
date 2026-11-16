<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academy_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('academy_id')->constrained('academies')->cascadeOnDelete();
            $table->string('audience')->default('all'); // all, batch, student
            $table->string('target')->nullable();
            $table->string('category')->nullable();
            $table->string('title');
            $table->text('message');
            $table->string('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['academy_id', 'audience']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_notifications');
    }
};
