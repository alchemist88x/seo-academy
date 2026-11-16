<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add soft deletes to support_tickets if not already present
        if (Schema::hasTable('support_tickets') && ! Schema::hasColumn('support_tickets', 'deleted_at')) {
            Schema::table('support_tickets', function (Blueprint $table) {
                $table->softDeletes();
                $table->index('deleted_at');
            });
        }

        // 2. Make user_id nullable on support_ticket_messages and nullOnDelete
        if (Schema::hasTable('support_ticket_messages')) {
            $driver = DB::getDriverName();

            if ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = OFF;');

                Schema::dropIfExists('support_ticket_messages_temp');

                Schema::create('support_ticket_messages_temp', function (Blueprint $table) {
                    $table->uuid('id')->primary();
                    $table->foreignUuid('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
                    $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->string('author_name')->nullable();
                    $table->text('message');
                    $table->boolean('is_internal')->default(false);
                    $table->timestamps();
                });

                DB::statement('INSERT INTO support_ticket_messages_temp (id, support_ticket_id, user_id, author_name, message, is_internal, created_at, updated_at) SELECT id, support_ticket_id, user_id, author_name, message, is_internal, created_at, updated_at FROM support_ticket_messages');

                Schema::drop('support_ticket_messages');
                Schema::rename('support_ticket_messages_temp', 'support_ticket_messages');

                Schema::table('support_ticket_messages', function (Blueprint $table) {
                    $table->index(['support_ticket_id', 'is_internal', 'created_at'], 'stm_ticket_internal_created_idx');
                });

                DB::statement('PRAGMA foreign_keys = ON;');
            } else {
                Schema::table('support_ticket_messages', function (Blueprint $table) {
                    $table->dropForeign('support_ticket_messages_user_id_foreign');
                });

                Schema::table('support_ticket_messages', function (Blueprint $table) {
                    $table->uuid('user_id')->nullable()->change();
                    $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
                });
            }

            // 3. Backfill missing author_name from users.name
            if (Schema::hasTable('users')) {
                $messages = DB::table('support_ticket_messages')
                    ->whereNotNull('user_id')
                    ->where(function ($query) {
                        $query->whereNull('author_name')
                            ->orWhere('author_name', '');
                    })
                    ->select('id', 'user_id')
                    ->get();

                foreach ($messages as $msg) {
                    $name = DB::table('users')->where('id', $msg->user_id)->value('name');
                    if ($name !== null) {
                        DB::table('support_ticket_messages')
                            ->where('id', $msg->id)
                            ->update(['author_name' => $name]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        // Safety Pre-Checks: Never fabricate authors and never expose soft-deleted tickets.
        $violations = [];

        if (Schema::hasTable('support_tickets') && Schema::hasColumn('support_tickets', 'deleted_at')) {
            $softDeletedCount = DB::table('support_tickets')->whereNotNull('deleted_at')->count();
            if ($softDeletedCount > 0) {
                $violations[] = "{$softDeletedCount} soft-deleted support ticket(s) exist. Rollback would drop 'deleted_at' and silently expose soft-deleted tickets.";
            }
        }

        if (Schema::hasTable('support_ticket_messages') && Schema::hasColumn('support_ticket_messages', 'user_id')) {
            $nullAuthorCount = DB::table('support_ticket_messages')->whereNull('user_id')->count();
            if ($nullAuthorCount > 0) {
                $violations[] = "{$nullAuthorCount} support message(s) have NULL user_id (author user was deleted). Rollback cannot proceed without fabricating authorship or deleting historical records.";
            }
        }

        if (! empty($violations)) {
            throw new RuntimeException(
                "Cannot rollback migration [2026_11_13_140000_make_user_id_nullable_on_support_ticket_messages] due to data safety constraints:\n".
                implode("\n", array_map(fn ($v) => "- {$v}", $violations))."\n".
                'Rollback aborted. Database schema and records remain unchanged.'
            );
        }

        // 1. Revert user_id nullable on support_ticket_messages
        if (Schema::hasTable('support_ticket_messages')) {
            $driver = DB::getDriverName();

            if ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = OFF;');

                Schema::dropIfExists('support_ticket_messages_temp');

                Schema::create('support_ticket_messages_temp', function (Blueprint $table) {
                    $table->uuid('id')->primary();
                    $table->foreignUuid('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
                    $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
                    $table->string('author_name')->nullable();
                    $table->text('message');
                    $table->boolean('is_internal')->default(false);
                    $table->timestamps();
                });

                // All messages are copied with historical authorship preserved
                DB::statement('INSERT INTO support_ticket_messages_temp (id, support_ticket_id, user_id, author_name, message, is_internal, created_at, updated_at) SELECT id, support_ticket_id, user_id, author_name, message, is_internal, created_at, updated_at FROM support_ticket_messages');

                Schema::drop('support_ticket_messages');
                Schema::rename('support_ticket_messages_temp', 'support_ticket_messages');

                Schema::table('support_ticket_messages', function (Blueprint $table) {
                    $table->index(['support_ticket_id', 'is_internal', 'created_at'], 'stm_ticket_internal_created_idx');
                });

                DB::statement('PRAGMA foreign_keys = ON;');
            } else {
                Schema::table('support_ticket_messages', function (Blueprint $table) {
                    $table->dropForeign(['user_id']);
                });

                Schema::table('support_ticket_messages', function (Blueprint $table) {
                    $table->uuid('user_id')->nullable(false)->change();
                    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                });
            }
        }

        // 2. Drop soft deletes from support_tickets
        if (Schema::hasTable('support_tickets') && Schema::hasColumn('support_tickets', 'deleted_at')) {
            Schema::table('support_tickets', function (Blueprint $table) {
                try {
                    $table->dropIndex(['deleted_at']);
                } catch (Throwable) {
                }

                $table->dropSoftDeletes();
            });
        }
    }
};
