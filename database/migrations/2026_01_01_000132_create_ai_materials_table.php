<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_materials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('academy_id')->constrained('academies')->cascadeOnDelete();
            $table->string('filename');
            $table->string('stored_path');
            $table->longText('extracted_text')->nullable();
            $table->string('uploaded_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('academy_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_materials');
    }
};
