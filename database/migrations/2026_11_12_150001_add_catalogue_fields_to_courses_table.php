<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->foreignUuid('category_id')
                ->nullable()
                ->after('academy_id')
                ->constrained('course_categories')
                ->nullOnDelete();
            $table->string('difficulty', 32)->default('Beginner')->after('description');
            $table->decimal('price', 10, 2)->default(0.00)->after('difficulty');
            $table->string('currency', 3)->default('USD')->after('price');
            $table->boolean('is_free')->default(true)->after('currency');
            $table->string('visibility', 20)->default('public')->after('is_free');
            $table->unsignedInteger('enrollment_limit')->nullable()->after('visibility');
            $table->timestamp('published_at')->nullable()->after('status');

            $table->dropUnique(['slug']);
            $table->unique(['academy_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropUnique(['academy_id', 'slug']);
            $table->unique('slug');
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn([
                'difficulty',
                'price',
                'currency',
                'is_free',
                'visibility',
                'enrollment_limit',
                'published_at',
            ]);
        });
    }
};
