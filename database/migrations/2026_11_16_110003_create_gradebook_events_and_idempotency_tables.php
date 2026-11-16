<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Grade override events (immutable audit trail)
        Schema::create('grade_override_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->string('assessment_type', 50); // quiz, assignment, exam, final_grade
            $table->uuid('assessment_id')->nullable();
            $table->decimal('previous_score', 5, 2)->nullable();
            $table->decimal('new_score', 5, 2);
            $table->text('reason');
            $table->foreignUuid('overridden_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['enrollment_id', 'created_at']);
            $table->index(['assessment_type', 'assessment_id']);
        });

        // 2. Assessment result publication events (immutable audit trail)
        Schema::create('assessment_result_publication_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->string('assessment_type', 50); // quiz, assignment, exam
            $table->uuid('assessment_id');
            $table->decimal('raw_score', 5, 2);
            $table->decimal('scaled_score', 5, 2)->nullable();
            $table->boolean('is_passing')->nullable();
            $table->foreignUuid('published_by')->constrained('users')->restrictOnDelete();
            $table->json('publication_metadata')->nullable();
            $table->timestamp('published_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['enrollment_id', 'assessment_type', 'assessment_id'], 'asmt_pub_events_enrollment_asmt_index');
            $table->index(['published_at']);
        });

        // 3. Grade lifecycle events (immutable audit trail)
        Schema::create('enrollment_grade_lifecycle_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->string('event_type', 50); // draft_calculated, grade_published, grade_superseded, grade_withdrawn, grade_reinstated, override_applied
            $table->foreignUuid('grade_snapshot_id')->nullable()->constrained('enrollment_final_grades')->restrictOnDelete();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['enrollment_id', 'event_type', 'created_at'], 'grade_lifecycle_enrollment_type_index');
        });

        // 4. Idempotency records (atomic reservation and idempotency guarantee)
        Schema::create('idempotency_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('actor_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 100);
            $table->string('idempotency_key', 255);
            $table->string('resource_type', 100);
            $table->uuid('resource_id');
            $table->string('request_hash', 64); // SHA-256 canonical hash
            $table->string('ownership_token', 64);
            $table->string('status', 50)->default('reserved'); // reserved, completed, failed, released
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->json('response_body')->nullable();
            $table->timestamp('reserved_until');
            $table->timestamps();

            // Strict uniqueness per actor, action, and key
            $table->unique(['actor_id', 'action', 'idempotency_key'], 'idemp_actor_action_key_unique');
            $table->index(['resource_type', 'resource_id', 'status'], 'idemp_resource_status_index');
            $table->index(['status', 'reserved_until'], 'idemp_status_reserved_until_index');
        });

        // 5. Certificate academic audit lifecycle events and support columns
        Schema::create('certificate_audit_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('certificate_id')->constrained('certificates')->cascadeOnDelete();
            $table->string('event_type', 50); // audit_flagged, audit_cleared, revoked, reinstated
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['certificate_id', 'event_type', 'created_at'], 'cert_audit_events_cert_type_index');
        });

        if (Schema::hasTable('certificates')) {
            Schema::table('certificates', function (Blueprint $table) {
                if (! Schema::hasColumn('certificates', 'requires_audit')) {
                    $table->boolean('requires_audit')->default(false)->after('revocation_reason');
                }
                if (! Schema::hasColumn('certificates', 'audit_reason')) {
                    $table->text('audit_reason')->nullable()->after('requires_audit');
                }
                if (! Schema::hasColumn('certificates', 'audited_at')) {
                    $table->timestamp('audited_at')->nullable()->after('audit_reason');
                }
                if (! Schema::hasColumn('certificates', 'audited_by')) {
                    $table->foreignUuid('audited_by')->nullable()->after('audited_at')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('certificates', 'reinstated_at')) {
                    $table->timestamp('reinstated_at')->nullable()->after('audited_by');
                }
                if (! Schema::hasColumn('certificates', 'reinstated_by')) {
                    $table->foreignUuid('reinstated_by')->nullable()->after('reinstated_at')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('certificates', 'reinstatement_reason')) {
                    $table->text('reinstatement_reason')->nullable()->after('reinstated_by');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('certificates')) {
            Schema::table('certificates', function (Blueprint $table) {
                $columns = [
                    'reinstatement_reason',
                    'reinstated_by',
                    'reinstated_at',
                    'audited_by',
                    'audited_at',
                    'audit_reason',
                    'requires_audit',
                ];
                $toDrop = array_filter($columns, fn ($col) => Schema::hasColumn('certificates', $col));
                if (! empty($toDrop)) {
                    $table->dropForeign(['audited_by']);
                    $table->dropForeign(['reinstated_by']);
                    $table->dropColumn($toDrop);
                }
            });
        }

        Schema::dropIfExists('certificate_audit_events');
        Schema::dropIfExists('idempotency_records');
        Schema::dropIfExists('enrollment_grade_lifecycle_events');
        Schema::dropIfExists('assessment_result_publication_events');
        Schema::dropIfExists('grade_override_events');
    }
};
