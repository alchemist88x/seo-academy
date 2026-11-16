<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create question_banks table
        if (! Schema::hasTable('question_banks')) {
            Schema::create('question_banks', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('academy_id')->constrained('academies')->cascadeOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('category', 128)->nullable();
                $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 32)->default('active');
                $table->timestamps();

                $table->index(['academy_id', 'status']);
                $table->index(['academy_id', 'category']);
            });
        }

        // 2. Create questions table
        if (! Schema::hasTable('questions')) {
            Schema::create('questions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('academy_id')->constrained('academies')->cascadeOnDelete();
                $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('question_text');
                $table->string('question_type', 32)->default('single_choice');
                $table->json('options')->nullable();
                $table->text('correct_answer')->nullable();
                $table->text('explanation')->nullable();
                $table->text('rubric')->nullable();
                $table->integer('points')->default(1);
                $table->string('difficulty', 32)->default('medium');
                $table->string('category', 128)->nullable();
                $table->json('tags')->nullable();
                $table->string('status', 32)->default('active');
                $table->timestamps();

                $table->index(['academy_id', 'status']);
                $table->index(['academy_id', 'difficulty']);
                $table->index(['academy_id', 'category']);
                $table->index(['academy_id', 'question_type']);
            });
        }

        // 3. Create question_bank_questions pivot table
        if (! Schema::hasTable('question_bank_questions')) {
            Schema::create('question_bank_questions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('question_bank_id')->constrained('question_banks')->cascadeOnDelete();
                $table->foreignUuid('question_id')->constrained('questions')->cascadeOnDelete();
                $table->integer('display_order')->default(0);
                $table->timestamps();

                $table->unique(['question_bank_id', 'question_id']);
                $table->index(['question_bank_id', 'display_order']);
            });
        }

        // 4. Create quiz_question_pools table
        if (! Schema::hasTable('quiz_question_pools')) {
            Schema::create('quiz_question_pools', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('academy_id')->constrained('academies')->cascadeOnDelete();
                $table->foreignUuid('quiz_id')->constrained('quizzes')->cascadeOnDelete();
                $table->foreignUuid('question_bank_id')->constrained('question_banks')->cascadeOnDelete();
                $table->unsignedInteger('question_count')->default(1);
                $table->string('difficulty', 32)->nullable();
                $table->string('category', 128)->nullable();
                $table->string('question_type', 32)->nullable();
                $table->unsignedInteger('points_per_question')->nullable();
                $table->integer('display_order')->default(0);
                $table->timestamps();

                $table->index('quiz_id');
                $table->index('question_bank_id');
            });
        }

        // 5. Update quizzes table with pool & randomization settings
        Schema::table('quizzes', function (Blueprint $table) {
            if (! Schema::hasColumn('quizzes', 'randomize_questions')) {
                $table->boolean('randomize_questions')->default(false)->after('status');
            }
            if (! Schema::hasColumn('quizzes', 'randomize_order')) {
                $table->boolean('randomize_order')->default(false)->after('randomize_questions');
            }
            if (! Schema::hasColumn('quizzes', 'question_source')) {
                $table->string('question_source', 32)->default('direct')->after('randomize_order');
            }
        });

        // 6. Update assessment_attempts table with snapshot columns
        Schema::table('assessment_attempts', function (Blueprint $table) {
            if (! Schema::hasColumn('assessment_attempts', 'selected_question_ids')) {
                $table->json('selected_question_ids')->nullable()->after('status');
            }
            if (! Schema::hasColumn('assessment_attempts', 'question_snapshots')) {
                $table->json('question_snapshots')->nullable()->after('selected_question_ids');
            }
        });

        // 7. Update assessment_attempt_answers table for question snapshots and bank questions
        if (Schema::hasTable('assessment_attempt_answers')) {
            $driver = DB::getDriverName();

            if ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = OFF;');

                Schema::dropIfExists('assessment_attempt_answers_temp');

                Schema::create('assessment_attempt_answers_temp', function (Blueprint $table) {
                    $table->uuid('id')->primary();
                    $table->foreignUuid('attempt_id')->constrained('assessment_attempts')->cascadeOnDelete();
                    $table->uuid('question_id')->nullable();
                    $table->foreignUuid('bank_question_id')->nullable()->constrained('questions')->nullOnDelete();
                    $table->longText('student_answer')->nullable();
                    $table->boolean('is_correct')->nullable();
                    $table->decimal('points_awarded', 8, 2)->default(0);
                    $table->decimal('points_possible', 8, 2)->default(1);
                    $table->text('evaluator_feedback')->nullable();
                    $table->foreignUuid('evaluated_by')->nullable()->constrained('users')->nullOnDelete();
                    $table->dateTime('evaluated_at')->nullable();
                    $table->json('question_snapshot')->nullable();
                    $table->timestamps();

                    $table->index(['attempt_id', 'question_id']);
                    $table->index(['attempt_id', 'bank_question_id']);
                });

                DB::statement('INSERT INTO assessment_attempt_answers_temp (id, attempt_id, question_id, student_answer, is_correct, points_awarded, points_possible, evaluator_feedback, evaluated_by, evaluated_at, created_at, updated_at) SELECT id, attempt_id, question_id, student_answer, is_correct, points_awarded, points_possible, evaluator_feedback, evaluated_by, evaluated_at, created_at, updated_at FROM assessment_attempt_answers');

                Schema::drop('assessment_attempt_answers');
                Schema::rename('assessment_attempt_answers_temp', 'assessment_attempt_answers');

                DB::statement('PRAGMA foreign_keys = ON;');
            } else {
                Schema::table('assessment_attempt_answers', function (Blueprint $table) {
                    if (! Schema::hasColumn('assessment_attempt_answers', 'bank_question_id')) {
                        $table->foreignUuid('bank_question_id')->nullable()->after('question_id')->constrained('questions')->nullOnDelete();
                    }
                    if (! Schema::hasColumn('assessment_attempt_answers', 'question_snapshot')) {
                        $table->json('question_snapshot')->nullable()->after('evaluated_at');
                    }
                });

                // Loosen foreign key on question_id so attempts can store bank question IDs
                try {
                    Schema::table('assessment_attempt_answers', function (Blueprint $table) {
                        $table->dropForeign(['question_id']);
                    });
                } catch (Throwable) {
                    // Ignore if already dropped
                }

                Schema::table('assessment_attempt_answers', function (Blueprint $table) {
                    $table->uuid('question_id')->nullable()->change();
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            if (Schema::hasTable('assessment_attempt_answers')) {
                Schema::table('assessment_attempt_answers', function (Blueprint $table) {
                    if (Schema::hasColumn('assessment_attempt_answers', 'bank_question_id')) {
                        $table->dropForeign(['bank_question_id']);
                        $table->dropColumn('bank_question_id');
                    }
                    if (Schema::hasColumn('assessment_attempt_answers', 'question_snapshot')) {
                        $table->dropColumn('question_snapshot');
                    }
                });
            }
        }

        Schema::table('assessment_attempts', function (Blueprint $table) {
            if (Schema::hasColumn('assessment_attempts', 'question_snapshots')) {
                $table->dropColumn('question_snapshots');
            }
            if (Schema::hasColumn('assessment_attempts', 'selected_question_ids')) {
                $table->dropColumn('selected_question_ids');
            }
        });

        Schema::table('quizzes', function (Blueprint $table) {
            if (Schema::hasColumn('quizzes', 'question_source')) {
                $table->dropColumn('question_source');
            }
            if (Schema::hasColumn('quizzes', 'randomize_order')) {
                $table->dropColumn('randomize_order');
            }
            if (Schema::hasColumn('quizzes', 'randomize_questions')) {
                $table->dropColumn('randomize_questions');
            }
        });

        Schema::dropIfExists('quiz_question_pools');
        Schema::dropIfExists('question_bank_questions');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('question_banks');
    }
};
