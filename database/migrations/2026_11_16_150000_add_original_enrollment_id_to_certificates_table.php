<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->foreignUuid('original_enrollment_id')
                ->nullable()
                ->after('enrollment_id')
                ->constrained('enrollments')
                ->cascadeOnDelete();

            $table->index('original_enrollment_id');
        });

        // Backfill existing certificates: original_enrollment_id = enrollment_id
        DB::table('certificates')
            ->whereNotNull('enrollment_id')
            ->update([
                'original_enrollment_id' => DB::raw('enrollment_id'),
            ]);

        // For any historical certificates where enrollment_id was cleared during reissuance,
        // backfill from successor
        $superseded = DB::table('certificates')
            ->whereNull('original_enrollment_id')
            ->whereNotNull('superseded_by_id')
            ->get(['id', 'superseded_by_id']);

        foreach ($superseded as $cert) {
            $successor = DB::table('certificates')->where('id', $cert->superseded_by_id)->first();
            if ($successor && ($successor->original_enrollment_id || $successor->enrollment_id)) {
                $enrollId = $successor->original_enrollment_id ?: $successor->enrollment_id;
                DB::table('certificates')->where('id', $cert->id)->update(['original_enrollment_id' => $enrollId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropForeign(['original_enrollment_id']);
            $table->dropIndex(['original_enrollment_id']);
            $table->dropColumn('original_enrollment_id');
        });
    }
};
