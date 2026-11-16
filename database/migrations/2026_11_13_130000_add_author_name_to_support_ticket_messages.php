<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // On SQLite foreign key constraint modifications require recreation,
        // but in Laravel we can update user_id to be nullable or retain user_id as nullable with set null.
        if (Schema::hasTable('support_ticket_messages')) {
            Schema::table('support_ticket_messages', function (Blueprint $table) {
                // Add author_name for permanent attribution retention if user account is ever deleted
                if (! Schema::hasColumn('support_ticket_messages', 'author_name')) {
                    $table->string('author_name')->nullable()->after('user_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('support_ticket_messages')) {
            Schema::table('support_ticket_messages', function (Blueprint $table) {
                if (Schema::hasColumn('support_ticket_messages', 'author_name')) {
                    $table->dropColumn('author_name');
                }
            });
        }
    }
};
