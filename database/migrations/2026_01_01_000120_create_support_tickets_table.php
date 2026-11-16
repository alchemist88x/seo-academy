<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('academy_id')->constrained('academies')->cascadeOnDelete();
            $table->string('subject');
            $table->text('description');
            $table->string('module_name')->nullable();
            $table->string('status')->default('Pending'); // Pending, In Progress, Resolved
            $table->timestamps();

            $table->index(['academy_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
