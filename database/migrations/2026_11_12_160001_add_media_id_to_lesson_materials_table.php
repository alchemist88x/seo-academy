<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_materials', function (Blueprint $table) {
            $table->foreignUuid('academy_id')
                ->nullable()
                ->after('id')
                ->constrained('academies')
                ->cascadeOnDelete();
            $table->foreignUuid('media_id')
                ->nullable()
                ->after('lesson_id')
                ->constrained('media')
                ->nullOnDelete();
            $table->unsignedBigInteger('file_size_bytes')
                ->nullable()
                ->after('file_path');
            $table->string('mime_type', 127)
                ->nullable()
                ->after('file_size_bytes');
            $table->string('provider', 32)
                ->default('private_media')
                ->after('mime_type');
        });

        DB::table('lesson_materials')
            ->whereNull('academy_id')
            ->orderBy('id')
            ->chunkById(100, function ($materials): void {
                $lessonIds = $materials->pluck('lesson_id')->filter()->unique()->all();
                if ($lessonIds === []) {
                    return;
                }

                $lessonAcademies = DB::table('lessons')
                    ->whereIn('id', $lessonIds)
                    ->pluck('academy_id', 'id');

                foreach ($materials as $material) {
                    $academyId = $lessonAcademies[$material->lesson_id] ?? null;
                    if ($academyId !== null) {
                        DB::table('lesson_materials')
                            ->where('id', $material->id)
                            ->update(['academy_id' => $academyId]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('lesson_materials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('media_id');
            $table->dropConstrainedForeignId('academy_id');
            $table->dropColumn([
                'file_size_bytes',
                'mime_type',
                'provider',
            ]);
        });
    }
};
