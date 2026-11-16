<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->foreignUuid('academy_id')
                ->nullable()
                ->after('id')
                ->constrained('academies')
                ->cascadeOnDelete();
            $table->string('completion_rule', 32)
                ->default('all_lessons')
                ->after('status');
            $table->unsignedInteger('required_lessons_count')
                ->nullable()
                ->after('completion_rule');

            $table->index(['academy_id', 'course_id']);
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->foreignUuid('academy_id')
                ->nullable()
                ->after('id')
                ->constrained('academies')
                ->cascadeOnDelete();
            $table->string('type', 32)
                ->default('video')
                ->after('title');
            $table->longText('content')
                ->nullable()
                ->after('description');
            $table->string('resource_provider', 32)
                ->nullable()
                ->after('content');
            $table->string('resource_reference', 500)
                ->nullable()
                ->after('resource_provider');
            $table->foreignUuid('media_id')
                ->nullable()
                ->after('resource_reference')
                ->constrained('media')
                ->nullOnDelete();
            $table->unsignedInteger('duration_seconds')
                ->default(0)
                ->after('thumbnail_path');
            $table->string('visibility', 20)
                ->default('enrolled')
                ->after('status');
            $table->boolean('is_free_preview')
                ->default(false)
                ->after('visibility');
            $table->unsignedTinyInteger('completion_threshold_percent')
                ->default(80)
                ->after('is_free_preview');

            $table->index(['academy_id', 'module_id']);
        });

        DB::table('modules')
            ->whereNull('academy_id')
            ->orderBy('id')
            ->chunkById(100, function ($modules): void {
                $courseIds = $modules->pluck('course_id')->filter()->unique()->all();
                if ($courseIds === []) {
                    return;
                }

                $courseAcademies = DB::table('courses')
                    ->whereIn('id', $courseIds)
                    ->pluck('academy_id', 'id');

                foreach ($modules as $module) {
                    $academyId = $courseAcademies[$module->course_id] ?? null;
                    if ($academyId !== null) {
                        DB::table('modules')
                            ->where('id', $module->id)
                            ->update(['academy_id' => $academyId]);
                    }
                }
            });

        DB::table('lessons')
            ->whereNull('academy_id')
            ->orderBy('id')
            ->chunkById(100, function ($lessons): void {
                $moduleIds = $lessons->pluck('module_id')->filter()->unique()->all();
                if ($moduleIds === []) {
                    return;
                }

                $moduleAcademies = DB::table('modules')
                    ->whereIn('id', $moduleIds)
                    ->pluck('academy_id', 'id');

                foreach ($lessons as $lesson) {
                    $academyId = $moduleAcademies[$lesson->module_id] ?? null;
                    if ($academyId !== null) {
                        DB::table('lessons')
                            ->where('id', $lesson->id)
                            ->update(['academy_id' => $academyId]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropForeign(['media_id']);
            $table->dropForeign(['academy_id']);
            $table->dropIndex(['academy_id', 'module_id']);
            $table->dropColumn([
                'media_id',
                'academy_id',
                'type',
                'content',
                'resource_provider',
                'resource_reference',
                'duration_seconds',
                'visibility',
                'is_free_preview',
                'completion_threshold_percent',
            ]);
        });

        Schema::table('modules', function (Blueprint $table) {
            $table->dropForeign(['academy_id']);
            $table->dropIndex(['academy_id', 'course_id']);
            $table->dropColumn([
                'academy_id',
                'completion_rule',
                'required_lessons_count',
            ]);
        });
    }
};
