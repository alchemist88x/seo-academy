<?php

namespace App\Domain\Platform\Services;

use App\Domain\Academies\Models\Academy;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use Throwable;

class DatabaseInstallerService
{
    private const PROBE_TABLE = '_mwa_perm_probe';

    public const IDENTITY_TABLE = 'installation_identity';

    public const COMMON_REQUIRED_EXTENSIONS = [
        'bcmath',
        'ctype',
        'curl',
        'dom',
        'fileinfo',
        'filter',
        'hash',
        'json',
        'mbstring',
        'openssl',
        'pcre',
        'pdo',
        'session',
        'tokenizer',
        'xml',
        'zip',
    ];

    public const DRIVER_EXTENSIONS = [
        'sqlite' => 'pdo_sqlite',
        'mysql' => 'pdo_mysql',
    ];

    public const KNOWN_TABLES = [
        // Laravel & Infrastructure
        'migrations',
        'users',
        'password_reset_tokens',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',

        // Core Domain
        'academies',
        'academy_users',
        'courses',
        'course_levels',
        'modules',
        'lessons',
        'lesson_materials',
        'live_classes',
        'class_materials',
        'student_profiles',
        'enrollments',
        'video_progress',
        'assignments',
        'assignment_submissions',
        'quizzes',
        'quiz_submissions',
        'attendance_records',
        'certificates',
        'reward_rules',
        'reward_events',
        'reward_streaks',
        'notifications',
        'academy_notifications',
        'notification_reads',
        'support_tickets',
        'support_ticket_messages',
        'support_ticket_attachments',
        'ai_messages',
        'ai_faqs',
        'ai_materials',
        'chat_metrics',
        'audit_log',
        'login_attempts',
        'media',

        // Spatie Permissions
        'roles',
        'permissions',
        'model_has_permissions',
        'model_has_roles',
        'role_has_permissions',

        // LMS & Extensions
        'class_videos',
        'video_events',
        'practice_checks',
        'admissions',
        'settings',
        'portal_menus',
        'quiz_questions',
        'academy_ai_credentials',
        'trainer_profiles',
        'invitations',
        'course_categories',
        'course_trainers',
        'course_prerequisites',
        'lesson_prerequisites',
        'lesson_progress',
        'notification_preferences',
        'backups',
        'assessment_attempts',
        'assessment_attempt_answers',
        'question_banks',
        'questions',
        'question_bank_questions',
        'quiz_question_pools',
        '_orphaned_attempt_answers',
        'assignment_rubric_criteria',
        'assignment_submission_attachments',
        'assignment_evaluations',

        // Phase 5.1 Gradebook & Transcripts
        'course_grading_schemes',
        'grading_categories',
        'assessment_weights',
        'enrollment_grade_drafts',
        'enrollment_final_grades',
        'enrollment_current_grades',
        'grade_override_events',
        'assessment_result_publication_events',
        'enrollment_grade_lifecycle_events',
        'idempotency_records',
        'certificate_audit_events',

        self::IDENTITY_TABLE,
        self::PROBE_TABLE,
    ];

    public const KNOWN_MIGRATIONS = [
        '0001_01_01_000000_create_users_table',
        '0001_01_01_000001_create_cache_table',
        '0001_01_01_000002_create_jobs_table',
        '2026_01_01_000010_create_academies_table',
        '2026_01_01_000011_create_academy_users_table',
        '2026_01_01_000020_create_courses_table',
        '2026_01_01_000021_create_course_levels_table',
        '2026_01_01_000022_create_modules_table',
        '2026_01_01_000030_create_lessons_table',
        '2026_01_01_000031_create_lesson_materials_table',
        '2026_01_01_000032_create_live_classes_table',
        '2026_01_01_000033_create_class_materials_table',
        '2026_01_01_000040_create_student_profiles_table',
        '2026_01_01_000041_create_enrollments_table',
        '2026_01_01_000050_create_video_progress_table',
        '2026_01_01_000060_create_assignments_table',
        '2026_01_01_000061_create_assignment_submissions_table',
        '2026_01_01_000070_create_quizzes_table',
        '2026_01_01_000071_create_quiz_submissions_table',
        '2026_01_01_000080_create_attendance_records_table',
        '2026_01_01_000090_create_certificates_table',
        '2026_01_01_000100_create_reward_rules_table',
        '2026_01_01_000101_create_reward_events_table',
        '2026_01_01_000102_create_reward_streaks_table',
        '2026_01_01_000110_create_notifications_table',
        '2026_01_01_000111_create_academy_notifications_table',
        '2026_01_01_000112_create_notification_reads_table',
        '2026_01_01_000120_create_support_tickets_table',
        '2026_01_01_000130_create_ai_messages_table',
        '2026_01_01_000131_create_ai_faqs_table',
        '2026_01_01_000132_create_ai_materials_table',
        '2026_01_01_000140_create_chat_metrics_table',
        '2026_01_01_000150_create_audit_log_table',
        '2026_01_01_000151_create_login_attempts_table',
        '2026_01_01_000160_create_media_table',
        '2026_11_12_111728_create_permission_tables',
        '2026_11_12_120001_create_class_videos_table',
        '2026_11_12_120002_create_video_events_table',
        '2026_11_12_120003_create_practice_checks_table',
        '2026_11_12_120004_create_admissions_table',
        '2026_11_12_120005_create_settings_table',
        '2026_11_12_120006_create_portal_menus_table',
        '2026_11_12_120007_create_quiz_questions_table',
        '2026_11_12_120008_create_academy_ai_credentials_table',
        '2026_11_12_120219_add_domain_columns_to_academies_table',
        '2026_11_12_130000_add_branding_and_contact_to_academies_table',
        '2026_11_12_140000_add_status_and_phone_to_users_and_memberships_table',
        '2026_11_12_140001_update_student_profiles_for_multi_academy',
        '2026_11_12_140002_create_trainer_profiles_table',
        '2026_11_12_140003_create_invitations_table',
        '2026_11_12_150000_create_course_categories_table',
        '2026_11_12_150001_add_catalogue_fields_to_courses_table',
        '2026_11_12_150002_create_course_trainers_table',
        '2026_11_12_150003_create_course_prerequisites_table',
        '2026_11_12_160000_add_structure_fields_to_modules_and_lessons_table',
        '2026_11_12_160001_add_media_id_to_lesson_materials_table',
        '2026_11_12_160002_create_lesson_prerequisites_table',
        '2026_11_12_160003_create_lesson_progress_table',
        '2026_11_12_180000_normalize_single_academy_roles_and_data',
        '2026_11_12_190000_update_enrollments_table_for_phase25',
        '2026_11_13_120000_update_support_tickets_for_phase26',
        '2026_11_13_130000_add_author_name_to_support_ticket_messages',
        '2026_11_13_140000_make_user_id_nullable_on_support_ticket_messages',
        '2026_11_13_150000_create_notification_preferences_table',
        '2026_11_13_160000_create_backups_table',
        '2026_11_13_170000_update_lesson_progress_table_for_phase32a',
        '2026_11_13_170001_add_last_active_lesson_to_enrollments_table',
        '2026_11_13_180000_update_quizzes_table_for_phase34',
        '2026_11_13_180001_create_assessment_attempts_and_answers_tables',
        '2026_11_13_190000_update_certificates_table_for_phase35',
        '2026_11_14_100000_create_question_banks_and_pools_tables',
        '2026_11_15_100000_add_examination_and_atomic_recovery_fields',
        '2026_11_16_100000_update_assignments_table_for_phase43',
        '2026_11_16_100001_create_assignment_rubric_criteria_table',
        '2026_11_16_100002_update_assignment_submissions_table_for_phase43',
        '2026_11_16_100003_create_assignment_attachments_and_evaluations_tables',
        '2026_11_16_100004_enforce_historical_deletion_protection',
        '2026_11_16_110000_create_course_grading_schemes_table',
        '2026_11_16_110001_create_grading_categories_and_weights_tables',
        '2026_11_16_110002_create_enrollment_grades_tables',
        '2026_11_16_110003_create_gradebook_events_and_idempotency_tables',
        '2026_11_16_110004_install_gradebook_immutability_triggers',
        '2026_11_16_120000_enhance_grade_override_events_integrity',
        '2026_11_16_140000_enhance_certificates_for_phase53',
        '2026_11_16_150000_add_original_enrollment_id_to_certificates_table',
        '2026_11_16_160000_encrypt_certificate_verification_codes',
        '2026_11_17_100000_add_proficiency_to_student_profiles_table',
    ];

    public function __construct(
        private readonly ?string $envPath = null,
        private readonly ?string $customLockPath = null,
        private readonly ?string $customSqlitePath = null,
    ) {}

    public function lockFilePath(): string
    {
        return $this->customLockPath ?? storage_path('app/private/installed.lock');
    }

    public function sqliteDatabasePath(): string
    {
        if ($this->customSqlitePath !== null) {
            return $this->customSqlitePath;
        }

        $configPath = config('database.connections.sqlite.database');
        if (! empty($configPath) && $configPath !== ':memory:') {
            return $configPath;
        }

        return storage_path('app/private/database.sqlite');
    }

    /**
     * Official list of migration filenames in SEO Academy.
     *
     * @return list<string>
     */
    public static function getOfficialMigrations(): array
    {
        $files = glob(database_path('migrations/*.php'));
        if (! empty($files)) {
            return array_values(array_map(fn ($path) => pathinfo($path, PATHINFO_FILENAME), $files));
        }

        return self::KNOWN_MIGRATIONS;
    }

    /**
     * Retrieve the persistent installation identity record from the target database.
     *
     * @return array<string, string>|null
     */
    public function getInstallationIdentity(PDO $pdo, string $driver): ?array
    {
        try {
            if ($driver === 'sqlite') {
                $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name = '".self::IDENTITY_TABLE."'");
                if (! $stmt || ! $stmt->fetchColumn()) {
                    return null;
                }
            } else {
                $stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '".self::IDENTITY_TABLE."'");
                if (! $stmt || ! $stmt->fetchColumn()) {
                    return null;
                }
            }

            $stmt = $pdo->query('SELECT id, value FROM '.self::IDENTITY_TABLE);
            if (! $stmt) {
                return null;
            }

            $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            return is_array($rows) && ! empty($rows) ? $rows : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Persist the installation identity marker to claim the database.
     *
     * Requirements:
     * - Only called after establishing the database is safe to claim.
     * - Written before migrations so partial installations can be reliably identified.
     * - Never overwrites an existing identity marker belonging to an unrelated application.
     */
    public function persistInstallationIdentity(
        PDO $pdo,
        string $driver,
        string $status = 'in_progress',
        ?string $installationUuid = null
    ): string {
        if ($driver === 'sqlite') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS '.self::IDENTITY_TABLE.' (
                id VARCHAR(64) PRIMARY KEY,
                value TEXT,
                created_at VARCHAR(64)
            )');
        } else {
            $pdo->exec('CREATE TABLE IF NOT EXISTS '.self::IDENTITY_TABLE.' (
                id VARCHAR(64) PRIMARY KEY,
                value TEXT,
                created_at VARCHAR(64)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        }

        // Validate existing identity if present: never overwrite an unrelated application's marker
        $existing = $this->getInstallationIdentity($pdo, $driver);
        if ($existing !== null && isset($existing['app']) && $existing['app'] !== 'seo-academy') {
            throw new RuntimeException("Target database already contains an installation marker for an unrelated application ('{$existing['app']}'). It will not be overwritten.");
        }

        $uuid = $installationUuid ?? ($existing['installation_uuid'] ?? (string) Str::uuid());
        $now = now()->toIso8601String();

        $stmt = $pdo->prepare('REPLACE INTO '.self::IDENTITY_TABLE.' (id, value, created_at) VALUES (:id, :value, :created_at)');

        $records = [
            'app' => 'seo-academy',
            'installation_uuid' => $uuid,
            'status' => $status,
            'schema_version' => '1.0',
            $status === 'completed' ? 'completed_at' : 'initiated_at' => $now,
        ];

        foreach ($records as $k => $v) {
            $stmt->execute([
                ':id' => $k,
                ':value' => $v,
                ':created_at' => $now,
            ]);
        }

        return $uuid;
    }

    /**
     * Fail-closed check: returns true if application is installed, or if database state is ambiguous/unavailable.
     */
    public function isInstalled(): bool
    {
        if (file_exists($this->lockFilePath())) {
            return true;
        }

        $dbStatus = $this->getDatabaseInstallationStatus();

        // Fail-closed: 'installed', 'ambiguous', or 'database_unavailable' all block installation
        return in_array($dbStatus['status'], ['installed', 'ambiguous', 'database_unavailable'], true);
    }

    /**
     * Connect to the target database configured for installation.
     */
    public function getTargetPdo(?string $driver = null): ?PDO
    {
        $driver = $driver ?? config('database.default', 'sqlite');

        if ($driver === 'sqlite') {
            $path = $this->sqliteDatabasePath();
            if (! file_exists($path)) {
                return null;
            }

            return new PDO('sqlite:'.$path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        }

        if ($driver === 'mysql') {
            $host = config('database.connections.mysql.host', '127.0.0.1');
            $port = (int) (config('database.connections.mysql.port') ?: 3306);
            $database = config('database.connections.mysql.database');
            $username = config('database.connections.mysql.username');
            $password = (string) config('database.connections.mysql.password');

            if (empty($database)) {
                return null;
            }

            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);

            return new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 2,
            ]);
        }

        return null;
    }

    /**
     * Check persistent installed state in the database with fail-closed semantics:
     * - 'installed': Positively verified (Academy + Admin + Installation Marker).
     * - 'ambiguous': Inconsistent state (e.g. Academy exists without marker, or marker exists without Academy).
     * - 'database_unavailable': Database is configured in environment but unreachable.
     * - 'partial_installation': Clean partial migration state (known migrations, but neither academy nor marker exists).
     * - 'uninstalled': Fresh/empty database, ready for installation.
     * - 'unconfigured': Database is not configured in environment.
     *
     * @return array{status: string, message: string}
     */
    public function getDatabaseInstallationStatus(?PDO $targetPdo = null, ?string $driver = null): array
    {
        $driver = $driver ?? config('database.default', 'sqlite');

        try {
            $pdo = $targetPdo ?? $this->getTargetPdo($driver);
        } catch (Throwable $e) {
            return [
                'status' => 'database_unavailable',
                'message' => 'Configured database is unreachable: '.$this->sanitizeErrorMessage($e->getMessage()),
            ];
        }

        if (! $pdo instanceof PDO) {
            return [
                'status' => 'uninstalled',
                'message' => 'Target database is unconfigured or empty.',
            ];
        }

        try {
            if ($driver === 'sqlite') {
                $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                $tables = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
            } else {
                $stmt = $pdo->query('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = DATABASE()');
                $tables = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
            }

            $tables = array_values(array_filter($tables, fn ($t) => $t !== self::PROBE_TABLE));

            if (empty($tables)) {
                return [
                    'status' => 'uninstalled',
                    'message' => 'Target database is empty and uninstalled.',
                ];
            }

            $hasAcademies = in_array('academies', $tables, true);
            $hasUsers = in_array('users', $tables, true);
            $hasSettings = in_array('settings', $tables, true);
            $hasMigrations = in_array('migrations', $tables, true);

            if (! $hasAcademies && ! $hasUsers && ! $hasSettings) {
                if ($hasMigrations) {
                    $identity = $this->getInstallationIdentity($pdo, $driver);
                    if ($identity !== null && ($identity['app'] ?? '') === 'seo-academy' && ! empty($identity['installation_uuid'])) {
                        return [
                            'status' => 'partial_installation',
                            'message' => 'Partial installation detected with verified identity.',
                        ];
                    }

                    return [
                        'status' => 'ambiguous',
                        'message' => 'Database contains migrations but lacks a verified installation identity marker.',
                    ];
                }

                return [
                    'status' => 'uninstalled',
                    'message' => 'Target database has no academy tables.',
                ];
            }

            $academyCount = $hasAcademies ? (int) $pdo->query('SELECT COUNT(*) FROM academies')->fetchColumn() : 0;
            $academyExists = $academyCount > 0;

            // Check persistent installation marker
            $markerExists = false;
            if ($hasSettings) {
                try {
                    $stmt = $pdo->query("SELECT COUNT(*) FROM settings WHERE `group` = 'system' AND `key` = 'installed_at'");
                    if ($stmt && (int) $stmt->fetchColumn() > 0) {
                        $markerExists = true;
                    }
                } catch (Throwable) {
                    try {
                        $stmt = $pdo->query('SELECT COUNT(*) FROM settings WHERE "group" = \'system\' AND "key" = \'installed_at\'');
                        if ($stmt && (int) $stmt->fetchColumn() > 0) {
                            $markerExists = true;
                        }
                    } catch (Throwable) {
                    }
                }
            }

            if (! $markerExists && $academyExists) {
                try {
                    $settingsJson = $pdo->query('SELECT settings FROM academies LIMIT 1')->fetchColumn();
                    if ($settingsJson) {
                        $decoded = json_decode((string) $settingsJson, true);
                        if (is_array($decoded) && ! empty($decoded['installed_at'])) {
                            $markerExists = true;
                        }
                    }
                } catch (Throwable) {
                }
            }

            // Check administrator user
            $adminExists = false;
            if ($hasUsers) {
                if (in_array('roles', $tables, true) && in_array('model_has_roles', $tables, true)) {
                    try {
                        $stmt = $pdo->query("SELECT COUNT(*) FROM model_has_roles mhr JOIN roles r ON r.id = mhr.role_id WHERE r.name = 'admin'");
                        if ($stmt && (int) $stmt->fetchColumn() > 0) {
                            $adminExists = true;
                        }
                    } catch (Throwable) {
                        $adminExists = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
                    }
                } else {
                    $adminExists = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
                }
            }

            // Positive verification: Academy + Admin + Installation Marker
            if ($academyExists && $markerExists && $adminExists) {
                try {
                    $this->writeInstallLock($driver, [
                        'restored_from_db' => true,
                        'restored_at' => now()->toIso8601String(),
                    ]);
                    $this->persistInstallationIdentity($pdo, $driver, 'completed');
                } catch (Throwable) {
                }

                return [
                    'status' => 'installed',
                    'message' => 'Application is positively verified as installed in the database.',
                ];
            }

            $identity = $this->getInstallationIdentity($pdo, $driver);
            $hasVerifiedIdentity = ($identity !== null && ($identity['app'] ?? '') === 'seo-academy' && ! empty($identity['installation_uuid']));

            // Ambiguous / Inconsistent states: fail-closed, DO NOT regenerate lock
            if ($academyExists && ! $markerExists) {
                if ($hasVerifiedIdentity) {
                    return [
                        'status' => 'partial_installation',
                        'message' => 'Incomplete SEO Academy installation detected with verified identity.',
                    ];
                }

                return [
                    'status' => 'ambiguous',
                    'message' => 'Academy record exists but installation marker is missing. Installation state is ambiguous.',
                ];
            }

            if (! $academyExists && $markerExists) {
                return [
                    'status' => 'ambiguous',
                    'message' => 'Database installation marker exists but Academy record is missing. Installation state is ambiguous.',
                ];
            }

            if ($academyExists && ! $adminExists) {
                if ($hasVerifiedIdentity) {
                    return [
                        'status' => 'partial_installation',
                        'message' => 'Incomplete SEO Academy installation detected with verified identity.',
                    ];
                }

                return [
                    'status' => 'ambiguous',
                    'message' => 'Academy record exists without an associated administrator. Installation state is ambiguous.',
                ];
            }

            return [
                'status' => 'partial_installation',
                'message' => 'Partial installation detected.',
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'ambiguous',
                'message' => 'Error querying database schema: '.$this->sanitizeErrorMessage($e->getMessage()),
            ];
        }
    }

    /**
     * Check system requirements: PHP version, common/driver extensions, and writable directories.
     *
     * @param  string|null  $driver  'sqlite', 'mysql', or null (general pre-flight)
     * @return array{
     *     compatible: bool,
     *     driver: string|null,
     *     php: array{required: string, current: string, ok: bool},
     *     extensions: array<string, array{name: string, category: string, driver?: string, required: bool, loaded: bool, ok: bool}>,
     *     directories: array<string, array{path: string, writable: bool, exists: bool, ok: bool}>
     * }
     */
    public function checkSystemRequirements(?string $driver = null): array
    {
        $phpVersion = PHP_VERSION;
        $phpOk = version_compare($phpVersion, '8.3.0', '>=');

        $driver = in_array($driver, ['sqlite', 'mysql'], true) ? $driver : null;

        $extensions = [];
        $extensionsOk = true;

        // 1. Common required PHP extensions
        foreach (self::COMMON_REQUIRED_EXTENSIONS as $ext) {
            $loaded = extension_loaded($ext);
            if (! $loaded) {
                $extensionsOk = false;
            }
            $extensions[$ext] = [
                'name' => $ext,
                'category' => 'common',
                'required' => true,
                'loaded' => $loaded,
                'ok' => $loaded,
            ];
        }

        // 2. Driver-specific extensions
        $sqliteLoaded = extension_loaded('pdo_sqlite');
        $mysqlLoaded = extension_loaded('pdo_mysql');

        if ($driver === 'sqlite') {
            if (! $sqliteLoaded) {
                $extensionsOk = false;
            }
            $extensions['pdo_sqlite'] = [
                'name' => 'pdo_sqlite',
                'category' => 'driver',
                'driver' => 'sqlite',
                'required' => true,
                'loaded' => $sqliteLoaded,
                'ok' => $sqliteLoaded,
            ];
            $extensions['pdo_mysql'] = [
                'name' => 'pdo_mysql',
                'category' => 'driver',
                'driver' => 'mysql',
                'required' => false,
                'loaded' => $mysqlLoaded,
                'ok' => true,
            ];
        } elseif ($driver === 'mysql') {
            if (! $mysqlLoaded) {
                $extensionsOk = false;
            }
            $extensions['pdo_sqlite'] = [
                'name' => 'pdo_sqlite',
                'category' => 'driver',
                'driver' => 'sqlite',
                'required' => false,
                'loaded' => $sqliteLoaded,
                'ok' => true,
            ];
            $extensions['pdo_mysql'] = [
                'name' => 'pdo_mysql',
                'category' => 'driver',
                'driver' => 'mysql',
                'required' => true,
                'loaded' => $mysqlLoaded,
                'ok' => $mysqlLoaded,
            ];
        } else {
            // No driver selected yet: compatible if at least one database extension is loaded
            if (! $sqliteLoaded && ! $mysqlLoaded) {
                $extensionsOk = false;
            }
            $extensions['pdo_sqlite'] = [
                'name' => 'pdo_sqlite',
                'category' => 'driver',
                'driver' => 'sqlite',
                'required' => false,
                'loaded' => $sqliteLoaded,
                'ok' => $sqliteLoaded,
            ];
            $extensions['pdo_mysql'] = [
                'name' => 'pdo_mysql',
                'category' => 'driver',
                'driver' => 'mysql',
                'required' => false,
                'loaded' => $mysqlLoaded,
                'ok' => $mysqlLoaded,
            ];
        }

        // 3. Writable storage directories
        $directoriesToCheck = [
            'storage' => storage_path(),
            'storage/app' => storage_path('app'),
            'storage/app/private' => storage_path('app/private'),
            'storage/framework' => storage_path('framework'),
            'storage/framework/cache' => storage_path('framework/cache'),
            'storage/framework/sessions' => storage_path('framework/sessions'),
            'storage/framework/views' => storage_path('framework/views'),
            'storage/logs' => storage_path('logs'),
            'bootstrap/cache' => base_path('bootstrap/cache'),
        ];

        $directories = [];
        $dirsOk = true;

        foreach ($directoriesToCheck as $label => $path) {
            File::ensureDirectoryExists($path);
            $exists = file_exists($path);
            $writable = is_writable($path);
            $ok = $exists && $writable;

            if (! $ok) {
                $dirsOk = false;
            }

            $directories[$label] = [
                'path' => $path,
                'exists' => $exists,
                'writable' => $writable,
                'ok' => $ok,
            ];
        }

        // 4. Server Limits & PHP Runtime
        $memoryLimit = ini_get('memory_limit') ?: '128M';
        $executionTime = (int) (ini_get('max_execution_time') ?: 30);
        $uploadMax = ini_get('upload_max_filesize') ?: '2M';
        $postMax = ini_get('post_max_size') ?: '8M';

        // 5. SQLite version check (if sqlite extension is loaded)
        $sqliteVersion = null;
        $sqliteOk = true;
        if ($sqliteLoaded) {
            try {
                $probe = new PDO('sqlite::memory:');
                $sqliteVersion = (string) $probe->query('SELECT sqlite_version()')->fetchColumn();
                $sqliteOk = version_compare($sqliteVersion, '3.35.0', '>=');
            } catch (Throwable) {
                $sqliteOk = false;
            }
        }

        $warnings = [];
        $correctiveActions = [];

        if (! $phpOk) {
            $correctiveActions[] = 'Upgrade your PHP runtime to version 8.3.0 or later.';
        }
        foreach ($extensions as $extName => $extData) {
            if ($extData['required'] && ! $extData['loaded']) {
                $correctiveActions[] = "Enable the PHP '{$extName}' extension in your php.ini or hosting panel.";
            }
        }
        foreach ($directories as $dirName => $dirData) {
            if (! $dirData['ok']) {
                $correctiveActions[] = "Ensure directory '{$dirName}' exists and has write permissions (chmod 755 or 775).";
            }
        }
        if ($sqliteLoaded && ! $sqliteOk) {
            $warnings[] = "Installed SQLite library is {$sqliteVersion}. Version 3.35.0+ is recommended for modern SQL syntax.";
        }

        return [
            'compatible' => $phpOk && $extensionsOk && $dirsOk,
            'driver' => $driver,
            'php' => [
                'required' => '8.3.0',
                'current' => $phpVersion,
                'sapi' => PHP_SAPI,
                'ok' => $phpOk,
            ],
            'extensions' => $extensions,
            'directories' => $directories,
            'server_limits' => [
                'memory_limit' => $memoryLimit,
                'max_execution_time' => $executionTime,
                'upload_max_filesize' => $uploadMax,
                'post_max_size' => $postMax,
            ],
            'sqlite' => [
                'loaded' => $sqliteLoaded,
                'version' => $sqliteVersion,
                'ok' => $sqliteOk,
            ],
            'warnings' => $warnings,
            'corrective_actions' => $correctiveActions,
        ];
    }

    /**
     * Inspect database tables and migration history to reliably determine ownership:
     * - Distinguishes fresh empty databases.
     * - Distinguishes unrelated databases with foreign tables.
     * - Distinguishes unrelated Laravel databases with coincidental table names via migration history and schema check.
     * - Distinguishes completed SEO Academy installations.
     * - Distinguishes valid, recoverable partial SEO Academy installations.
     *
     * @return array{
     *     status: 'fresh'|'unrelated_tables'|'unrelated_database'|'installed_academy_exists'|'partial_installation'|'ambiguous',
     *     tables: list<string>,
     *     unrelated?: list<string>,
     *     foreign_migrations?: list<string>,
     *     academy_name?: string,
     *     migration_count?: int,
     *     message: string
     * }
     */
    public function inspectDatabase(PDO $pdo, string $driver): array
    {
        if ($driver === 'sqlite') {
            $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            $tables = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
        } else {
            $stmt = $pdo->query('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = DATABASE()');
            $tables = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
        }

        // Exclude probe table if left from connection test
        $tables = array_values(array_filter($tables, fn ($t) => $t !== self::PROBE_TABLE));

        if (empty($tables)) {
            return [
                'status' => 'fresh',
                'tables' => [],
                'message' => 'Target database is empty and ready for fresh installation.',
            ];
        }

        // Check for unrelated tables outside SEO Academy's known schema
        $unrelated = array_values(array_diff($tables, self::KNOWN_TABLES));
        if (! empty($unrelated)) {
            $preview = implode(', ', array_slice($unrelated, 0, 5)).(count($unrelated) > 5 ? '...' : '');

            return [
                'status' => 'unrelated_tables',
                'tables' => $tables,
                'unrelated' => $unrelated,
                'message' => "The target database contains unrelated existing tables ({$preview}). To protect your existing data, SEO Academy will not overwrite or alter an existing database. Please use a fresh, dedicated database.",
            ];
        }

        // If tables exist, check for the migrations table
        if (! in_array('migrations', $tables, true)) {
            return [
                'status' => 'unrelated_database',
                'tables' => $tables,
                'message' => 'The target database contains existing tables without an SEO Academy migration history. To protect existing data, setup cannot proceed.',
            ];
        }

        // Inspect migration history to distinguish foreign/unrelated Laravel databases
        try {
            $executedMigrations = $pdo->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
            $officialMigrations = $this->getOfficialMigrations();
            $foreignMigrations = array_values(array_diff($executedMigrations, $officialMigrations));

            if (! empty($foreignMigrations)) {
                $preview = implode(', ', array_slice($foreignMigrations, 0, 3));

                return [
                    'status' => 'unrelated_database',
                    'tables' => $tables,
                    'foreign_migrations' => $foreignMigrations,
                    'message' => "The target database contains migration history from an unrelated application ({$preview}). SEO Academy will not modify an existing application database.",
                ];
            }
        } catch (Throwable) {
            return [
                'status' => 'unrelated_database',
                'tables' => $tables,
                'message' => 'Unable to read migrations table. Database ownership cannot be confirmed.',
            ];
        }

        // Schema validation: SEO Academy uses UUIDs for users.id
        if (in_array('users', $tables, true)) {
            try {
                if ($driver === 'sqlite') {
                    $cols = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($cols as $col) {
                        if ($col['name'] === 'id' && stripos((string) ($col['type'] ?? ''), 'int') !== false) {
                            return [
                                'status' => 'unrelated_database',
                                'tables' => $tables,
                                'message' => 'The target database contains an incompatible users table schema (integer primary key). This database belongs to an unrelated application.',
                            ];
                        }
                    }
                } else {
                    $colType = $pdo->query("SELECT DATA_TYPE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'id'")->fetchColumn();
                    if ($colType && in_array(strtolower((string) $colType), ['int', 'bigint', 'mediumint', 'smallint', 'tinyint'], true)) {
                        return [
                            'status' => 'unrelated_database',
                            'tables' => $tables,
                            'message' => 'The target database contains an incompatible users table schema (integer primary key). This database belongs to an unrelated application.',
                        ];
                    }
                }
            } catch (Throwable) {
                // Ignore schema column inspect failure
            }
        }

        // Check for persistent installation marker
        $markerExists = false;
        if (in_array('settings', $tables, true)) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM settings WHERE `group` = 'system' AND `key` = 'installed_at'");
                if ($stmt && (int) $stmt->fetchColumn() > 0) {
                    $markerExists = true;
                }
            } catch (Throwable) {
                try {
                    $stmt = $pdo->query('SELECT COUNT(*) FROM settings WHERE "group" = \'system\' AND "key" = \'installed_at\'');
                    if ($stmt && (int) $stmt->fetchColumn() > 0) {
                        $markerExists = true;
                    }
                } catch (Throwable) {
                }
            }
        }

        if (! $markerExists && in_array('academies', $tables, true)) {
            try {
                $settingsJson = $pdo->query('SELECT settings FROM academies LIMIT 1')->fetchColumn();
                if ($settingsJson) {
                    $decoded = json_decode((string) $settingsJson, true);
                    if (is_array($decoded) && ! empty($decoded['installed_at'])) {
                        $markerExists = true;
                    }
                }
            } catch (Throwable) {
            }
        }

        // Check if an initialized academy exists
        $academyCount = 0;
        $academyName = null;
        if (in_array('academies', $tables, true)) {
            try {
                $academyCount = (int) $pdo->query('SELECT COUNT(*) FROM academies')->fetchColumn();
                if ($academyCount > 0) {
                    $name = $pdo->query('SELECT name FROM academies LIMIT 1')->fetchColumn();
                    $academyName = $name ?: 'Primary Academy';
                }
            } catch (Throwable) {
            }
        }

        // Check if users exist in users table
        $userCount = 0;
        if (in_array('users', $tables, true)) {
            try {
                $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            } catch (Throwable) {
            }
        }

        // If completed (installation marker exists): block reinstallation
        if ($markerExists && ($academyCount > 0 || $userCount > 0)) {
            return [
                'status' => 'installed_academy_exists',
                'tables' => $tables,
                'academy_name' => (string) ($academyName ?: 'Existing Academy'),
                'message' => "The target database already contains an initialized SEO Academy installation ('".($academyName ?: 'Existing Academy')."'). Reinstallation is blocked to protect existing data.",
            ];
        }

        // Validate the persistent installation identity marker
        $identity = $this->getInstallationIdentity($pdo, $driver);
        if ($identity === null || ($identity['app'] ?? '') !== 'seo-academy' || empty($identity['installation_uuid'])) {
            return [
                'status' => 'ambiguous',
                'tables' => $tables,
                'message' => 'The target database contains schema resembling SEO Academy but lacks a verified installation identity marker. To prevent accidental data collision or hijacking, automatic resumption is rejected. Manual verification is required.',
            ];
        }

        // Interrupted or partial installation (identity verified): recoverable through positive association
        $migrationCount = count($executedMigrations ?? []);

        return [
            'status' => 'partial_installation',
            'tables' => $tables,
            'academy_name' => $academyName ? (string) $academyName : null,
            'migration_count' => $migrationCount,
            'installation_uuid' => $identity['installation_uuid'],
            'message' => "Incomplete SEO Academy installation detected ({$migrationCount} migrations completed, identity verified). Setup can safely resume.",
        ];
    }

    /**
     * Validate SQLite storage directory/file, check SQLite version >= 3.35.0, verify PDO connectivity, and inspect existing tables.
     *
     * @return array{ok: bool, driver: string, message: string, engine?: string, version?: string}
     */
    public function testSqliteConnection(?string $path = null): array
    {
        $sqlitePath = $path ?? $this->sqliteDatabasePath();

        try {
            $this->ensureSqliteFileReady($sqlitePath);

            $pdo = new PDO('sqlite:'.$sqlitePath, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->exec('PRAGMA foreign_keys = ON;');
            $pdo->query('SELECT 1')->fetchColumn();

            $sqliteVersion = (string) $pdo->query('SELECT sqlite_version()')->fetchColumn();
            if (version_compare($sqliteVersion, '3.35.0', '<')) {
                return [
                    'ok' => false,
                    'driver' => 'sqlite',
                    'engine' => 'SQLite',
                    'version' => $sqliteVersion,
                    'message' => "Unsupported SQLite version ({$sqliteVersion}). SEO Academy requires SQLite 3.35.0 or higher.",
                ];
            }

            $inspection = $this->inspectDatabase($pdo, 'sqlite');
            if (in_array($inspection['status'], ['unrelated_tables', 'unrelated_database', 'installed_academy_exists', 'ambiguous'], true)) {
                return [
                    'ok' => false,
                    'driver' => 'sqlite',
                    'engine' => 'SQLite',
                    'version' => $sqliteVersion,
                    'message' => $inspection['message'],
                ];
            }

            return [
                'ok' => true,
                'driver' => 'sqlite',
                'engine' => 'SQLite',
                'version' => $sqliteVersion,
                'message' => $inspection['status'] === 'partial_installation'
                    ? $inspection['message']
                    : "SQLite connection verified (SQLite {$sqliteVersion}). Storage directory is secure and writable.",
            ];
        } catch (Throwable $e) {
            $sanitized = $this->sanitizeErrorMessage($e->getMessage());
            Log::warning('Database installer SQLite check failed.', ['error' => $sanitized]);

            return [
                'ok' => false,
                'driver' => 'sqlite',
                'message' => $sanitized,
            ];
        }
    }

    /**
     * Validate MySQL PDO connectivity, detect engine (MySQL vs MariaDB), verify minimum versions (MySQL 8.0+ / MariaDB 10.11+),
     * verify InnoDB support, test query execution, required DDL/DML permissions, and inspect existing tables.
     *
     * @param  array{host: string, port?: int|string|null, database: string, username: string, password?: string|null}  $config
     * @return array{ok: bool, driver: string, message: string, engine?: string, version?: string, raw_version?: string, innodb?: bool}
     */
    public function testMySqlConnection(array $config): array
    {
        $host = trim((string) ($config['host'] ?? ''));
        $port = (int) ($config['port'] ?? 3306);
        $database = trim((string) ($config['database'] ?? ''));
        $username = trim((string) ($config['username'] ?? ''));
        $password = (string) ($config['password'] ?? '');

        if ($host === '' || $database === '' || $username === '') {
            return [
                'ok' => false,
                'driver' => 'mysql',
                'message' => 'Host, database name, and username are required.',
            ];
        }

        if ($port < 1 || $port > 65535) {
            $port = 3306;
        }

        $pdo = null;

        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);

            // 1. Detect server engine and version
            $rawVersion = (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
            $isMariaDb = stripos($rawVersion, 'mariadb') !== false;
            $engineName = $isMariaDb ? 'MariaDB' : 'MySQL';

            preg_match('/(\d+\.\d+(\.\d+)?)/', $rawVersion, $matches);
            $cleanVersion = $matches[1] ?? $rawVersion;

            // 2. Validate version compatibility
            if ($isMariaDb) {
                if (version_compare($cleanVersion, '10.11.0', '<')) {
                    return [
                        'ok' => false,
                        'driver' => 'mysql',
                        'engine' => 'MariaDB',
                        'version' => $cleanVersion,
                        'raw_version' => $rawVersion,
                        'message' => "Unsupported MariaDB version ({$cleanVersion}). SEO Academy requires MariaDB 10.11+ LTS with InnoDB.",
                    ];
                }
            } else {
                if (version_compare($cleanVersion, '8.0.0', '<')) {
                    return [
                        'ok' => false,
                        'driver' => 'mysql',
                        'engine' => 'MySQL',
                        'version' => $cleanVersion,
                        'raw_version' => $rawVersion,
                        'message' => "Unsupported MySQL version ({$cleanVersion}). SEO Academy requires MySQL 8.4+ (minimum 8.0) with InnoDB.",
                    ];
                }
            }

            // 3. Execute basic test query & verify InnoDB storage engine
            $pdo->query('SELECT 1')->fetchColumn();
            $this->assertInnoDbSupported($pdo);

            // 4. Verify required DDL/DML permissions
            $this->assertMySqlPermissions($pdo);

            // 5. Inspect database schema tables
            $inspection = $this->inspectDatabase($pdo, 'mysql');
            if (in_array($inspection['status'], ['unrelated_tables', 'unrelated_database', 'installed_academy_exists', 'ambiguous'], true)) {
                return [
                    'ok' => false,
                    'driver' => 'mysql',
                    'engine' => $engineName,
                    'version' => $cleanVersion,
                    'raw_version' => $rawVersion,
                    'message' => $inspection['message'],
                ];
            }

            return [
                'ok' => true,
                'driver' => 'mysql',
                'engine' => $engineName,
                'version' => $cleanVersion,
                'raw_version' => $rawVersion,
                'innodb' => true,
                'message' => $inspection['status'] === 'partial_installation'
                    ? $inspection['message']
                    : "Successfully connected to {$engineName} {$cleanVersion}. InnoDB storage engine and schema privileges verified.",
            ];
        } catch (Throwable $e) {
            $sanitized = $this->sanitizeErrorMessage($e->getMessage(), $password, $username);
            Log::warning('Database installer MySQL check failed.', ['error' => $sanitized]);

            return [
                'ok' => false,
                'driver' => 'mysql',
                'message' => $sanitized,
            ];
        } finally {
            if ($pdo instanceof PDO) {
                try {
                    $pdo->exec('DROP TABLE IF EXISTS '.self::PROBE_TABLE);
                } catch (Throwable) {
                    // Best-effort cleanup of probe table.
                }
            }
        }
    }

    /**
     * Execute complete end-to-end installation:
     * 1. Validate requirements, database connection & inspect existing tables
     * 2. Write database configuration atomically to .env
     * 3. Run pending migrations & Spatie role definitions (resumes partial setups safely)
     * 4. Safe recovery / creation of single Academy profile & Initial Administrator
     * 5. Record persistent installed state in DB & write install lock file
     *
     * @param array{
     *     driver: string,
     *     host?: string|null,
     *     port?: int|string|null,
     *     database?: string|null,
     *     username?: string|null,
     *     password?: string|null
     * } $dbConfig
     * @param array{
     *     name: string,
     *     slug: string,
     *     support_email: string,
     *     timezone?: string|null,
     *     locale?: string|null
     * } $academyConfig
     * @param array{
     *     name: string,
     *     email: string,
     *     password: string
     * } $adminConfig
     * @return array{ok: bool, academy: Academy, admin: User, message: string}
     */
    public function executeCompleteInstallation(
        array $dbConfig,
        array $academyConfig,
        array $adminConfig
    ): array {
        if (file_exists($this->lockFilePath())) {
            throw new RuntimeException('Application is already installed or installation is locked.');
        }

        $driver = $dbConfig['driver'] ?? 'sqlite';

        // 1. Configure, inspect, and verify database connection
        if ($driver === 'sqlite') {
            $check = $this->testSqliteConnection();
            if (! $check['ok']) {
                throw new RuntimeException($check['message']);
            }

            $sqlitePath = str_replace('\\', '/', $this->sqliteDatabasePath());
            $this->persistEnv([
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => $sqlitePath,
            ]);

            config([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => $sqlitePath,
            ]);

            DB::purge('sqlite');
            DB::reconnect('sqlite');
        } elseif ($driver === 'mysql') {
            $check = $this->testMySqlConnection($dbConfig);
            if (! $check['ok']) {
                throw new RuntimeException($check['message']);
            }

            $host = trim((string) ($dbConfig['host'] ?? '127.0.0.1'));
            $port = (string) ((int) ($dbConfig['port'] ?? 3306) ?: 3306);
            $database = trim((string) ($dbConfig['database'] ?? ''));
            $username = trim((string) ($dbConfig['username'] ?? ''));
            $password = (string) ($dbConfig['password'] ?? '');

            $this->persistEnv([
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => $host,
                'DB_PORT' => $port,
                'DB_DATABASE' => $database,
                'DB_USERNAME' => $username,
                'DB_PASSWORD' => $password,
            ]);

            config([
                'database.default' => 'mysql',
                'database.connections.mysql.host' => $host,
                'database.connections.mysql.port' => $port,
                'database.connections.mysql.database' => $database,
                'database.connections.mysql.username' => $username,
                'database.connections.mysql.password' => $password,
            ]);

            DB::purge('mysql');
            DB::reconnect('mysql');
        } else {
            throw new RuntimeException("Unsupported database driver: [{$driver}]");
        }

        // Check if database is already positively verified as installed
        $targetPdo = $this->getTargetPdo($driver);
        $dbStatus = $this->getDatabaseInstallationStatus($targetPdo, $driver);
        if ($dbStatus['status'] === 'installed') {
            throw new RuntimeException('An initialized SEO Academy is already present in this database. Reinstallation is blocked.');
        }
        if ($dbStatus['status'] === 'database_unavailable') {
            throw new RuntimeException($dbStatus['message']);
        }
        if ($dbStatus['status'] === 'ambiguous') {
            throw new RuntimeException('The target database is in an ambiguous state and cannot be safely installed. '.$dbStatus['message']);
        }

        // Claim database and persist installation identity marker before running migrations
        if ($targetPdo instanceof PDO) {
            $this->persistInstallationIdentity($targetPdo, $driver, 'in_progress');
        }

        // 2. Run pending database migrations & Spatie role definitions
        $this->runMigrationsAndSeeders(seedRoles: true);

        // 3. Positively associated creation or safe recovery of Academy and Administrator
        $created = DB::transaction(function () use ($academyConfig, $adminConfig): array {
            $existingAcademy = Academy::first();
            if ($existingAcademy) {
                // Positively verify association with the same installation
                $inputSlug = Str::slug((string) ($academyConfig['slug'] ?: $academyConfig['name']));
                $inputName = trim((string) $academyConfig['name']);

                if ($existingAcademy->slug !== $inputSlug && $existingAcademy->name !== $inputName) {
                    throw new RuntimeException(
                        "An existing academy ('{$existingAcademy->name}') is already present in this database with a different identity. ".
                        'The installer will not overwrite or alter an existing academy. '.
                        'Please use a clean database or complete configuration manually.'
                    );
                }

                // Positively associated: reuse without overwriting or altering configuration
                $academy = $existingAcademy;
            } else {
                $academy = Academy::create([
                    'name' => trim((string) $academyConfig['name']),
                    'slug' => Str::slug((string) ($academyConfig['slug'] ?: $academyConfig['name'])),
                    'support_email' => strtolower(trim((string) $academyConfig['support_email'])),
                    'timezone' => $academyConfig['timezone'] ?? 'UTC',
                    'locale' => $academyConfig['locale'] ?? 'en',
                    'is_active' => true,
                    'settings' => [
                        'installed_at' => now()->toIso8601String(),
                        'theme' => 'light',
                    ],
                ]);
            }

            $userCount = User::count();
            $inputEmail = strtolower(trim((string) $adminConfig['email']));
            $existingAdmin = User::where('email', $inputEmail)->first();

            if ($userCount > 0) {
                if (! $existingAdmin) {
                    throw new RuntimeException(
                        "The target database already contains user accounts that do not match the specified administrator email ('{$inputEmail}'). ".
                        'To prevent account collisions or hijacking, recovery is halted. '.
                        'Please verify database ownership or inspect the users table manually.'
                    );
                }

                if (! Hash::check($adminConfig['password'], $existingAdmin->password)) {
                    throw new RuntimeException(
                        "An administrator account with email '{$inputEmail}' already exists in this database, but the provided password does not match. ".
                        'The installer will never reset or overwrite an existing administrator password. '.
                        'Please provide the existing administrator password to resume setup, or use a fresh database.'
                    );
                }

                // Positively verified administrator: preserve credentials, name, and status
                $adminUser = $existingAdmin;
            } else {
                $adminUser = User::create([
                    'name' => trim((string) $adminConfig['name']),
                    'email' => $inputEmail,
                    'password' => Hash::make($adminConfig['password']),
                    'is_active' => true,
                ]);
                $adminUser->email_verified_at = now();
                $adminUser->save();
            }

            // Ensure local 'admin' role is assigned
            if (! $adminUser->hasRole('admin')) {
                $adminUser->assignRole('admin');
            }

            // Attach user to academy pivot if not already attached
            if (! $adminUser->academies()->where('academies.id', $academy->id)->exists()) {
                $adminUser->academies()->attach($academy->id, [
                    'id' => (string) Str::uuid(),
                    'role' => 'admin',
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Record persistent database state in settings table
            if (Schema::hasTable('settings')) {
                DB::table('settings')->updateOrInsert(
                    [
                        'academy_id' => $academy->id,
                        'group' => 'system',
                        'key' => 'installed_at',
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'value' => now()->toIso8601String(),
                        'type' => 'string',
                        'is_public' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            return [
                'academy' => $academy,
                'admin' => $adminUser,
            ];
        });

        // Mark installation identity as completed
        if ($targetPdo instanceof PDO) {
            try {
                $this->persistInstallationIdentity($targetPdo, $driver, 'completed');
            } catch (Throwable) {
            }
        }

        // 4. Securely write install lock file
        $this->writeInstallLock($driver, [
            'academy_id' => $created['academy']->id,
            'academy_name' => $created['academy']->name,
            'admin_email' => $created['admin']->email,
        ]);

        return [
            'ok' => true,
            'academy' => $created['academy'],
            'admin' => $created['admin'],
            'message' => 'SEO Academy installed and configured successfully.',
        ];
    }

    public function writeInstallLock(string $driver, array $meta = []): void
    {
        $lockPath = $this->lockFilePath();
        $directory = dirname($lockPath);

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create private storage directory for install lock.');
        }

        $payload = json_encode(array_merge([
            'installed_at' => now()->toIso8601String(),
            'driver' => $driver,
        ], $meta), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

        if (@file_put_contents($lockPath, $payload, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write installed.lock file.');
        }
    }

    public function sanitizeErrorMessage(string $message, ?string $password = null, ?string $username = null): string
    {
        $sanitized = $message;

        if ($password !== null && $password !== '') {
            $needles = array_unique(array_filter([
                $password,
                urlencode($password),
                rawurlencode($password),
                addslashes($password),
                htmlspecialchars($password, ENT_QUOTES, 'UTF-8'),
            ], static fn (string $value): bool => $value !== ''));

            $sanitized = str_replace($needles, '[REDACTED]', $sanitized);
        }

        if ($username !== null && $username !== '') {
            $sanitized = preg_replace(
                '/using password:\s*(YES|NO)/i',
                'using password: [REDACTED]',
                $sanitized,
            ) ?? $sanitized;
        }

        $sanitized = preg_replace(
            '/(password|passwd|pwd)\s*([:=])\s*([^\s,;)\]]+)/i',
            '$1$2[REDACTED]',
            $sanitized,
        ) ?? $sanitized;

        $sanitized = preg_replace('/(DB_PASSWORD=)([^\s]+)/i', '$1[REDACTED]', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/(APP_KEY=)([^\s]+)/i', '$1[REDACTED]', $sanitized) ?? $sanitized;

        return $sanitized;
    }

    private function ensureSqliteFileReady(string $sqlitePath): void
    {
        $directory = dirname($sqlitePath);

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create private SQLite storage directory.');
        }

        if (! is_writable($directory)) {
            throw new RuntimeException('Private SQLite storage directory is not writable.');
        }

        if (! file_exists($sqlitePath) && ! @touch($sqlitePath)) {
            throw new RuntimeException('Unable to create SQLite database file.');
        }

        if (! is_writable($sqlitePath)) {
            throw new RuntimeException('SQLite database file is not writable.');
        }
    }

    private function assertInnoDbSupported(PDO $pdo): void
    {
        $stmt = $pdo->query('SHOW ENGINES');
        $engines = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        foreach ($engines as $engine) {
            $name = strtoupper((string) ($engine['Engine'] ?? ''));
            $support = strtoupper((string) ($engine['Support'] ?? ''));

            if ($name === 'INNODB' && in_array($support, ['YES', 'DEFAULT'], true)) {
                return;
            }
        }

        throw new RuntimeException('InnoDB storage engine is not enabled on the target MySQL server.');
    }

    private function assertMySqlPermissions(PDO $pdo): void
    {
        $table = self::PROBE_TABLE;

        $pdo->exec("DROP TABLE IF EXISTS {$table}");
        $pdo->exec("CREATE TABLE {$table} (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, probe_key VARCHAR(64) NOT NULL) ENGINE=InnoDB");
        $pdo->exec("ALTER TABLE {$table} ADD INDEX idx_probe_key (probe_key)");
        $pdo->exec("INSERT INTO {$table} (probe_key) VALUES ('ok')");
        $value = $pdo->query("SELECT probe_key FROM {$table} WHERE probe_key = 'ok' LIMIT 1")->fetchColumn();

        if ($value !== 'ok') {
            throw new RuntimeException('MySQL SELECT permission verification failed.');
        }

        $pdo->exec("UPDATE {$table} SET probe_key = 'updated' WHERE probe_key = 'ok'");
        $pdo->exec("DELETE FROM {$table} WHERE probe_key = 'updated'");
        $pdo->exec("DROP TABLE {$table}");
    }

    private function runMigrationsAndSeeders(bool $seedRoles): void
    {
        Artisan::call('migrate', ['--force' => true]);

        if ($seedRoles && class_exists(RoleSeeder::class)) {
            Artisan::call('db:seed', [
                '--class' => RoleSeeder::class,
                '--force' => true,
            ]);
        }
    }

    /**
     * Atomically write configuration settings to .env file preserving all unrelated settings.
     *
     * @param  array<string, string>  $values
     */
    public function persistEnv(array $values): void
    {
        $envFile = $this->envPath ?? base_path('.env');
        $directory = dirname($envFile);

        if (! file_exists($envFile)) {
            $example = base_path('.env.example');
            if (file_exists($example)) {
                @copy($example, $envFile);
            } else {
                @touch($envFile);
            }
        }

        $content = file_exists($envFile) ? (string) file_get_contents($envFile) : '';

        foreach ($values as $key => $value) {
            $value = str_replace(["\r", "\n"], '', (string) $value);
            $formatted = $this->formatEnvValue($value);
            $line = "{$key}={$formatted}";

            $activePattern = '/^'.preg_quote($key, '/').'=.*$/m';
            $commentedPattern = '/^#\s*'.preg_quote($key, '/').'=.*$/m';

            if (preg_match($activePattern, $content) === 1) {
                $content = preg_replace($activePattern, $line, $content, 1) ?? $content;
            } elseif (preg_match($commentedPattern, $content) === 1) {
                $content = preg_replace($commentedPattern, $line, $content, 1) ?? $content;
            } else {
                $separator = ($content !== '' && ! str_ends_with($content, "\n")) ? PHP_EOL : '';
                $content .= $separator.$line.PHP_EOL;
            }
        }

        // Atomic file write using temporary file in same directory
        $tempFile = $directory.DIRECTORY_SEPARATOR.'env_'.bin2hex(random_bytes(8)).'.tmp';
        if (@file_put_contents($tempFile, $content, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write temporary environment configuration file.');
        }

        @chmod($tempFile, 0640);

        if (! @rename($tempFile, $envFile)) {
            if (@copy($tempFile, $envFile)) {
                @unlink($tempFile);
            } else {
                @unlink($tempFile);
                throw new RuntimeException('Unable to write database configuration to .env.');
            }
        }
    }

    public function formatEnvValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/[\s#"\'$\\\\=]/', $value) === 1) {
            $escaped = str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value);

            return '"'.$escaped.'"';
        }

        return $value;
    }

    /**
     * Inspect Composer dependencies and validate required packages.
     *
     * @return array{
     *     compatible: bool,
     *     has_composer_json: bool,
     *     has_autoload: bool,
     *     has_installed_json: bool,
     *     packages: array<string, array{required: string, installed: string|null, ok: bool}>,
     *     missing: list<string>
     * }
     */
    public function checkComposerDependencies(): array
    {
        $composerJsonPath = base_path('composer.json');
        $autoloadPath = base_path('vendor/autoload.php');
        $installedJsonPath = base_path('vendor/composer/installed.json');

        $hasComposerJson = file_exists($composerJsonPath);
        $hasAutoload = file_exists($autoloadPath);
        $hasInstalledJson = file_exists($installedJsonPath);

        $requiredCore = [
            'laravel/framework' => '^13.17',
            'livewire/livewire' => '^4.4',
            'dompdf/dompdf' => '^3.1',
            'spatie/laravel-permission' => '^8.5',
        ];

        $packages = [];
        $missing = [];
        $installedMap = [];

        if ($hasInstalledJson) {
            $installedData = json_decode((string) file_get_contents($installedJsonPath), true);
            $rawPackages = $installedData['packages'] ?? [];
            foreach ($rawPackages as $pkg) {
                if (isset($pkg['name'], $pkg['version'])) {
                    $installedMap[$pkg['name']] = (string) $pkg['version'];
                }
            }
        }

        foreach ($requiredCore as $name => $constraint) {
            $installedVersion = $installedMap[$name] ?? null;
            $ok = $installedVersion !== null;
            if (! $ok) {
                $missing[] = $name;
            }
            $packages[$name] = [
                'required' => $constraint,
                'installed' => $installedVersion,
                'ok' => $ok,
            ];
        }

        $compatible = $hasComposerJson && $hasAutoload && empty($missing);

        return [
            'compatible' => $compatible,
            'has_composer_json' => $hasComposerJson,
            'has_autoload' => $hasAutoload,
            'has_installed_json' => $hasInstalledJson,
            'packages' => $packages,
            'missing' => $missing,
        ];
    }

    /**
     * Inspect .env file existence, APP_KEY presence, and public protection.
     *
     * @return array{
     *     compatible: bool,
     *     has_env: bool,
     *     has_key: bool,
     *     is_protected: bool,
     *     message: string
     * }
     */
    public function checkEnvironmentSetup(): array
    {
        $envPath = $this->envPath ?? base_path('.env');
        $hasEnv = file_exists($envPath);
        $hasKey = false;

        if ($hasEnv) {
            $content = (string) file_get_contents($envPath);
            if (preg_match('/^APP_KEY=(.+)$/m', $content, $matches)) {
                $val = trim($matches[1]);
                $hasKey = (str_starts_with($val, 'base64:') || strlen($val) >= 32);
            }
        }

        // Verify .env is located outside web root (public)
        $isProtected = ! str_starts_with(realpath($envPath) ?: '', realpath(public_path()) ?: '___none___');

        $compatible = $hasEnv && $hasKey && $isProtected;

        return [
            'compatible' => $compatible,
            'has_env' => $hasEnv,
            'has_key' => $hasKey,
            'is_protected' => $isProtected,
            'message' => $compatible
                ? 'Environment file is securely initialized with an active application key.'
                : 'Environment file (.env) or application key requires setup.',
        ];
    }

    /**
     * Staged database configuration with switching protection.
     *
     * @param array{
     *     driver: string,
     *     host?: string|null,
     *     port?: int|string|null,
     *     database?: string|null,
     *     username?: string|null,
     *     password?: string|null,
     *     path?: string|null
     * } $dbConfig
     * @return array{ok: bool, requires_confirmation?: bool, message: string}
     */
    public function configureDatabase(array $dbConfig, bool $confirmSwitch = false): array
    {
        $driver = $dbConfig['driver'] ?? 'sqlite';
        $currentDriver = config('database.default', 'sqlite');

        // Check if database driver is changing on an existing installation with tables
        if ($driver !== $currentDriver && ! $confirmSwitch) {
            try {
                $currentPdo = $this->getTargetPdo($currentDriver);
                if ($currentPdo instanceof PDO) {
                    $status = $this->getDatabaseInstallationStatus($currentPdo, $currentDriver);
                    if (in_array($status['status'], ['installed', 'partial_installation', 'installed_academy_exists'], true)) {
                        return [
                            'ok' => false,
                            'requires_confirmation' => true,
                            'message' => "The previous database ({$currentDriver}) contains existing records. Switching to {$driver} will not migrate existing data. Please confirm you wish to switch.",
                        ];
                    }
                }
            } catch (Throwable) {
                // If previous DB is unreachable, allow switching
            }
        }

        if ($driver === 'sqlite') {
            $test = $this->testSqliteConnection($dbConfig['path'] ?? null);
            if (! $test['ok']) {
                return $test;
            }

            $path = str_replace('\\', '/', $dbConfig['path'] ?? $this->sqliteDatabasePath());
            $this->persistEnv([
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => $path,
            ]);

            config([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => $path,
            ]);
            DB::purge('sqlite');
        } else {
            $test = $this->testMySqlConnection($dbConfig);
            if (! $test['ok']) {
                return $test;
            }

            $this->persistEnv([
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => (string) ($dbConfig['host'] ?? '127.0.0.1'),
                'DB_PORT' => (int) ($dbConfig['port'] ?? 3306),
                'DB_DATABASE' => (string) ($dbConfig['database'] ?? ''),
                'DB_USERNAME' => (string) ($dbConfig['username'] ?? ''),
                'DB_PASSWORD' => (string) ($dbConfig['password'] ?? ''),
            ]);

            config([
                'database.default' => 'mysql',
                'database.connections.mysql.host' => $dbConfig['host'] ?? '127.0.0.1',
                'database.connections.mysql.port' => $dbConfig['port'] ?? 3306,
                'database.connections.mysql.database' => $dbConfig['database'] ?? '',
                'database.connections.mysql.username' => $dbConfig['username'] ?? '',
                'database.connections.mysql.password' => $dbConfig['password'] ?? '',
            ]);
            DB::purge('mysql');
        }

        return [
            'ok' => true,
            'message' => "Database configuration successfully updated and verified for {$driver}.",
        ];
    }

    /**
     * Execute migrations and establish core security roles.
     *
     * @return array{ok: bool, count: int, message: string}
     */
    public function runMigrationsAndSeedRoles(): array
    {
        $driver = config('database.default', 'sqlite');
        $pdo = $this->getTargetPdo($driver);

        if (! $pdo instanceof PDO) {
            throw new RuntimeException("Unable to establish PDO connection for {$driver} prior to migration.");
        }

        // Claim database identity
        $this->persistInstallationIdentity($pdo, $driver, 'in_progress');

        // Execute all migrations
        $this->runMigrationsAndSeeders(seedRoles: true);

        return [
            'ok' => true,
            'count' => count(self::KNOWN_MIGRATIONS),
            'message' => 'All application database migrations and role definitions executed successfully.',
        ];
    }

    /**
     * Verify filesystem storage permissions and directory layout.
     *
     * @return array{compatible: bool, directories: array<string, array{path: string, exists: bool, writable: bool, ok: bool}>}
     */
    public function verifyFilesystemStorage(): array
    {
        $dirs = [
            'storage/app/private' => storage_path('app/private'),
            'storage/app/private/media' => storage_path('app/private/media'),
            'storage/app/private/backups' => storage_path('app/private/backups'),
            'storage/framework/cache' => storage_path('framework/cache'),
            'storage/framework/sessions' => storage_path('framework/sessions'),
            'storage/framework/views' => storage_path('framework/views'),
            'storage/logs' => storage_path('logs'),
            'bootstrap/cache' => base_path('bootstrap/cache'),
        ];

        $results = [];
        $compatible = true;

        foreach ($dirs as $label => $path) {
            File::ensureDirectoryExists($path);
            $exists = file_exists($path);
            $writable = is_writable($path);

            // Conduct probe file test
            if ($exists && $writable) {
                $probeFile = $path.DIRECTORY_SEPARATOR.'_perm_probe_'.bin2hex(random_bytes(4)).'.tmp';
                if (@file_put_contents($probeFile, 'probe') !== false) {
                    @unlink($probeFile);
                } else {
                    $writable = false;
                }
            }

            $ok = $exists && $writable;
            if (! $ok) {
                $compatible = false;
            }

            $results[$label] = [
                'path' => $path,
                'exists' => $exists,
                'writable' => $writable,
                'ok' => $ok,
            ];
        }

        return [
            'compatible' => $compatible,
            'directories' => $results,
        ];
    }

    /**
     * Staged configuration of the single Academy organization.
     *
     * @param array{
     *     name: string,
     *     slug?: string|null,
     *     support_email: string,
     *     timezone?: string|null,
     *     locale?: string|null,
     *     currency?: string|null,
     *     date_format?: string|null,
     *     time_format?: string|null
     * } $config
     */
    public function configureAcademyProfile(array $config): Academy
    {
        $academy = Academy::first() ?? new Academy;
        $academy->name = trim($config['name']);
        $academy->slug = ! empty($config['slug']) ? Str::slug($config['slug']) : Str::slug($config['name']);
        $academy->support_email = trim($config['support_email']);
        $academy->timezone = $config['timezone'] ?? 'UTC';
        $academy->locale = $config['locale'] ?? 'en';
        $academy->is_active = true;

        $settings = is_array($academy->settings) ? $academy->settings : [];
        $settings['currency'] = $config['currency'] ?? 'USD';
        $settings['date_format'] = $config['date_format'] ?? 'Y-m-d';
        $settings['time_format'] = $config['time_format'] ?? 'H:i';
        $settings['single_tenant'] = true;
        $academy->settings = $settings;

        $academy->save();

        return $academy;
    }

    /**
     * Create or update the initial primary administrator account.
     *
     * @param array{
     *     name: string,
     *     email: string,
     *     password?: string|null
     * } $config
     */
    public function createFirstAdministrator(array $config): User
    {
        $email = strtolower(trim($config['email']));
        $user = User::where('email', $email)->first();

        if (! $user) {
            $user = new User;
            $user->id = (string) Str::uuid();
            $user->email = $email;
        }

        $user->name = trim($config['name']);
        if (! empty($config['password'])) {
            $user->password = Hash::make($config['password']);
        }
        $user->is_active = true;
        $user->email_verified_at = now();
        $user->save();

        // Assign only 'admin' role
        $user->syncRoles(['admin']);

        // Attach user to Academy in academy_users table
        $academy = Academy::first();
        if ($academy) {
            $existingMembership = DB::table('academy_users')
                ->where('user_id', $user->id)
                ->where('academy_id', $academy->id)
                ->first();

            if (! $existingMembership) {
                DB::table('academy_users')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'academy_id' => $academy->id,
                    'role' => 'admin',
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('academy_users')
                    ->where('id', $existingMembership->id)
                    ->update([
                        'role' => 'admin',
                        'status' => 'active',
                        'updated_at' => now(),
                    ]);
            }
        }

        return $user;
    }

    /**
     * Finalize installation: verify health, clear caches, write installed.lock.
     *
     * @return array{ok: bool, message: string}
     */
    public function finalizeInstallation(): array
    {
        $driver = config('database.default', 'sqlite');
        $pdo = $this->getTargetPdo($driver);

        if (! $pdo instanceof PDO) {
            throw new RuntimeException('Target database connection could not be verified during finalization.');
        }

        $academy = Academy::first();
        if (! $academy) {
            throw new RuntimeException('Academy profile is missing. Installation cannot be finalized.');
        }

        $adminCount = User::role('admin')->count();
        if ($adminCount === 0) {
            throw new RuntimeException('Administrator account is missing. Installation cannot be finalized.');
        }

        // Record installed marker in academy settings
        $settings = is_array($academy->settings) ? $academy->settings : [];
        $settings['installed_at'] = now()->toIso8601String();
        $academy->settings = $settings;
        $academy->save();

        // Record persistent identity as completed
        $this->persistInstallationIdentity($pdo, $driver, 'completed');

        // Write immutable lock file
        $this->writeInstallLock($driver, [
            'finalized_at' => now()->toIso8601String(),
            'version' => '5.6.0',
            'academy_id' => $academy->id,
            'academy_name' => $academy->name,
        ]);

        // Safely clear configuration and route caches
        try {
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
        } catch (Throwable) {
            // Non-fatal cache clearing
        }

        return [
            'ok' => true,
            'message' => 'SEO Academy has been successfully installed and secured.',
        ];
    }

    /**
     * Generate complete system diagnostics.
     *
     * @return array<string, mixed>
     */
    public function getSystemDiagnostics(): array
    {
        $driver = config('database.default', 'sqlite');
        $dbStatus = 'disconnected';
        $dbVersion = 'Unknown';
        $tableCount = 0;

        try {
            $pdo = $this->getTargetPdo($driver);
            if ($pdo instanceof PDO) {
                $dbStatus = 'connected';
                if ($driver === 'sqlite') {
                    $dbVersion = (string) $pdo->query('SELECT sqlite_version()')->fetchColumn();
                    $stmt = $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                    $tableCount = $stmt ? (int) $stmt->fetchColumn() : 0;
                } else {
                    $dbVersion = (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
                    $stmt = $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()');
                    $tableCount = $stmt ? (int) $stmt->fetchColumn() : 0;
                }
            }
        } catch (Throwable) {
            $dbStatus = 'error';
        }

        $migrationsExecuted = 0;
        try {
            $migrationsExecuted = DB::table('migrations')->count();
        } catch (Throwable) {
        }

        $hasAssets = file_exists(public_path('build/manifest.json'));

        return [
            'timestamp' => now()->toIso8601String(),
            'application' => [
                'name' => 'SEO Academy',
                'version' => '5.6.0',
                'framework' => 'Laravel '.app()->version(),
                'installed' => $this->isInstalled(),
            ],
            'php' => [
                'version' => PHP_VERSION,
                'sapi' => PHP_SAPI,
                'os' => PHP_OS_FAMILY,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
            ],
            'extensions' => array_combine(
                self::COMMON_REQUIRED_EXTENSIONS,
                array_map(fn ($ext) => extension_loaded($ext), self::COMMON_REQUIRED_EXTENSIONS)
            ),
            'database' => [
                'driver' => $driver,
                'status' => $dbStatus,
                'version' => $dbVersion,
                'tables' => $tableCount,
                'migrations_executed' => $migrationsExecuted,
                'known_migrations' => count(self::KNOWN_MIGRATIONS),
            ],
            'dependencies' => $this->checkComposerDependencies(),
            'environment' => [
                'has_env' => file_exists(base_path('.env')),
                'app_key_configured' => ! empty(config('app.key')),
                'app_env' => config('app.env'),
                'app_debug' => config('app.debug'),
            ],
            'storage' => $this->verifyFilesystemStorage(),
            'assets' => [
                'compiled_manifest_exists' => $hasAssets,
            ],
        ];
    }

    /**
     * Generate a sanitized diagnostics export without credentials or sensitive paths.
     */
    public function getSanitizedDiagnosticsReport(): string
    {
        $raw = $this->getSystemDiagnostics();

        // Sanitize any potential absolute path references
        $json = json_encode($raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $base = addslashes(base_path());
        $sanitized = str_replace([base_path(), $base], '[PROJECT_ROOT]', (string) $json);

        return $sanitized;
    }
}
