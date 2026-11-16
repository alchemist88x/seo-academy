<?php

namespace Tests\Feature\Assessments;

use App\Domain\Academies\Models\Academy;
use App\Domain\Assessments\Services\AssessmentService;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Questions\Models\Question;
use App\Domain\Questions\Models\QuestionBank;
use App\Domain\Questions\Services\QuestionBankService;
use App\Domain\Quizzes\Models\Quiz;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Tenants\Services\TenantContext;
use App\Livewire\Admin\AssessmentsIndex;
use App\Livewire\Admin\QuestionBankManager;
use App\Livewire\Student\AssessmentPlayer;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class QuestionBankTest extends TestCase
{
    public static function engineProvider(): array
    {
        return [
            'sqlite' => ['sqlite'],
            'mysql' => ['mysql'],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cache.default' => 'array',
            'permission.cache.store' => 'array',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        app('cache')->setDefaultDriver('array');
        app('cache')->forgetDriver();
        $this->app->forgetInstance('cache');
        $this->app->forgetInstance(PermissionRegistrar::class);
    }

    protected function tearDown(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => true,
        ]);
        DB::purge('mysql');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        parent::tearDown();
    }

    private function useEngine(string $engine): void
    {
        config(['cache.default' => 'array', 'permission.cache.store' => 'array']);
        app('cache')->setDefaultDriver('array');
        app('cache')->forgetDriver();
        $this->app->forgetInstance('cache');
        $this->app->forgetInstance(PermissionRegistrar::class);

        if ($engine === 'sqlite') {
            config([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => ':memory:',
                'database.connections.sqlite.foreign_key_constraints' => true,
            ]);
            DB::purge('sqlite');
            DB::setDefaultConnection('sqlite');
        } else {
            config([
                'database.default' => 'mysql',
                'database.connections.mysql.host' => '127.0.0.1',
                'database.connections.mysql.port' => 3306,
                'database.connections.mysql.database' => 'seo_academy_test',
                'database.connections.mysql.username' => 'root',
                'database.connections.mysql.password' => '',
            ]);
            DB::purge('mysql');
            DB::setDefaultConnection('mysql');
            try {
                DB::connection('mysql')->getPdo();
            } catch (\Throwable $e) {
                $this->markTestSkipped('MySQL connection not available: '.$e->getMessage());
            }
        }

        Artisan::call('migrate:fresh', ['--database' => $engine, '--force' => true]);
        $this->seed(RoleSeeder::class);
    }

    private function createAcademy(): Academy
    {
        $academy = Academy::create([
            'name' => 'SEO Test Academy '.Str::random(4),
            'slug' => 'seo-test-'.Str::random(6),
            'subdomain' => 'test-'.Str::random(6),
            'status' => 'active',
            'is_active' => true,
        ]);

        app(TenantContext::class)->setAcademy($academy);

        return $academy;
    }

    private function createUserWithRole(string $role, Academy $academy): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);
        $user->academies()->attach($academy->id, [
            'id' => (string) Str::uuid(),
            'role' => $role,
            'status' => 'active',
            'created_at' => now(),
        ]);

        return $user;
    }

    private function createHarness(Academy $academy): array
    {
        $course = Course::factory()->create(['academy_id' => $academy->id]);
        $module = Module::create([
            'academy_id' => $academy->id,
            'course_id' => $course->id,
            'title' => 'Core Module',
            'display_order' => 1,
            'status' => 'Active',
            'completion_rule' => 'all_lessons',
        ]);
        $lesson = Lesson::create([
            'academy_id' => $academy->id,
            'module_id' => $module->id,
            'title' => 'Core Lesson',
            'type' => 'text',
            'display_order' => 1,
            'status' => 'Active',
            'visibility' => 'enrolled',
            'is_mandatory' => true,
        ]);

        $studentUser = $this->createUserWithRole('student', $academy);

        $profile = StudentProfile::factory()->create([
            'academy_id' => $academy->id,
            'user_id' => $studentUser->id,
        ]);

        $enrollment = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $profile->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now()->toDateString(),
        ]);

        return [$course, $module, $lesson, $studentUser, $profile, $enrollment];
    }

    #[DataProvider('engineProvider')]
    public function test_question_bank_crud_and_lifecycle(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createUserWithRole('admin', $academy);

        $service = app(QuestionBankService::class);

        // 1. Create bank
        $bank = $service->createBank($academy, [
            'name' => 'Technical SEO Fundamentals',
            'description' => 'Core crawling, indexing and rendering questions.',
            'category' => 'Technical SEO',
            'status' => QuestionBank::STATUS_ACTIVE,
        ], $admin);

        $this->assertDatabaseHas('question_banks', [
            'id' => $bank->id,
            'academy_id' => $academy->id,
            'name' => 'Technical SEO Fundamentals',
            'created_by' => $admin->id,
            'status' => 'active',
        ]);

        // 2. Update bank
        $updated = $service->updateBank($bank, [
            'name' => 'Advanced Technical SEO',
            'description' => 'Updated description',
        ]);
        $this->assertEquals('Advanced Technical SEO', $updated->name);

        // 3. Archive & restore bank
        $service->archiveBank($bank);
        $this->assertEquals('archived', $bank->fresh()->status);
        $this->assertFalse($bank->fresh()->isActive());

        $service->restoreBank($bank);
        $this->assertEquals('active', $bank->fresh()->status);
        $this->assertTrue($bank->fresh()->isActive());

        // 4. Delete bank
        $service->deleteBank($bank);
        $this->assertDatabaseMissing('question_banks', ['id' => $bank->id]);
    }

    #[DataProvider('engineProvider')]
    public function test_question_creation_and_supported_types(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createUserWithRole('admin', $academy);
        $service = app(QuestionBankService::class);

        $bank = $service->createBank($academy, ['name' => 'SEO Assessment Bank'], $admin);

        // 1. Single Choice Question
        $qSingle = $service->createQuestion($academy, [
            'question_text' => 'What does canonical tag signify?',
            'question_type' => Question::TYPE_SINGLE_CHOICE,
            'options' => [
                ['key' => 'a', 'text' => 'Preferred URL for duplicates'],
                ['key' => 'b', 'text' => 'Redirect to new domain'],
                ['key' => 'c', 'text' => 'Block indexing'],
            ],
            'correct_answer' => 'a',
            'points' => 2,
            'difficulty' => Question::DIFFICULTY_EASY,
            'category' => 'Technical SEO',
            'tags' => ['canonical', 'duplicate-content'],
            'explanation' => 'A canonical tag specifies the preferred master copy of a page.',
        ], $bank, $admin);

        $this->assertDatabaseHas('questions', [
            'id' => $qSingle->id,
            'question_type' => 'single_choice',
            'points' => 2,
            'difficulty' => 'easy',
        ]);
        $this->assertCount(1, $bank->questions);

        // 2. Multiple Choice Question
        $qMulti = $service->createQuestion($academy, [
            'question_text' => 'Which of the following are HTTP status codes for redirects?',
            'question_type' => Question::TYPE_MULTIPLE_CHOICE,
            'options' => [
                ['key' => '301', 'text' => '301 Moved Permanently'],
                ['key' => '302', 'text' => '302 Found'],
                ['key' => '404', 'text' => '404 Not Found'],
                ['key' => '500', 'text' => '500 Internal Error'],
            ],
            'correct_answer' => ['301', '302'],
            'points' => 3,
            'difficulty' => Question::DIFFICULTY_MEDIUM,
            'category' => 'HTTP',
        ], $bank, $admin);

        $this->assertEquals(['301', '302'], $qMulti->correct_answer);

        // 3. True / False Question
        $qTF = $service->createQuestion($academy, [
            'question_text' => 'Robots.txt prevents pages from being indexed by search engines.',
            'question_type' => Question::TYPE_TRUE_FALSE,
            'options' => [
                ['key' => 'true', 'text' => 'True'],
                ['key' => 'false', 'text' => 'False'],
            ],
            'correct_answer' => 'false',
            'points' => 1,
            'difficulty' => Question::DIFFICULTY_EASY,
            'explanation' => 'Robots.txt blocks crawling, not necessarily indexing if linked elsewhere.',
        ], $bank, $admin);

        $this->assertFalse($qTF->requiresManualGrading());

        // 4. Short Answer Question
        $qShort = $service->createQuestion($academy, [
            'question_text' => 'Name the file that informs crawlers about site structure in XML format.',
            'question_type' => Question::TYPE_SHORT_ANSWER,
            'correct_answer' => 'sitemap.xml',
            'points' => 2,
            'difficulty' => Question::DIFFICULTY_EASY,
        ], $bank, $admin);

        $this->assertTrue($qShort->requiresManualGrading());

        // 5. Long Answer Question (Requires Manual Grading)
        $qLong = $service->createQuestion($academy, [
            'question_text' => 'Explain the difference between client-side rendering and server-side rendering in modern SEO.',
            'question_type' => Question::TYPE_LONG_ANSWER,
            'rubric' => 'Award 2 points for SSR crawler benefits, 2 points for TTFB/execution cost explanation.',
            'points' => 5,
            'difficulty' => Question::DIFFICULTY_HARD,
        ], $bank, $admin);

        $this->assertTrue($qLong->requiresManualGrading());
        $this->assertEquals(5, $bank->questions()->count());
    }

    #[DataProvider('engineProvider')]
    public function test_multi_bank_question_reuse_and_detachment_safety(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createUserWithRole('admin', $academy);
        $service = app(QuestionBankService::class);

        $bankA = $service->createBank($academy, ['name' => 'Bank Alpha'], $admin);
        $bankB = $service->createBank($academy, ['name' => 'Bank Beta'], $admin);

        // Create question in Bank A
        $question = $service->createQuestion($academy, [
            'question_text' => 'Shared Core SEO Question',
            'question_type' => Question::TYPE_SINGLE_CHOICE,
            'options' => [
                ['key' => '1', 'text' => 'Choice 1'],
                ['key' => '2', 'text' => 'Choice 2'],
            ],
            'correct_answer' => '1',
            'points' => 1,
        ], $bankA, $admin);

        // Attach question to Bank B
        $service->addQuestionToBank($bankB, $question);

        $this->assertCount(1, $bankA->questions);
        $this->assertCount(1, $bankB->questions);
        $this->assertCount(2, $question->banks);

        // Detach from Bank A
        $service->removeQuestionFromBank($bankA, $question);

        // Bank A has 0 questions
        $this->assertCount(0, $bankA->fresh()->questions);

        // Bank B still has the question
        $this->assertCount(1, $bankB->fresh()->questions);
        $this->assertEquals($question->id, $bankB->questions()->first()->id);

        // Question still exists in DB
        $this->assertDatabaseHas('questions', ['id' => $question->id]);
    }

    #[DataProvider('engineProvider')]
    public function test_quiz_question_pools_and_pool_sufficiency_validation(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        [$course, $module] = $this->createHarness($academy);
        $admin = $this->createUserWithRole('admin', $academy);
        $bankService = app(QuestionBankService::class);

        $quiz = Quiz::create([
            'course_id' => $course->id,
            'module_id' => $module->id,
            'title' => 'Pool Quiz',
            'type' => 'quiz',
            'is_required' => true,
            'question_source' => 'pools',
            'randomize_questions' => true,
            'randomize_order' => true,
            'status' => 'Active',
        ]);

        $bank = $bankService->createBank($academy, ['name' => 'Topic Bank'], $admin);

        // Add 3 medium questions
        for ($i = 1; $i <= 3; $i++) {
            $bankService->createQuestion($academy, [
                'question_text' => "Medium Question {$i}",
                'question_type' => Question::TYPE_SINGLE_CHOICE,
                'options' => [['key' => 'a', 'text' => 'Option A']],
                'correct_answer' => 'a',
                'difficulty' => Question::DIFFICULTY_MEDIUM,
                'category' => 'Crawling',
                'points' => 2,
            ], $bank, $admin);
        }

        // Configure a pool requesting 2 questions
        $pool = $bankService->addPoolToQuiz($quiz, $bank, 2, [
            'category' => 'Crawling',
            'difficulty' => Question::DIFFICULTY_MEDIUM,
            'points_per_question' => 2,
        ]);

        $this->assertEquals(3, $pool->getAvailableCount());
        $this->assertTrue($pool->isSufficient());
        $this->assertEmpty($quiz->validatePoolSufficiency());

        // Attempting to add an insufficient pool throws InvalidArgumentException immediately
        try {
            $bankService->addPoolToQuiz($quiz, $bank, 5, [
                'difficulty' => Question::DIFFICULTY_HARD,
            ]);
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Insufficient questions', $e->getMessage());
        }

        // When questions are archived after pool creation, validatePoolSufficiency detects shortage
        $crawlingQuestions = $pool->getEligibleQuestionsQuery()->get();
        $crawlingQuestions[0]->update(['status' => Question::STATUS_ARCHIVED]);
        $crawlingQuestions[1]->update(['status' => Question::STATUS_ARCHIVED]);

        $errors = $quiz->validatePoolSufficiency();
        $this->assertNotEmpty($errors);
        $this->assertEquals(1, $errors[0]['available']);
        $this->assertEquals(2, $errors[0]['requested']);
    }

    #[DataProvider('engineProvider')]
    public function test_attempt_question_resolution_randomization_and_stability(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        [$course, $module, $lesson, $studentUser, $profile, $enrollment] = $this->createHarness($academy);
        $admin = $this->createUserWithRole('admin', $academy);
        $bankService = app(QuestionBankService::class);
        $assessmentService = app(AssessmentService::class);

        $quiz = Quiz::create([
            'course_id' => $course->id,
            'module_id' => $module->id,
            'title' => 'Randomized Exam',
            'type' => 'exam',
            'question_source' => 'pools',
            'randomize_questions' => true,
            'randomize_order' => true,
            'status' => 'Active',
            'passing_percentage' => 70,
        ]);

        $bank = $bankService->createBank($academy, ['name' => 'Large Question Bank'], $admin);

        // Populate bank with 10 questions
        for ($i = 1; $i <= 10; $i++) {
            $bankService->createQuestion($academy, [
                'question_text' => "SEO Question #{$i}",
                'question_type' => Question::TYPE_SINGLE_CHOICE,
                'options' => [
                    ['key' => 'a', 'text' => "Option A for {$i}"],
                    ['key' => 'b', 'text' => "Option B for {$i}"],
                ],
                'correct_answer' => 'a',
                'points' => 2,
            ], $bank, $admin);
        }

        // Draw 4 questions from bank
        $bankService->addPoolToQuiz($quiz, $bank, 4, [
            'points_per_question' => 2,
        ]);

        // Start student attempt
        $attempt = $assessmentService->createAttempt($enrollment, $quiz, $studentUser);

        $this->assertCount(4, $attempt->selected_question_ids);
        $this->assertCount(4, $attempt->question_snapshots);

        // Fetch questions multiple times to ensure stability
        $firstRetrieval = $attempt->getQuestionsForAttempt();
        $secondRetrieval = $attempt->getQuestionsForAttempt();

        $this->assertEquals(
            collect($firstRetrieval)->pluck('id')->all(),
            collect($secondRetrieval)->pluck('id')->all(),
            'Attempt questions must remain strictly identical across repeated retrievals.'
        );

        $this->assertEquals($attempt->selected_question_ids, collect($firstRetrieval)->pluck('id')->all());

        // Check sanitized payload for student
        $sanitized = $attempt->getSanitizedQuestionsForStudent();
        $this->assertCount(4, $sanitized);
        foreach ($sanitized as $item) {
            $this->assertArrayNotHasKey('correct_answer', $item);
            $this->assertArrayNotHasKey('rubric', $item);
            $this->assertArrayNotHasKey('explanation', $item);
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('question_text', $item);
            $this->assertArrayHasKey('options', $item);
            $this->assertArrayHasKey('points', $item);
        }
    }

    #[DataProvider('engineProvider')]
    public function test_immutable_snapshot_protects_grading_against_subsequent_bank_edits(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        [$course, $module, $lesson, $studentUser, $profile, $enrollment] = $this->createHarness($academy);
        $admin = $this->createUserWithRole('admin', $academy);
        $bankService = app(QuestionBankService::class);
        $assessmentService = app(AssessmentService::class);

        $quiz = Quiz::create([
            'course_id' => $course->id,
            'module_id' => $module->id,
            'title' => 'Evaluation Exam',
            'type' => 'exam',
            'question_source' => 'pools',
            'passing_percentage' => 50,
            'status' => 'Active',
        ]);

        $bank = $bankService->createBank($academy, ['name' => 'Original Bank'], $admin);
        $question = $bankService->createQuestion($academy, [
            'question_text' => 'What status code indicates permanent redirect?',
            'question_type' => Question::TYPE_SINGLE_CHOICE,
            'options' => [
                ['key' => '301', 'text' => '301'],
                ['key' => '302', 'text' => '302'],
            ],
            'correct_answer' => '301',
            'points' => 10,
        ], $bank, $admin);

        // Associate question with quiz via pool
        $bankService->addPoolToQuiz($quiz, $bank, 1, [
            'points_per_question' => 10,
        ]);

        // Student starts attempt while correct answer is 301 and points is 10
        $attempt = $assessmentService->createAttempt($enrollment, $quiz, $studentUser);

        // Now instructor modifies the question in the bank to something completely different
        $bankService->updateQuestion($question, [
            'question_text' => 'MODIFIED QUESTION PROMPT',
            'correct_answer' => '302', // changed correct answer!
            'points' => 2,             // changed points!
        ]);

        // Student submits original answer '301'
        $submittedAttempt = $assessmentService->submitAttempt($attempt, [
            $question->id => '301',
        ], $studentUser);

        // Verification: The attempt evaluation MUST use the immutable snapshot recorded at start time
        $answer = $submittedAttempt->answers()->where('bank_question_id', $question->id)->first();
        $this->assertNotNull($answer);
        $this->assertTrue((bool) $answer->is_correct, 'Answer 301 should be correct based on attempt snapshot.');
        $this->assertEquals(10.0, (float) $answer->points_awarded, 'Points awarded should match snapshot points (10), not modified points (2).');
        $this->assertEquals(10.0, (float) $submittedAttempt->earned_points);
        $this->assertTrue((bool) $submittedAttempt->passed);
    }

    #[DataProvider('engineProvider')]
    public function test_answer_key_and_rubric_security_zero_leakage_to_students(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createUserWithRole('admin', $academy);
        $bankService = app(QuestionBankService::class);

        $bank = $bankService->createBank($academy, ['name' => 'Secure Bank'], $admin);

        $qSingle = $bankService->createQuestion($academy, [
            'question_text' => 'Secret prompt 1',
            'question_type' => Question::TYPE_SINGLE_CHOICE,
            'options' => [['key' => 'a', 'text' => 'Alpha'], ['key' => 'b', 'text' => 'Beta']],
            'correct_answer' => 'a',
            'explanation' => 'Super secret explanation',
            'points' => 5,
        ], $bank, $admin);

        $qLong = $bankService->createQuestion($academy, [
            'question_text' => 'Secret essay question',
            'question_type' => Question::TYPE_LONG_ANSWER,
            'rubric' => 'Secret grading rubric with internal guidance',
            'points' => 10,
        ], $bank, $admin);

        $sanitizedSingle = $qSingle->getSanitizedForStudent();
        $sanitizedLong = $qLong->getSanitizedForStudent();

        $this->assertArrayNotHasKey('correct_answer', $sanitizedSingle);
        $this->assertArrayNotHasKey('explanation', $sanitizedSingle);
        $this->assertArrayNotHasKey('rubric', $sanitizedSingle);

        $this->assertArrayNotHasKey('correct_answer', $sanitizedLong);
        $this->assertArrayNotHasKey('explanation', $sanitizedLong);
        $this->assertArrayNotHasKey('rubric', $sanitizedLong);
    }

    #[DataProvider('engineProvider')]
    public function test_role_based_authorization_matrix_and_tenant_isolation(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        $admin = $this->createUserWithRole('admin', $academy);
        $trainer = $this->createUserWithRole('trainer', $academy);
        $supportStaff = $this->createUserWithRole('support_staff', $academy);
        $student = $this->createUserWithRole('student', $academy);

        $bankService = app(QuestionBankService::class);
        $adminBank = $bankService->createBank($academy, ['name' => 'Admin Bank'], $admin);
        $trainerBank = $bankService->createBank($academy, ['name' => 'Trainer Bank'], $trainer);

        // A bank with foreign academy ID
        $foreignBank = new QuestionBank([
            'name' => 'Foreign Academy Bank',
            'academy_id' => (string) Str::uuid(),
            'status' => 'active',
        ]);
        $foreignBank->id = (string) Str::uuid();

        // 1. Admin permissions
        $this->assertTrue($admin->can('viewAny', QuestionBank::class));
        $this->assertTrue($admin->can('create', QuestionBank::class));
        $this->assertTrue($admin->can('update', $adminBank));
        $this->assertTrue($admin->can('update', $trainerBank));
        $this->assertTrue($admin->can('delete', $adminBank));
        $this->assertTrue($admin->can('delete', $trainerBank));

        // 2. Trainer permissions
        $this->assertTrue($trainer->can('viewAny', QuestionBank::class));
        $this->assertTrue($trainer->can('create', QuestionBank::class));
        $this->assertTrue($trainer->can('view', $trainerBank));
        $this->assertTrue($trainer->can('update', $trainerBank));
        $this->assertTrue($trainer->can('delete', $trainerBank));

        // Trainer cannot delete admin's bank
        $this->assertFalse($trainer->can('delete', $adminBank));

        // 3. Support Staff permissions (Must be completely denied 403)
        $this->assertFalse($supportStaff->can('viewAny', QuestionBank::class));
        $this->assertFalse($supportStaff->can('create', QuestionBank::class));
        $this->assertFalse($supportStaff->can('view', $adminBank));
        $this->assertFalse($supportStaff->can('update', $adminBank));
        $this->assertFalse($supportStaff->can('delete', $adminBank));

        // 4. Student permissions (Must be completely denied 403)
        $this->assertFalse($student->can('viewAny', QuestionBank::class));
        $this->assertFalse($student->can('create', QuestionBank::class));
        $this->assertFalse($student->can('view', $adminBank));
        $this->assertFalse($student->can('update', $adminBank));
        $this->assertFalse($student->can('delete', $adminBank));

        // 5. Tenant isolation: bank belonging to another academy cannot be accessed
        $this->assertFalse($admin->can('view', $foreignBank));
        $this->assertFalse($admin->can('update', $foreignBank));
        $this->assertFalse($admin->can('delete', $foreignBank));
    }

    #[DataProvider('engineProvider')]
    public function test_question_bank_livewire_manager_component(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createUserWithRole('admin', $academy);

        $component = Livewire::actingAs($admin)
            ->test(QuestionBankManager::class)
            ->call('openCreateBankModal')
            ->set('bankName', 'Component Created Bank')
            ->set('bankDescription', 'Testing livewire component bank creation')
            ->set('bankCategory', 'Technical')
            ->call('saveBank');

        $this->assertDatabaseHas('question_banks', [
            'name' => 'Component Created Bank',
            'academy_id' => $academy->id,
        ]);

        $bank = QuestionBank::where('name', 'Component Created Bank')->first();

        // Switch to the bank and add a question
        $component->call('selectBank', $bank->id)
            ->call('openCreateQuestionModal')
            ->set('questionText', 'Livewire test question?')
            ->set('questionType', 'single_choice')
            ->set('points', 3)
            ->set('difficulty', 'medium')
            ->set('options', [
                ['key' => 'opt_1', 'text' => 'Option One'],
                ['key' => 'opt_2', 'text' => 'Option Two'],
            ])
            ->set('correctAnswer', 'opt_1')
            ->call('saveQuestion');

        $this->assertDatabaseHas('questions', [
            'academy_id' => $academy->id,
            'question_text' => 'Livewire test question?',
            'question_type' => 'single_choice',
            'points' => 3,
        ]);

        $this->assertCount(1, $bank->fresh()->questions);
    }

    #[DataProvider('engineProvider')]
    public function test_assessments_index_pool_modal_interactions(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        [$course, $module] = $this->createHarness($academy);
        $admin = $this->createUserWithRole('admin', $academy);
        $bankService = app(QuestionBankService::class);

        $bank = $bankService->createBank($academy, ['name' => 'Pool Bank'], $admin);
        for ($i = 0; $i < 3; $i++) {
            $bankService->createQuestion($academy, [
                'question_text' => "Question {$i}",
                'question_type' => 'single_choice',
                'options' => [['key' => 'a', 'text' => 'A']],
                'correct_answer' => 'a',
                'points' => 2,
            ], $bank, $admin);
        }

        $quiz = Quiz::create([
            'course_id' => $course->id,
            'module_id' => $module->id,
            'title' => 'Assessments Index Quiz',
            'type' => 'exam',
            'status' => 'Active',
        ]);

        Livewire::actingAs($admin)
            ->test(AssessmentsIndex::class)
            ->call('openPoolsModal', $quiz->id)
            ->set('poolBankId', $bank->id)
            ->set('poolQuestionCount', 2)
            ->set('poolPointsOverride', 2)
            ->call('addPool');

        $this->assertDatabaseHas('quiz_question_pools', [
            'quiz_id' => $quiz->id,
            'question_bank_id' => $bank->id,
            'question_count' => 2,
        ]);
        $this->assertEquals('pools', $quiz->fresh()->question_source);
    }

    #[DataProvider('engineProvider')]
    public function test_trainer_to_trainer_isolation_across_banks_and_livewire(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $trainerA = $this->createUserWithRole('trainer', $academy);
        $trainerB = $this->createUserWithRole('trainer', $academy);
        $bankService = app(QuestionBankService::class);

        $bankA = $bankService->createBank($academy, ['name' => 'Trainer A Bank'], $trainerA);
        $bankB = $bankService->createBank($academy, ['name' => 'Trainer B Bank'], $trainerB);

        // 1. Policy Level Checks
        $this->assertTrue($trainerA->can('update', $bankA));
        $this->assertTrue($trainerA->can('delete', $bankA));
        $this->assertTrue($trainerB->can('update', $bankB));
        $this->assertTrue($trainerB->can('delete', $bankB));

        // Cross-trainer access denied
        $this->assertFalse($trainerB->can('update', $bankA));
        $this->assertFalse($trainerB->can('delete', $bankA));
        $this->assertFalse($trainerA->can('update', $bankB));
        $this->assertFalse($trainerA->can('delete', $bankB));

        // 2. Livewire Action Checks
        // Trainer B cannot open edit modal for Bank A
        Livewire::actingAs($trainerB)
            ->test(QuestionBankManager::class)
            ->call('openEditBankModal', $bankA->id)
            ->assertStatus(403);

        // Trainer B cannot toggle archive for Bank A
        Livewire::actingAs($trainerB)
            ->test(QuestionBankManager::class)
            ->call('toggleArchiveBank', $bankA->id)
            ->assertStatus(403);

        // Trainer B cannot add questions to Bank A
        Livewire::actingAs($trainerB)
            ->test(QuestionBankManager::class)
            ->call('selectBank', $bankA->id)
            ->call('openCreateQuestionModal')
            ->set('questionText', 'Unauthorized Injection')
            ->set('questionType', 'single_choice')
            ->set('points', 2)
            ->set('difficulty', 'medium')
            ->call('saveQuestion')
            ->assertStatus(403);

        // Trainer A can access Bank A without error
        Livewire::actingAs($trainerA)
            ->test(QuestionBankManager::class)
            ->call('openEditBankModal', $bankA->id)
            ->assertStatus(200);
    }

    #[DataProvider('engineProvider')]
    public function test_overlapping_question_pool_deduplication_and_unique_constraint(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        [$course, $module, $lesson, $studentUser, $profile, $enrollment] = $this->createHarness($academy);
        $admin = $this->createUserWithRole('admin', $academy);
        $bankService = app(QuestionBankService::class);
        $assessmentService = app(AssessmentService::class);

        $bank = $bankService->createBank($academy, ['name' => 'Overlapping Bank'], $admin);

        // Populate bank with 5 distinct questions in category 'Technical' and difficulty 'medium'
        for ($i = 1; $i <= 5; $i++) {
            $bankService->createQuestion($academy, [
                'question_text' => "Unique Technical Question {$i}",
                'question_type' => Question::TYPE_SINGLE_CHOICE,
                'options' => [['key' => 'a', 'text' => 'Option A']],
                'correct_answer' => 'a',
                'difficulty' => Question::DIFFICULTY_MEDIUM,
                'category' => 'Technical',
                'points' => 2,
            ], $bank, $admin);
        }

        $quiz = Quiz::create([
            'course_id' => $course->id,
            'module_id' => $module->id,
            'title' => 'Overlapping Pools Quiz',
            'type' => 'exam',
            'question_source' => 'pools',
            'randomize_questions' => true,
            'randomize_order' => false,
            'status' => 'Active',
        ]);

        // Pool 1 draws 2 questions from 'Technical' (eligible: 5)
        $bankService->addPoolToQuiz($quiz, $bank, 2, [
            'category' => 'Technical',
            'difficulty' => Question::DIFFICULTY_MEDIUM,
        ]);

        // Pool 2 draws 2 questions from 'Technical' (overlapping eligible set)
        $bankService->addPoolToQuiz($quiz, $bank, 2, [
            'category' => 'Technical',
        ]);

        // Create attempt: total requested 2 + 2 = 4
        $attempt = $assessmentService->createAttempt($enrollment, $quiz, $studentUser);

        $this->assertCount(4, $attempt->selected_question_ids);
        // Ensure no question ID occurs more than once
        $this->assertCount(4, array_unique($attempt->selected_question_ids));

        // Stability: successive retrievals return exact same questions
        $first = $attempt->getQuestionsForAttempt();
        $second = $attempt->getQuestionsForAttempt();
        $this->assertEquals(collect($first)->pluck('id')->all(), collect($second)->pluck('id')->all());

        // Now test insufficiency: second quiz requests 3 + 3 = 6 from bank of 5
        $quiz2 = Quiz::create([
            'course_id' => $course->id,
            'module_id' => $module->id,
            'title' => 'Insufficient Unique Questions Quiz',
            'type' => 'exam',
            'question_source' => 'pools',
            'status' => 'Active',
        ]);

        $bankService->addPoolToQuiz($quiz2, $bank, 3, ['category' => 'Technical']);
        $bankService->addPoolToQuiz($quiz2, $bank, 3, ['category' => 'Technical']);

        try {
            $assessmentService->createAttempt($enrollment, $quiz2, $studentUser);
            $this->fail('Expected InvalidArgumentException for insufficient unique questions was not thrown.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Insufficient unique questions', $e->getMessage());
        }

        $this->assertDatabaseMissing('assessment_attempts', ['assessment_id' => $quiz2->id]);
    }

    #[DataProvider('engineProvider')]
    public function test_deleted_question_snapshot_survival_and_grading_integrity(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        [$course, $module, $lesson, $studentUser, $profile, $enrollment] = $this->createHarness($academy);
        $admin = $this->createUserWithRole('admin', $academy);
        $bankService = app(QuestionBankService::class);
        $assessmentService = app(AssessmentService::class);

        $bank = $bankService->createBank($academy, ['name' => 'Ephemeral Bank'], $admin);
        $question = $bankService->createQuestion($academy, [
            'question_text' => 'Persistent Question Prompt',
            'question_type' => Question::TYPE_SINGLE_CHOICE,
            'options' => [
                ['key' => 'a', 'text' => 'Correct Option'],
                ['key' => 'b', 'text' => 'Wrong Option'],
            ],
            'correct_answer' => 'a',
            'explanation' => 'Persistent Explanation',
            'rubric' => 'Persistent Rubric',
            'points' => 5,
            'difficulty' => Question::DIFFICULTY_EASY,
        ], $bank, $admin);

        $quiz = Quiz::create([
            'course_id' => $course->id,
            'module_id' => $module->id,
            'title' => 'Snapshot Survival Quiz',
            'type' => 'quiz',
            'question_source' => 'pools',
            'status' => 'Active',
            'passing_percentage' => 50,
        ]);

        $bankService->addPoolToQuiz($quiz, $bank, 1, ['points_per_question' => 5]);

        // Student starts attempt while question exists
        $attempt = $assessmentService->createAttempt($enrollment, $quiz, $studentUser);

        // Permanently delete the question from the database before submission
        $questionId = $question->id;
        $question->delete();
        $this->assertDatabaseMissing('questions', ['id' => $questionId]);

        // Attempt still loads from persisted snapshot
        $questions = $attempt->getQuestionsForAttempt();
        $this->assertCount(1, $questions);
        $this->assertEquals('Persistent Question Prompt', $questions[0]['question_text']);
        $this->assertEquals(5, $questions[0]['points']);

        // Student-facing data remains sanitized
        $sanitized = $attempt->getSanitizedQuestionsForStudent();
        $this->assertArrayNotHasKey('correct_answer', $sanitized[0]);
        $this->assertArrayNotHasKey('rubric', $sanitized[0]);
        $this->assertArrayNotHasKey('explanation', $sanitized[0]);
        $this->assertEquals('Persistent Question Prompt', $sanitized[0]['question_text']);

        // Submit attempt with correct answer from snapshot
        $submitted = $assessmentService->submitAttempt($attempt, [
            $questionId => 'a',
        ], $studentUser);

        $this->assertTrue((bool) $submitted->passed);
        $this->assertEquals(5.0, (float) $submitted->earned_points);

        $answer = $submitted->answers()->first();
        $this->assertNotNull($answer);
        $this->assertNull($answer->bank_question_id); // Safely null because question was deleted
        $this->assertEquals('Persistent Question Prompt', $answer->getQuestionText());
        $this->assertEquals(5.0, $answer->getPointsPossible());
    }

    #[DataProvider('engineProvider')]
    public function test_student_facing_livewire_assessment_player_security(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        [$course, $module, $lesson, $studentUser, $profile, $enrollment] = $this->createHarness($academy);
        $admin = $this->createUserWithRole('admin', $academy);
        $bankService = app(QuestionBankService::class);

        $bank = $bankService->createBank($academy, ['name' => 'Confidential Bank'], $admin);
        $question = $bankService->createQuestion($academy, [
            'question_text' => 'Public Question Prompt Text',
            'question_type' => Question::TYPE_SINGLE_CHOICE,
            'options' => [
                ['key' => 'ans_a', 'text' => 'Public Option A'],
                ['key' => 'ans_b', 'text' => 'Public Option B'],
            ],
            'correct_answer' => 'ans_a',
            'explanation' => 'CONFIDENTIAL_EXPLANATION_TEXT',
            'rubric' => 'CONFIDENTIAL_RUBRIC_TEXT',
            'points' => 10,
            'difficulty' => Question::DIFFICULTY_EASY,
        ], $bank, $admin);

        $quiz = Quiz::create([
            'course_id' => $course->id,
            'module_id' => $module->id,
            'title' => 'Livewire Secure Assessment',
            'type' => 'exam',
            'question_source' => 'pools',
            'status' => 'Active',
            'passing_percentage' => 70,
        ]);

        $bankService->addPoolToQuiz($quiz, $bank, 1, ['points_per_question' => 10]);

        $component = Livewire::actingAs($studentUser)
            ->test(AssessmentPlayer::class, [
                'course' => $course,
                'assessment' => $quiz,
            ]);

        // 1. Initial render prior to starting: Confidential fields never in HTML
        $component->assertDontSee('CONFIDENTIAL_EXPLANATION_TEXT')
            ->assertDontSee('CONFIDENTIAL_RUBRIC_TEXT')
            ->assertDontSee('ans_a'); // Answer key not leaked

        // 2. Start attempt
        $component->call('startAttempt');

        // Check HTML again during active attempt
        $component->assertSee('Public Question Prompt Text')
            ->assertSee('Public Option A')
            ->assertSee('Public Option B')
            ->assertDontSee('CONFIDENTIAL_EXPLANATION_TEXT')
            ->assertDontSee('CONFIDENTIAL_RUBRIC_TEXT');

        // Verify model serialization hiding
        $currentAttempt = $component->get('currentAttempt');
        $this->assertNotNull($currentAttempt);
        $attemptArray = $currentAttempt->toArray();
        $this->assertArrayNotHasKey('question_snapshots', $attemptArray);

        // Verify assessmentData questions
        $assessmentData = $component->get('assessmentData');
        $this->assertNotEmpty($assessmentData['questions']);
        foreach ($assessmentData['questions'] as $q) {
            $this->assertArrayNotHasKey('correct_answer', $q);
            $this->assertArrayNotHasKey('rubric', $q);
            $this->assertArrayNotHasKey('explanation', $q);
        }

        // 3. Submit and verify passing flow
        $component->set('answers.'.$question->id, 'ans_a')
            ->call('submitAttempt');

        $component->assertSee('Assessment Passed!')
            ->assertDontSee('CONFIDENTIAL_EXPLANATION_TEXT');

        $this->assertTrue($component->get('isSubmitted'));
        $this->assertEquals(100.0, $component->get('submissionResult')['score_percentage']);
    }

    #[DataProvider('engineProvider')]
    public function test_pool_sufficiency_failure_at_attempt_creation_prevents_partial_state(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        [$course, $module, $lesson, $studentUser1, $profile1, $enrollment1] = $this->createHarness($academy);
        $admin = $this->createUserWithRole('admin', $academy);
        $bankService = app(QuestionBankService::class);
        $assessmentService = app(AssessmentService::class);

        $bank = $bankService->createBank($academy, ['name' => 'Strict Bank'], $admin);
        $q1 = $bankService->createQuestion($academy, [
            'question_text' => 'Question 1',
            'question_type' => 'single_choice',
            'options' => [['key' => 'a', 'text' => 'A']],
            'correct_answer' => 'a',
        ], $bank, $admin);
        $q2 = $bankService->createQuestion($academy, [
            'question_text' => 'Question 2',
            'question_type' => 'single_choice',
            'options' => [['key' => 'a', 'text' => 'A']],
            'correct_answer' => 'a',
        ], $bank, $admin);

        $quiz = Quiz::create([
            'course_id' => $course->id,
            'module_id' => $module->id,
            'title' => 'Sufficiency Test Quiz',
            'type' => 'exam',
            'question_source' => 'pools',
            'status' => 'Active',
        ]);

        $bankService->addPoolToQuiz($quiz, $bank, 2);

        // Student 1 starts valid attempt
        $attempt1 = $assessmentService->createAttempt($enrollment1, $quiz, $studentUser1);
        $this->assertNotNull($attempt1);
        $this->assertDatabaseHas('assessment_attempts', ['id' => $attempt1->id]);

        // Setup Student 2
        $studentUser2 = $this->createUserWithRole('student', $academy);
        $profile2 = StudentProfile::factory()->create(['academy_id' => $academy->id, 'user_id' => $studentUser2->id]);
        $enrollment2 = Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $profile2->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now()->toDateString(),
        ]);

        // Archive question 2 so only 1 remains (pool demands 2)
        $q2->update(['status' => Question::STATUS_ARCHIVED]);

        // Direct Service attempt creation must fail
        try {
            $assessmentService->createAttempt($enrollment2, $quiz, $studentUser2);
            $this->fail('Expected InvalidArgumentException for insufficient pool was not thrown.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Insufficient unique questions', $e->getMessage());
        }

        // Student 2 has no attempt record created
        $this->assertDatabaseMissing('assessment_attempts', [
            'enrollment_id' => $enrollment2->id,
            'user_id' => $studentUser2->id,
        ]);

        // Student 1's attempt is completely unharmed
        $this->assertDatabaseHas('assessment_attempts', ['id' => $attempt1->id]);

        // Livewire AssessmentPlayer handles the failure cleanly with an error message
        Livewire::actingAs($studentUser2)
            ->test(AssessmentPlayer::class, [
                'course' => $course,
                'assessment' => $quiz,
            ])
            ->call('startAttempt')
            ->assertSee('Insufficient unique questions');

        $this->assertDatabaseMissing('assessment_attempts', [
            'enrollment_id' => $enrollment2->id,
            'user_id' => $studentUser2->id,
        ]);
    }
}
