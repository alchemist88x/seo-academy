<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cms_chatbot_settings')) {
            Schema::table('cms_chatbot_settings', function (Blueprint $table) {
                if (! Schema::hasColumn('cms_chatbot_settings', 'circuit_breaker_cooldown')) {
                    $table->unsignedInteger('circuit_breaker_cooldown')->default(300)->after('human_support_enabled');
                }
            });
        }

        if (Schema::hasTable('cms_chat_conversations')) {
            Schema::table('cms_chat_conversations', function (Blueprint $table) {
                if (! Schema::hasColumn('cms_chat_conversations', 'handler')) {
                    $table->string('handler')->default('ai')->after('status')->index(); // ai, human, fallback
                }
                if (! Schema::hasColumn('cms_chat_conversations', 'handoff_reason')) {
                    $table->string('handoff_reason')->nullable()->after('handler')->index(); // visitor_intent, visitor_button, ai_unavailable, ai_error, admin_takeover, staff_takeover, manual_reset
                }
                if (! Schema::hasColumn('cms_chat_conversations', 'handoff_at')) {
                    $table->timestamp('handoff_at')->nullable()->after('handoff_reason')->index();
                }
                if (! Schema::hasColumn('cms_chat_conversations', 'assigned_at')) {
                    $table->timestamp('assigned_at')->nullable()->after('assigned_to_user_id')->index();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cms_chatbot_settings')) {
            Schema::table('cms_chatbot_settings', function (Blueprint $table) {
                if (Schema::hasColumn('cms_chatbot_settings', 'circuit_breaker_cooldown')) {
                    $table->dropColumn('circuit_breaker_cooldown');
                }
            });
        }

        if (Schema::hasTable('cms_chat_conversations')) {
            Schema::table('cms_chat_conversations', function (Blueprint $table) {
                $columns = ['handler', 'handoff_reason', 'handoff_at', 'assigned_at'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('cms_chat_conversations', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
