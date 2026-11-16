<?php

/**
 * Concurrency Worker for Phase 4.3 Practical Assignment Multi-Process Integration Tests.
 * Bootstraps Laravel in an independent OS process and executes actions across an atomic barrier.
 */

declare(strict_types=1);
use App\Domain\Assignments\Models\Assignment;
use App\Domain\Assignments\Models\AssignmentSubmission;
use App\Domain\Assignments\Services\AssignmentGradingService;
use App\Domain\Assignments\Services\AssignmentSubmissionService;
use App\Domain\Learning\Services\CourseCompletionService;
use App\Domain\Students\Models\Enrollment;
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
    'assignment-id:',
    'submission-id::',
    'user-id:',
    'content::',
    'student-notes::',
    'points-awarded::',
    'feedback::',
    'revision-reason::',
]);

$pid = getmypid();
$workerId = $options['worker-id'] ?? 'worker_'.$pid;
$barrierDir = $options['barrier-dir'] ?? sys_get_temp_dir();
$connection = $options['connection'] ?? 'sqlite';
$database = $options['database'] ?? ':memory:';
$action = $options['action'] ?? '';
$assignmentId = $options['assignment-id'] ?? '';
$submissionId = $options['submission-id'] ?? '';
$userId = $options['user-id'] ?? '';

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
    $app = require __DIR__.'/../../../bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    // Configure isolated database connection
    config([
        'cache.default' => 'array',
        'permission.cache.store' => 'array',
        'session.driver' => 'array',
    ]);

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

    /** @var Assignment $assignment */
    $assignment = Assignment::findOrFail($assignmentId);
    /** @var User $user */
    $user = User::findOrFail($userId);

    // Signal ready at barrier
    $readyFile = $barrierDir.DIRECTORY_SEPARATOR.'ready_'.$workerId;
    file_put_contents($readyFile, (string) $pid);

    // Wait for release signal
    $startFile = $barrierDir.DIRECTORY_SEPARATOR.'start';
    $deadline = microtime(true) + 20.0;
    while (! file_exists($startFile)) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException("Worker [{$workerId}] timed out waiting for barrier start.");
        }
        usleep(2000); // 2ms polling
    }

    // Execute designated action
    if ($action === 'submit') {
        $service = app(AssignmentSubmissionService::class);
        $content = $options['content'] ?? 'Concurrent submission content from '.$workerId;
        $notes = $options['student-notes'] ?? null;

        $submission = $service->submit($assignment, $user, [
            'content' => $content,
            'student_notes' => $notes,
            'files' => [
                [
                    'name' => 'worker_'.$workerId.'.txt',
                    'content' => 'Worker file payload from PID '.$pid,
                    'mime_type' => 'text/plain',
                ],
            ],
        ]);

        $response['status'] = 'success';
        $response['data'] = [
            'submission_id' => $submission->id,
            'version_number' => $submission->version_number,
            'status' => $submission->status,
        ];
    } elseif ($action === 'grade') {
        $service = app(AssignmentGradingService::class);
        $sub = AssignmentSubmission::findOrFail($submissionId);
        $points = isset($options['points-awarded']) ? (float) $options['points-awarded'] : 80.0;
        $feedback = $options['feedback'] ?? 'Graded by worker '.$workerId;

        $eval = $service->grade($sub, $user, [
            'points_awarded' => $points,
            'overall_feedback' => $feedback,
        ]);

        $response['status'] = 'success';
        $response['data'] = [
            'evaluation_id' => $eval->id,
            'points_awarded' => (float) $eval->points_awarded,
            'is_passed' => $eval->is_passed,
        ];
    } elseif ($action === 'request_revision') {
        $service = app(AssignmentGradingService::class);
        $sub = AssignmentSubmission::findOrFail($submissionId);
        $reason = $options['revision-reason'] ?? 'Revision requested by worker '.$workerId;

        $eval = $service->requestRevision($sub, $user, $reason);

        $response['status'] = 'success';
        $response['data'] = [
            'evaluation_id' => $eval->id,
            'status' => $eval->status,
            'revision_reason' => $eval->revision_reason,
        ];
    } elseif ($action === 'evaluate_completion') {
        $service = app(CourseCompletionService::class);
        $enrollment = Enrollment::findOrFail($submissionId);
        $result = $service->evaluateCourseCompletion($enrollment);
        $response['status'] = 'success';
        $response['data'] = [
            'is_completed' => $result,
        ];
    } else {
        throw new InvalidArgumentException("Unknown worker action [{$action}].");
    }
} catch (Throwable $e) {
    $response['status'] = 'error';
    $response['error_class'] = get_class($e);
    $response['error_message'] = $e->getMessage();
}

echo json_encode($response);
exit($response['status'] === 'success' ? 0 : 1);
