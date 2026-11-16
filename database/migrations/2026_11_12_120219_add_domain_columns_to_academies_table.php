<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academies', function (Blueprint $table) {
            $table->string('subdomain', 63)->nullable()->unique()->after('slug');
            $table->string('custom_domain', 255)->nullable()->unique()->after('subdomain');
        });
    }

    public function down(): void
    {
        Schema::table('academies', function (Blueprint $table) {
            $table->dropUnique(['subdomain']);
            $table->dropUnique(['custom_domain']);
            $table->dropColumn(['subdomain', 'custom_domain']);
        });
    }
};
