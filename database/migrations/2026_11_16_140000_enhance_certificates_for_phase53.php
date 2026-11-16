<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->json('issuance_snapshot')->nullable()->after('template');
            $table->string('pdf_generation_status', 30)->default('pending')->after('pdf_path');
            $table->timestamp('pdf_generated_at')->nullable()->after('pdf_generation_status');
            $table->text('pdf_generation_error')->nullable()->after('pdf_generated_at');

            $table->boolean('public_identity_consent')->default(false)->after('requires_audit');
            $table->timestamp('consent_recorded_at')->nullable()->after('public_identity_consent');
            $table->timestamp('consent_withdrawn_at')->nullable()->after('consent_recorded_at');

            $table->unsignedInteger('version')->default(1)->after('id');
            $table->foreignUuid('supersedes_id')->nullable()->after('version')->constrained('certificates')->nullOnDelete();
            $table->foreignUuid('superseded_by_id')->nullable()->after('supersedes_id')->constrained('certificates')->nullOnDelete();

            $table->timestamp('expires_at')->nullable()->after('issued_at');
            $table->string('verification_token_hash', 64)->nullable()->after('verification_code');

            $table->index('verification_token_hash');
            $table->index('pdf_generation_status');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropForeign(['supersedes_id']);
            $table->dropForeign(['superseded_by_id']);

            $table->dropIndex(['verification_token_hash']);
            $table->dropIndex(['pdf_generation_status']);

            $table->dropColumn([
                'version',
                'supersedes_id',
                'superseded_by_id',
                'issuance_snapshot',
                'pdf_generation_status',
                'pdf_generated_at',
                'pdf_generation_error',
                'public_identity_consent',
                'consent_recorded_at',
                'consent_withdrawn_at',
                'expires_at',
                'verification_token_hash',
            ]);
        });
    }
};
