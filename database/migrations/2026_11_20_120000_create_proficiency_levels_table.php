<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('proficiency_levels')) {
            Schema::create('proficiency_levels', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('academy_id')->constrained('academies')->cascadeOnDelete();
                $table->string('name', 100);
                $table->string('slug', 100);
                $table->text('description')->nullable();
                $table->integer('display_order')->default(0);
                $table->string('color', 50)->default('blue');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['academy_id', 'slug']);
                $table->index(['academy_id', 'is_active', 'display_order']);
            });
        }

        if (! Schema::hasColumn('student_profiles', 'proficiency_level_id')) {
            Schema::table('student_profiles', function (Blueprint $table) {
                $table->foreignUuid('proficiency_level_id')
                    ->nullable()
                    ->after('academy_id')
                    ->constrained('proficiency_levels')
                    ->nullOnDelete();
                $table->string('proficiency', 100)->nullable()->change();
                $table->string('level', 100)->nullable()->change();
            });
        }

        // Backfill default proficiency levels for every existing academy if none exist
        if (Schema::hasTable('academies')) {
            $academies = DB::table('academies')->get(['id']);
            foreach ($academies as $academy) {
                $defaults = [
                    [
                        'id' => (string) Str::uuid(),
                        'academy_id' => $academy->id,
                        'name' => 'Beginner',
                        'slug' => 'beginner',
                        'description' => 'Foundational concepts and essential introductory knowledge.',
                        'display_order' => 1,
                        'color' => 'blue',
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'academy_id' => $academy->id,
                        'name' => 'Intermediate',
                        'slug' => 'intermediate',
                        'description' => 'Practical competency, standard workflow execution, and hands-on skill.',
                        'display_order' => 2,
                        'color' => 'amber',
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'academy_id' => $academy->id,
                        'name' => 'Expert',
                        'slug' => 'expert',
                        'description' => 'Advanced strategic mastery, technical depth, and industry leadership.',
                        'display_order' => 3,
                        'color' => 'purple',
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ];

                foreach ($defaults as $row) {
                    $exists = DB::table('proficiency_levels')
                        ->where('academy_id', $academy->id)
                        ->where('slug', $row['slug'])
                        ->exists();

                    if (! $exists) {
                        DB::table('proficiency_levels')->insert($row);
                    }
                }

                // Relate existing student profiles to their newly seeded level
                $levels = DB::table('proficiency_levels')
                    ->where('academy_id', $academy->id)
                    ->get()
                    ->keyBy('slug');

                foreach ($levels as $slug => $lvl) {
                    DB::table('student_profiles')
                        ->where('academy_id', $academy->id)
                        ->where('proficiency', $slug)
                        ->whereNull('proficiency_level_id')
                        ->update([
                            'proficiency_level_id' => $lvl->id,
                            'level' => $lvl->name,
                        ]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('student_profiles', 'proficiency_level_id')) {
            Schema::table('student_profiles', function (Blueprint $table) {
                $table->dropForeign(['proficiency_level_id']);
                $table->dropColumn('proficiency_level_id');
            });
        }

        Schema::dropIfExists('proficiency_levels');
    }
};
