<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academies', function (Blueprint $table) {
            $table->string('logo_path', 255)->nullable()->after('description');
            $table->string('favicon_path', 255)->nullable()->after('logo_path');
            $table->string('primary_color', 20)->default('#4f46e5')->after('favicon_path');
            $table->string('secondary_color', 20)->default('#0f172a')->after('primary_color');
            $table->string('support_email', 255)->nullable()->after('secondary_color');
            $table->string('contact_phone', 50)->nullable()->after('support_email');
            $table->string('website_url', 255)->nullable()->after('contact_phone');
            $table->text('address')->nullable()->after('website_url');
            $table->string('timezone', 64)->default('UTC')->after('address');
            $table->string('locale', 16)->default('en')->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('academies', function (Blueprint $table) {
            $table->dropColumn([
                'logo_path',
                'favicon_path',
                'primary_color',
                'secondary_color',
                'support_email',
                'contact_phone',
                'website_url',
                'address',
                'timezone',
                'locale',
            ]);
        });
    }
};
