<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. CMS Website Appearance / Theme Customizer
        if (! Schema::hasTable('cms_website_themes')) {
            Schema::create('cms_website_themes', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('academy_id')->constrained('academies')->cascadeOnDelete();
                $table->string('name')->default('Default Theme');
                $table->boolean('is_published')->default(false)->index();
                $table->boolean('is_draft')->default(true);
                $table->json('settings')->nullable();
                $table->text('css_cache')->nullable();
                $table->timestamps();

                $table->index(['academy_id', 'is_published']);
            });
        }

        // 2. CMS Promotional Banners
        if (! Schema::hasTable('cms_banners')) {
            Schema::create('cms_banners', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('academy_id')->constrained('academies')->cascadeOnDelete();
                $table->string('name');
                $table->string('heading');
                $table->string('subtitle')->nullable();
                $table->text('description')->nullable();
                $table->string('placement')->default('homepage_hero')->index(); // homepage_hero, homepage_promo, courses_promo, services_promo, blog_promo
                $table->string('desktop_image_path')->nullable();
                $table->string('mobile_image_path')->nullable();
                $table->string('background_image_path')->nullable();
                $table->string('foreground_image_path')->nullable();
                $table->string('primary_cta_label')->nullable();
                $table->string('primary_cta_url')->nullable();
                $table->string('secondary_cta_label')->nullable();
                $table->string('secondary_cta_url')->nullable();
                $table->string('text_alignment')->default('left'); // left, center, right
                $table->string('text_color')->default('#ffffff');
                $table->string('overlay_color')->default('#000000');
                $table->integer('overlay_opacity')->default(40);
                $table->string('banner_height')->default('medium'); // compact, medium, tall
                $table->integer('display_order')->default(0);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();

                $table->index(['academy_id', 'placement', 'is_active']);
            });
        }

        // 3. CMS Chatbot Configuration & Knowledge Settings
        if (! Schema::hasTable('cms_chatbot_settings')) {
            Schema::create('cms_chatbot_settings', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('academy_id')->constrained('academies')->cascadeOnDelete();
                $table->boolean('is_enabled')->default(true);
                $table->string('name')->default('SEO Academy Assistant');
                $table->text('welcome_message')->nullable();
                $table->text('initial_greeting')->nullable();
                $table->string('avatar_path')->nullable();
                $table->string('brand_color')->default('#2563eb');
                $table->string('header_color')->default('#1e293b');
                $table->string('text_color')->default('#ffffff');
                $table->string('position')->default('bottom_right'); // bottom_right, bottom_left
                $table->string('launcher_icon')->default('message-circle'); // message-circle, bot, sparkle, help-circle
                $table->boolean('desktop_visibility')->default(true);
                $table->boolean('mobile_visibility')->default(true);
                $table->json('suggested_questions')->nullable();
                $table->text('offline_message')->nullable();
                $table->string('contact_support_cta')->default('Contact Admissions Desk');

                // Optional AI Integration parameters
                $table->boolean('ai_enabled')->default(false);
                $table->string('ai_provider')->default('null');
                $table->string('ai_endpoint')->nullable();
                $table->string('ai_model')->nullable();
                $table->text('ai_api_key')->nullable();
                $table->float('ai_temperature')->default(0.7);
                $table->integer('ai_max_tokens')->default(500);
                $table->timestamps();

                $table->unique('academy_id');
            });
        }

        // 4. CMS Chatbot Conversations & Audit Logs
        if (! Schema::hasTable('cms_chatbot_logs')) {
            Schema::create('cms_chatbot_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('academy_id')->constrained('academies')->cascadeOnDelete();
                $table->string('session_id')->index();
                $table->text('question');
                $table->text('answer');
                $table->string('source')->default('faq'); // faq, course_discovery, service_discovery, ai, fallback
                $table->boolean('is_answered')->default(true);
                $table->boolean('is_handoff')->default(false);
                $table->boolean('selected_suggested_question')->default(false);
                $table->string('ip_hash')->nullable();
                $table->timestamps();

                $table->index(['academy_id', 'created_at']);
                $table->index(['academy_id', 'is_answered']);
                $table->index(['academy_id', 'is_handoff']);
            });
        }

        // 5. Enhance ai_faqs if missing display_order
        if (Schema::hasTable('ai_faqs') && ! Schema::hasColumn('ai_faqs', 'display_order')) {
            Schema::table('ai_faqs', function (Blueprint $table) {
                $table->integer('display_order')->default(0)->after('is_active');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_chatbot_logs');
        Schema::dropIfExists('cms_chatbot_settings');
        Schema::dropIfExists('cms_banners');
        Schema::dropIfExists('cms_website_themes');
        if (Schema::hasTable('ai_faqs') && Schema::hasColumn('ai_faqs', 'display_order')) {
            Schema::table('ai_faqs', function (Blueprint $table) {
                $table->dropColumn('display_order');
            });
        }
    }
};
