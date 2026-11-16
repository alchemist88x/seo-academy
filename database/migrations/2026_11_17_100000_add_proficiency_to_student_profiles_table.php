<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('student_profiles', 'proficiency')) {
            Schema::table('student_profiles', function (Blueprint $table) {
                $table->string('proficiency', 20)->default('beginner')->after('level');
                $table->index(['academy_id', 'proficiency']);
            });

            // Backfill existing records cleanly based on historical level string
            DB::table('student_profiles')->get(['id', 'level'])->each(function ($row) {
                $lvl = strtolower(trim((string) ($row->level ?? '')));
                $proficiency = match (true) {
                    str_contains($lvl, 'inter') => 'intermediate',
                    str_contains($lvl, 'adv') || str_contains($lvl, 'exp') => 'expert',
                    default => 'beginner',
                };

                DB::table('student_profiles')->where('id', $row->id)->update([
                    'proficiency' => $proficiency,
                ]);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('student_profiles', 'proficiency')) {
            Schema::table('student_profiles', function (Blueprint $table) {
                $table->dropIndex(['academy_id', 'proficiency']);
                $table->dropColumn('proficiency');
            });
        }
    }
};
