<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->dropForeign(['assignment_id']);
            $table->dropForeign(['user_id']);
            $table->foreign('assignment_id')->references('id')->on('assignments')->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });

        if (Schema::hasTable('assignment_evaluations')) {
            Schema::table('assignment_evaluations', function (Blueprint $table) {
                if (! Schema::hasColumn('assignment_evaluations', 'raw_score')) {
                    $table->decimal('raw_score', 6, 2)->nullable()->after('evaluator_id');
                }
                if (! Schema::hasColumn('assignment_evaluations', 'late_penalty_applied')) {
                    $table->decimal('late_penalty_applied', 6, 2)->default(0.00)->after('raw_score');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->dropForeign(['assignment_id']);
            $table->dropForeign(['user_id']);
            $table->foreign('assignment_id')->references('id')->on('assignments')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        if (Schema::hasTable('assignment_evaluations')) {
            Schema::table('assignment_evaluations', function (Blueprint $table) {
                $columns = [];
                if (Schema::hasColumn('assignment_evaluations', 'late_penalty_applied')) {
                    $columns[] = 'late_penalty_applied';
                }
                if (Schema::hasColumn('assignment_evaluations', 'raw_score')) {
                    $columns[] = 'raw_score';
                }
                if (! empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
