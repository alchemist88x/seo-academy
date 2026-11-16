<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->foreignUuid('enrollment_id')->nullable()->unique()->after('course_id')->constrained('enrollments')->nullOnDelete();
            $table->string('verification_code', 64)->nullable()->unique()->after('certificate_number');
            $table->string('template', 50)->default('classic')->after('verification_code');
            $table->string('pdf_path', 500)->nullable()->after('template');
            $table->timestamp('revoked_at')->nullable()->after('status');
            $table->foreignUuid('revoked_by')->nullable()->after('revoked_at')->constrained('users')->nullOnDelete();
            $table->text('revocation_reason')->nullable()->after('revoked_by');
            $table->timestamp('updated_at')->nullable()->after('created_at');

            $table->index(['enrollment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropForeign(['enrollment_id']);
            $table->dropForeign(['revoked_by']);
            $table->dropIndex(['enrollment_id', 'status']);
            $table->dropUnique(['enrollment_id']);
            $table->dropUnique(['verification_code']);
            $table->dropColumn([
                'enrollment_id',
                'verification_code',
                'template',
                'pdf_path',
                'revoked_at',
                'revoked_by',
                'revocation_reason',
                'updated_at',
            ]);
        });
    }
};
