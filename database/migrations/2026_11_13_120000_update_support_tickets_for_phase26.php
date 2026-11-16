<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('support_tickets', 'priority')) {
                $table->string('priority')->default('medium')->index()->after('status');
            }

            if (! Schema::hasColumn('support_tickets', 'assigned_to')) {
                $table->foreignUuid('assigned_to')
                    ->nullable()
                    ->after('priority')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('support_tickets', 'assigned_at')) {
                $table->dateTime('assigned_at')->nullable()->after('assigned_to');
            }

            if (! Schema::hasColumn('support_tickets', 'resolved_at')) {
                $table->dateTime('resolved_at')->nullable()->after('assigned_at');
            }

            if (! Schema::hasColumn('support_tickets', 'closed_at')) {
                $table->dateTime('closed_at')->nullable()->after('resolved_at');
            }

            if (! Schema::hasColumn('support_tickets', 'last_replied_at')) {
                $table->dateTime('last_replied_at')->nullable()->after('closed_at');
            }
        });

        if (! Schema::hasTable('support_ticket_messages')) {
            Schema::create('support_ticket_messages', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
                $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
                $table->text('message');
                $table->boolean('is_internal')->default(false);
                $table->timestamps();

                $table->index(['support_ticket_id', 'is_internal', 'created_at'], 'stm_ticket_internal_created_idx');
            });
        }

        // Normalize existing status values
        if (Schema::hasTable('support_tickets')) {
            DB::table('support_tickets')->where('status', 'Pending')->update(['status' => 'open']);
            DB::table('support_tickets')->where('status', 'In Progress')->update(['status' => 'in_progress']);
            DB::table('support_tickets')->where('status', 'Resolved')->update(['status' => 'resolved']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_messages');

        Schema::table('support_tickets', function (Blueprint $table) {
            try {
                $table->dropIndex(['priority']);
            } catch (Throwable) {
            }

            if (Schema::hasColumn('support_tickets', 'assigned_to')) {
                $table->dropConstrainedForeignId('assigned_to');
            }

            $columnsToDrop = [];
            foreach ([
                'priority',
                'assigned_at',
                'resolved_at',
                'closed_at',
                'last_replied_at',
            ] as $col) {
                if (Schema::hasColumn('support_tickets', $col)) {
                    $columnsToDrop[] = $col;
                }
            }

            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
