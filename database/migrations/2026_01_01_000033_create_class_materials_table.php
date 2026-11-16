<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_materials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('live_class_id')->constrained('live_classes')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->integer('display_order')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['live_class_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_materials');
    }
};
