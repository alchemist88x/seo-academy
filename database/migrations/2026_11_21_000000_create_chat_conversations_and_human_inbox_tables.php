<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Settings additions for Human Support & Operating Modes
        if (Schema::hasTable('cms_chatbot_settings')) {
            Schema::table('cms_chatbot_settings', function (Blueprint $table) {
                if (! Schema::hasColumn('cms_chatbot_settings', 'default_operating_mode')) {
                    $table->string('default_operating_mode')->default('ai_enabled')->after('is_enabled'); // ai_enabled, fallback_only, human_support
                }
                if (! Schema::hasColumn('cms_chatbot_settings', 'human_support_enabled')) {
                    $table->boolean('human_support_enabled')->default(true)->after('default_operating_mode');
                }
                if (! Schema::hasColumn('cms_chatbot_settings', 'support_offline_message')) {
                    $table->text('support_offline_message')->nullable()->after('human_support_enabled');
                }
            });
        }

        // 2. Chat Conversations Table
        if (! Schema::hasTable('cms_chat_conversations')) {
            Schema::create('cms_chat_conversations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('academy_id')->index();
                $table->string('session_id')->index();
                $table->string('visitor_name')->nullable();
                $table->string('visitor_email')->nullable();
                $table->string('visitor_phone')->nullable();
                $table->string('status')->default('open')->index(); // open, waiting_for_support, assigned, waiting_for_visitor, resolved
                $table->string('assigned_to_user_id')->nullable()->index();
                $table->string('operating_mode')->default('ai_enabled'); // ai_enabled, fallback_only, human_support
                $table->uuid('lead_inquiry_id')->nullable()->index();
                $table->timestamp('last_activity_at')->nullable()->index();
                $table->timestamp('resolved_at')->nullable();
                $table->string('resolved_by_user_id')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['academy_id', 'status']);
                $table->index(['academy_id', 'last_activity_at']);
            });
        }

        // 3. Chat Messages Table
        if (! Schema::hasTable('cms_chat_messages')) {
            Schema::create('cms_chat_messages', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('conversation_id')->index();
                $table->string('sender_type')->default('visitor')->index(); // visitor, bot, admin, support_staff, system
                $table->string('sender_id')->nullable()->index();
                $table->string('sender_name')->nullable();
                $table->text('message');
                $table->boolean('is_internal')->default(false)->index();
                $table->string('source')->default('chat'); // ai, faq, fallback, human, system, lead_capture
                $table->json('structured_data')->nullable();
                $table->boolean('is_read')->default(false);
                $table->string('client_ip')->nullable();
                $table->timestamps();

                $table->index(['conversation_id', 'created_at']);
                $table->index(['conversation_id', 'is_internal']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_chat_messages');
        Schema::dropIfExists('cms_chat_conversations');

        if (Schema::hasTable('cms_chatbot_settings')) {
            Schema::table('cms_chatbot_settings', function (Blueprint $table) {
                $columns = ['default_operating_mode', 'human_support_enabled', 'support_offline_message'];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('cms_chatbot_settings', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
