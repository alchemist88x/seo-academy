<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Enhance cms_chatbot_settings with visibility, timeouts, and retention limits
        if (Schema::hasTable('cms_chatbot_settings')) {
            Schema::table('cms_chatbot_settings', function (Blueprint $table) {
                if (! Schema::hasColumn('cms_chatbot_settings', 'visibility_pages')) {
                    $table->json('visibility_pages')->nullable()->after('mobile_visibility');
                }
                if (! Schema::hasColumn('cms_chatbot_settings', 'ai_timeout')) {
                    $table->integer('ai_timeout')->default(15)->after('ai_max_tokens');
                }
                if (! Schema::hasColumn('cms_chatbot_settings', 'daily_request_limit')) {
                    $table->integer('daily_request_limit')->default(500)->after('ai_timeout');
                }
                if (! Schema::hasColumn('cms_chatbot_settings', 'retention_days')) {
                    $table->integer('retention_days')->default(90)->after('daily_request_limit');
                }
                if (! Schema::hasColumn('cms_chatbot_settings', 'rate_limit_per_minute')) {
                    $table->integer('rate_limit_per_minute')->default(20)->after('retention_days');
                }
                if (! Schema::hasColumn('cms_chatbot_settings', 'lead_capture_enabled')) {
                    $table->boolean('lead_capture_enabled')->default(true)->after('contact_support_cta');
                }
            });
        }

        // 2. Enhance cms_chatbot_logs with token usage, conversion, and structured payload
        if (Schema::hasTable('cms_chatbot_logs')) {
            Schema::table('cms_chatbot_logs', function (Blueprint $table) {
                if (! Schema::hasColumn('cms_chatbot_logs', 'tokens_used')) {
                    $table->integer('tokens_used')->default(0)->after('answer');
                }
                if (! Schema::hasColumn('cms_chatbot_logs', 'is_lead_converted')) {
                    $table->boolean('is_lead_converted')->default(false)->after('is_handoff');
                }
                if (! Schema::hasColumn('cms_chatbot_logs', 'lead_inquiry_id')) {
                    $table->uuid('lead_inquiry_id')->nullable()->after('is_lead_converted');
                }
                if (! Schema::hasColumn('cms_chatbot_logs', 'structured_data')) {
                    $table->json('structured_data')->nullable()->after('lead_inquiry_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cms_chatbot_logs')) {
            Schema::table('cms_chatbot_logs', function (Blueprint $table) {
                $columns = ['tokens_used', 'is_lead_converted', 'lead_inquiry_id', 'structured_data'];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('cms_chatbot_logs', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('cms_chatbot_settings')) {
            Schema::table('cms_chatbot_settings', function (Blueprint $table) {
                $columns = ['visibility_pages', 'ai_timeout', 'daily_request_limit', 'retention_days', 'rate_limit_per_minute', 'lead_capture_enabled'];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('cms_chatbot_settings', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
