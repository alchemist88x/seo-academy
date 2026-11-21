<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. CMS Pages Table
        Schema::create('cms_pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('academy_id')->index();
            $table->string('title');
            $table->string('slug');
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->string('template')->default('default'); // default, full_width, landing
            $table->string('status')->default('draft')->index(); // draft, published
            $table->timestamp('published_at')->nullable()->index();
            $table->integer('display_order')->default(0);
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->string('og_image_path')->nullable();
            $table->string('canonical_url')->nullable();
            $table->boolean('is_indexable')->default(true);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['academy_id', 'slug']);
        });

        // 2. CMS Homepage Sections Table
        Schema::create('cms_homepage_sections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('academy_id')->index();
            $table->string('section_key'); // hero, stats, featured_courses, introduction, services, why_us, instructors, faq, cta_banner
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->json('content')->nullable();
            $table->integer('display_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->string('background_color')->nullable();
            $table->string('background_image_path')->nullable();
            $table->timestamps();

            $table->unique(['academy_id', 'section_key']);
        });

        // 3. CMS Services Table
        Schema::create('cms_services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('academy_id')->index();
            $table->string('title');
            $table->string('slug');
            $table->string('icon')->default('search');
            $table->text('short_description')->nullable();
            $table->longText('full_description')->nullable();
            $table->json('benefits')->nullable();
            $table->string('cta_label')->nullable()->default('Inquire Now');
            $table->string('cta_url')->nullable();
            $table->integer('display_order')->default(0);
            $table->boolean('is_published')->default(true)->index();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamps();

            $table->unique(['academy_id', 'slug']);
        });

        // 4. CMS Blog Categories Table
        Schema::create('cms_blog_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('academy_id')->index();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->unique(['academy_id', 'slug']);
        });

        // 5. CMS Blog Posts Table
        Schema::create('cms_blog_posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('academy_id')->index();
            $table->uuid('category_id')->nullable()->index();
            $table->uuid('author_id')->nullable()->index();
            $table->string('title');
            $table->string('slug');
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->string('featured_image_path')->nullable();
            $table->string('status')->default('draft')->index(); // draft, published
            $table->timestamp('published_at')->nullable()->index();
            $table->integer('reading_time_minutes')->default(5);
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->string('og_image_path')->nullable();
            $table->string('canonical_url')->nullable();
            $table->boolean('is_indexable')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['academy_id', 'slug']);
        });

        // 6. CMS Navigation Items Table
        Schema::create('cms_navigation_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('academy_id')->index();
            $table->string('menu_location')->default('header')->index(); // header, footer
            $table->string('label');
            $table->string('url')->nullable();
            $table->string('route_name')->nullable();
            $table->string('target')->default('_self'); // _self, _blank
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->uuid('parent_id')->nullable();
            $table->timestamps();
        });

        // 7. CMS Media Assets Table
        Schema::create('cms_media_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('academy_id')->index();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('disk')->default('public');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->default(0);
            $table->string('alt_text')->nullable();
            $table->string('title')->nullable();
            $table->uuid('uploader_id')->nullable();
            $table->timestamps();
        });

        // 8. CMS Contact Inquiries Table
        Schema::create('cms_contact_inquiries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('academy_id')->index();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('subject');
            $table->text('message');
            $table->string('interested_service')->nullable();
            $table->string('status')->default('new')->index(); // new, read, in_progress, resolved, archived
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });

        // 9. CMS Redirects Table
        Schema::create('cms_redirects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('academy_id')->index();
            $table->string('source_path');
            $table->string('target_path');
            $table->integer('status_code')->default(301); // 301, 302
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['academy_id', 'source_path']);
        });

        // 10. Add marketing columns to courses table if not present
        Schema::table('courses', function (Blueprint $table) {
            if (! Schema::hasColumn('courses', 'is_marketing_featured')) {
                $table->boolean('is_marketing_featured')->default(false)->after('status')->index();
            }
            if (! Schema::hasColumn('courses', 'marketing_description')) {
                $table->text('marketing_description')->nullable()->after('is_marketing_featured');
            }
            if (! Schema::hasColumn('courses', 'marketing_highlights')) {
                $table->json('marketing_highlights')->nullable()->after('marketing_description');
            }
            if (! Schema::hasColumn('courses', 'seo_title')) {
                $table->string('seo_title')->nullable()->after('marketing_highlights');
            }
            if (! Schema::hasColumn('courses', 'seo_description')) {
                $table->text('seo_description')->nullable()->after('seo_title');
            }
            if (! Schema::hasColumn('courses', 'canonical_url')) {
                $table->string('canonical_url')->nullable()->after('seo_description');
            }
            if (! Schema::hasColumn('courses', 'is_public_listed')) {
                $table->boolean('is_public_listed')->default(true)->after('canonical_url')->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $columns = [
                'is_marketing_featured',
                'marketing_description',
                'marketing_highlights',
                'seo_title',
                'seo_description',
                'canonical_url',
                'is_public_listed',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('courses', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('cms_redirects');
        Schema::dropIfExists('cms_contact_inquiries');
        Schema::dropIfExists('cms_media_assets');
        Schema::dropIfExists('cms_navigation_items');
        Schema::dropIfExists('cms_blog_posts');
        Schema::dropIfExists('cms_blog_categories');
        Schema::dropIfExists('cms_services');
        Schema::dropIfExists('cms_homepage_sections');
        Schema::dropIfExists('cms_pages');
    }
};
