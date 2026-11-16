<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            if (! Schema::hasColumn('enrollments', 'academy_id')) {
                $table->foreignUuid('academy_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('academies')
                    ->cascadeOnDelete();
            }

            if (! Schema::hasColumn('enrollments', 'assigned_by')) {
                $table->foreignUuid('assigned_by')
                    ->nullable()
                    ->after('course_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('enrollments', 'starts_at')) {
                $table->dateTime('starts_at')->nullable()->after('enrolled_at');
            }

            if (! Schema::hasColumn('enrollments', 'expires_at')) {
                $table->dateTime('expires_at')->nullable()->after('starts_at')->index();
            }

            if (! Schema::hasColumn('enrollments', 'cancelled_at')) {
                $table->dateTime('cancelled_at')->nullable()->after('status');
            }

            if (! Schema::hasColumn('enrollments', 'cancellation_reason')) {
                $table->string('cancellation_reason')->nullable()->after('cancelled_at');
            }

            if (! Schema::hasColumn('enrollments', 'suspended_at')) {
                $table->dateTime('suspended_at')->nullable()->after('cancellation_reason');
            }

            if (! Schema::hasColumn('enrollments', 'suspension_reason')) {
                $table->string('suspension_reason')->nullable()->after('suspended_at');
            }

            if (! Schema::hasColumn('enrollments', 'completed_at')) {
                $table->dateTime('completed_at')->nullable()->after('suspension_reason');
            }

            if (! Schema::hasColumn('enrollments', 'notes')) {
                $table->text('notes')->nullable()->after('completed_at');
            }
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->index('status');
            $table->string('status')->default('pending')->change();
        });

        // Backfill academy_id
        if (Schema::hasTable('academies')) {
            $defaultAcademyId = DB::table('academies')->value('id');
            if ($defaultAcademyId !== null) {
                DB::table('enrollments')->whereNull('academy_id')->update(['academy_id' => $defaultAcademyId]);
            }
        }

        // Normalize existing status values
        DB::table('enrollments')->where('status', 'Active')->update(['status' => 'active']);
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            try {
                $table->dropIndex(['status']);
            } catch (Throwable) {
            }

            try {
                $table->dropIndex(['expires_at']);
            } catch (Throwable) {
            }

            if (Schema::hasColumn('enrollments', 'assigned_by')) {
                $table->dropConstrainedForeignId('assigned_by');
            }

            if (Schema::hasColumn('enrollments', 'academy_id')) {
                $table->dropConstrainedForeignId('academy_id');
            }

            $columnsToDrop = [];
            foreach ([
                'starts_at',
                'expires_at',
                'cancelled_at',
                'cancellation_reason',
                'suspended_at',
                'suspension_reason',
                'completed_at',
                'notes',
            ] as $column) {
                if (Schema::hasColumn('enrollments', $column)) {
                    $columnsToDrop[] = $column;
                }
            }

            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
