<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('cms_contact_inquiries')) {
            Schema::table('cms_contact_inquiries', function (Blueprint $table) {
                if (! Schema::hasColumn('cms_contact_inquiries', 'source')) {
                    $table->string('source')->default('contact_form')->index();
                }
                if (! Schema::hasColumn('cms_contact_inquiries', 'conversation_id')) {
                    $table->uuid('conversation_id')->nullable()->index();
                }
                if (! Schema::hasColumn('cms_contact_inquiries', 'assigned_to_user_id')) {
                    $table->string('assigned_to_user_id')->nullable()->index();
                }
                if (! Schema::hasColumn('cms_contact_inquiries', 'follow_up_date')) {
                    $table->date('follow_up_date')->nullable()->index();
                }
                if (! Schema::hasColumn('cms_contact_inquiries', 'phone_country_code')) {
                    $table->string('phone_country_code', 10)->nullable();
                }
                if (! Schema::hasColumn('cms_contact_inquiries', 'phone_formatted')) {
                    $table->string('phone_formatted', 50)->nullable();
                }
                if (! Schema::hasColumn('cms_contact_inquiries', 'course_interest')) {
                    $table->string('course_interest')->nullable();
                }
                if (! Schema::hasColumn('cms_contact_inquiries', 'consent_text')) {
                    $table->text('consent_text')->nullable();
                }
                if (! Schema::hasColumn('cms_contact_inquiries', 'consented_at')) {
                    $table->timestamp('consented_at')->nullable();
                }
                if (! Schema::hasColumn('cms_contact_inquiries', 'consent_version')) {
                    $table->string('consent_version', 20)->nullable()->default('v1.0');
                }
                if (! Schema::hasColumn('cms_contact_inquiries', 'activity_history')) {
                    $table->json('activity_history')->nullable();
                }
            });

            // Map any legacy statuses safely: read -> contacted, resolved/archived -> closed
            DB::table('cms_contact_inquiries')->where('status', 'read')->update(['status' => 'contacted']);
            DB::table('cms_contact_inquiries')->whereIn('status', ['resolved', 'archived'])->update(['status' => 'closed']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('cms_contact_inquiries')) {
            Schema::table('cms_contact_inquiries', function (Blueprint $table) {
                $columns = [
                    'source',
                    'conversation_id',
                    'assigned_to_user_id',
                    'follow_up_date',
                    'phone_country_code',
                    'phone_formatted',
                    'course_interest',
                    'consent_text',
                    'consented_at',
                    'consent_version',
                    'activity_history',
                ];

                foreach ($columns as $column) {
                    if (Schema::hasColumn('cms_contact_inquiries', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
