<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('certificate_templates')) {
            Schema::create('certificate_templates', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('academy_id')->index();
                $table->string('name');
                $table->string('slug')->index();
                $table->text('description')->nullable();
                $table->boolean('is_default')->default(false)->index();
                $table->boolean('is_active')->default(true)->index();
                $table->boolean('is_archived')->default(false)->index();
                $table->uuid('current_version_id')->nullable();
                $table->string('page_size', 20)->default('A4');
                $table->string('orientation', 20)->default('landscape');
                $table->string('background_color', 30)->default('#ffffff');
                $table->string('background_image_path')->nullable();
                $table->string('border_design', 50)->default('double');
                $table->json('design_data')->nullable();
                $table->uuid('created_by')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('certificate_template_versions')) {
            Schema::create('certificate_template_versions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('template_id')->index();
                $table->unsignedInteger('version_number');
                $table->string('status', 20)->default('published')->index();
                $table->json('design_data');
                $table->string('change_summary')->nullable();
                $table->uuid('created_by')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('certificates')) {
            Schema::table('certificates', function (Blueprint $table) {
                if (! Schema::hasColumn('certificates', 'certificate_template_id')) {
                    $table->uuid('certificate_template_id')->nullable()->index();
                }
                if (! Schema::hasColumn('certificates', 'certificate_template_version_id')) {
                    $table->uuid('certificate_template_version_id')->nullable()->index();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('certificates')) {
            Schema::table('certificates', function (Blueprint $table) {
                if (Schema::hasColumn('certificates', 'certificate_template_version_id')) {
                    $table->dropColumn('certificate_template_version_id');
                }
                if (Schema::hasColumn('certificates', 'certificate_template_id')) {
                    $table->dropColumn('certificate_template_id');
                }
            });
        }

        Schema::dropIfExists('certificate_template_versions');
        Schema::dropIfExists('certificate_templates');
    }
};
