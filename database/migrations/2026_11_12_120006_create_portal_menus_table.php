<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_menus', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('academy_id')->nullable()->constrained('academies')->cascadeOnDelete();
            $table->string('location')->default('sidebar');
            $table->string('label');
            $table->string('route_or_url');
            $table->string('icon')->nullable();
            $table->string('role')->nullable();
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['academy_id', 'location', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_menus');
    }
};
