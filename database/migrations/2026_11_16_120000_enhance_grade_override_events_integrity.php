<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('grade_override_events')) {
            Schema::table('grade_override_events', function (Blueprint $table) {
                if (! Schema::hasColumn('grade_override_events', 'sequence_number')) {
                    $table->unsignedInteger('sequence_number')->default(1)->after('id');
                }
                if (! Schema::hasColumn('grade_override_events', 'previous_event_id')) {
                    $table->foreignUuid('previous_event_id')->nullable()->after('sequence_number')->constrained('grade_override_events')->nullOnDelete();
                }
                if (! Schema::hasColumn('grade_override_events', 'reversal_of_event_id')) {
                    $table->foreignUuid('reversal_of_event_id')->nullable()->after('previous_event_id')->constrained('grade_override_events')->nullOnDelete();
                }
                if (! Schema::hasColumn('grade_override_events', 'effective_score')) {
                    $table->decimal('effective_score', 5, 2)->nullable()->after('new_score');
                }

                $table->index(['enrollment_id', 'assessment_type', 'sequence_number'], 'grade_override_seq_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('grade_override_events')) {
            Schema::table('grade_override_events', function (Blueprint $table) {
                $columns = ['sequence_number', 'previous_event_id', 'reversal_of_event_id', 'effective_score'];
                $toDrop = array_filter($columns, fn ($col) => Schema::hasColumn('grade_override_events', $col));
                if (! empty($toDrop)) {
                    if (Schema::hasColumn('grade_override_events', 'previous_event_id')) {
                        $table->dropForeign(['previous_event_id']);
                    }
                    if (Schema::hasColumn('grade_override_events', 'reversal_of_event_id')) {
                        $table->dropForeign(['reversal_of_event_id']);
                    }
                    $table->dropIndex('grade_override_seq_index');
                    $table->dropColumn($toDrop);
                }
            });
        }
    }
};
