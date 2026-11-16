<?php

/**
 * Concurrency Worker for Multi-Process Integration Tests.
 * Bootstraps Laravel in an independent OS process and executes actions across an atomic barrier.
 */

declare(strict_types=1);
use App\Domain\Assessments\Models\AssessmentAttempt;
use App\Domain\Assessments\Services\AssessmentFinalizationService;
use App\Domain\Assessments\Services\AssessmentService;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;

$options = getopt('', [
    'connection:',
    'database:',
    'barrier-dir:',
    'worker-id:',
    'action:',
    'attempt-id:',
    'question-id::',
    'answer::',
    'revision::',
    'idempotency-key::',
    'trigger::',
    'evaluator-id::',
    'user-id::',
    'manual-scores::',
]);

$pid = getmypid();
$workerId = $options['worker-id'] ?? 'worker_'.$pid;
$barrierDir = $options['barrier-dir'] ?? sys_get_temp_dir();
$connection = $options['connection'] ?? 'sqlite';
$database = $options['database'] ?? ':memory:';
$action = $options['action'] ?? '';
$attemptId = $options['attempt-id'] ?? '';

$response = [
    'pid' => $pid,
    'worker_id' => $workerId,
    'action' => $action,
    'status' => 'pending',
    'error_class' => null,
    'error_message' => null,
    'data' => null,
];

try {
    require __DIR__.'/../../../vendor/autoload.php';
    /** @var Application $app */
    $app = require_once __DIR__.'/../../../bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    // Configure isolated database connection
    config(['cache.default' => 'array', 'permission.cache.store' => 'array']);
    if ($connection === 'sqlite') {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $database,
            'database.connections.sqlite.foreign_key_constraints' => true,
            'database.connections.sqlite.busy_timeout' => 10000,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
    } else {
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => '127.0.0.1',
            'database.connections.mysql.port' => 3306,
            'database.connections.mysql.database' => $database,
            'database.connections.mysql.username' => 'root',
            'database.connections.mysql.password' => '',
        ]);
        DB::purge('mysql');
        DB::setDefaultConnection('mysql');
    }

    /** @var AssessmentAttempt $attempt */
    $attempt = AssessmentAttempt::findOrFail($attemptId);
    $user = ! empty($options['user-id']) ? User::find($options['user-id']) : $attempt->user;

    // Signal ready at barrier
    $readyFile = $barrierDir.DIRECTORY_SEPARATOR.'ready_'.$workerId;
    file_put_contents($readyFile, (string) $pid);

    // Wait for release signal
    $startFile = $barrierDir.DIRECTORY_SEPARATOR.'start';
    $deadline = microtime(true) + 15.0;
    while (! file_exists($startFile)) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException("Worker [{$workerId}] timed out waiting for barrier start.");
        }
        usleep(2000); // 2ms polling
    }

    // Execute designated action under race condition
    if ($action === 'save_answer') {
        $service = app(AssessmentService::class);
        $questionId = (string) ($options['question-id'] ?? '');
        $studentAnswer = $options['answer'] ?? null;
        $revision = (int) ($options['revision'] ?? 1);
        $idempotencyKey = $options['idempotency-key'] ?? null;

        $ans = $service->saveAttemptAnswer(
            attempt: $attempt,
            questionId: $questionId,
            studentAnswer: $studentAnswer,
            revision: $revision,
            idempotencyKey: $idempotencyKey,
            user: $user
        );

        $response['status'] = 'success';
        $response['data'] = [
            'id' => $ans->id,
            'student_answer' => $ans->student_answer,
            'revision' => $ans->revision,
            'last_idempotency_key' => $ans->last_idempotency_key,
        ];
    } elseif ($action === 'finalize_attempt') {
        $finalizationService = app(AssessmentFinalizationService::class);
        $trigger = $options['trigger'] ?? 'student_submit';

        $finalized = $finalizationService->finalizeAttempt(
            attempt: $attempt,
            trigger: $trigger,
            submittedAnswers: null,
            user: $user
        );

        $response['status'] = 'success';
        $response['data'] = [
            'id' => $finalized->id,
            'status' => $finalized->status,
            'earned_points' => (float) $finalized->earned_points,
            'total_points' => (float) $finalized->total_points,
            'passed' => (bool) $finalized->passed,
            'finalization_trigger' => $finalized->finalization_trigger,
        ];
    } elseif ($action === 'grade_attempt') {
        $service = app(AssessmentService::class);
        $evaluatorId = $options['evaluator-id'] ?? '';
        $evaluator = User::findOrFail($evaluatorId);
        $scoresJson = $options['manual-scores'] ?? '{}';
        $scores = json_decode($scoresJson, true) ?: [];

        $graded = $service->gradeAttempt(
            attempt: $attempt,
            manualScores: $scores,
            evaluator: $evaluator
        );

        $response['status'] = 'success';
        $response['data'] = [
            'id' => $graded->id,
            'status' => $graded->status,
            'earned_points' => (float) $graded->earned_points,
            'total_points' => (float) $graded->total_points,
            'passed' => (bool) $graded->passed,
        ];
    } else {
        throw new InvalidArgumentException("Unknown action [{$action}].");
    }
} catch (Throwable $e) {
    $response['status'] = 'error';
    $response['error_class'] = get_class($e);
    $response['error_message'] = $e->getMessage();
}

echo json_encode($response);
exit(0);
