<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Tests\TestCase;

class DatabaseConnectivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_sqlite_connection_works(): void
    {
        $connection = DB::connection();

        $this->assertSame('sqlite', $connection->getDriverName());
        $this->assertInstanceOf(PDO::class, $connection->getPdo());
    }

    public function test_can_run_simple_query(): void
    {
        $result = DB::select('SELECT 1 as ok');

        $this->assertCount(1, $result);
        $this->assertEquals(1, $result[0]->ok);
    }

    public function test_all_expected_tables_exist_after_migration(): void
    {
        $expectedTables = [
            'users',
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
            'ai_messages',
            'ai_faqs',
            'ai_materials',
            'chat_metrics',
            'audit_log',
            'login_attempts',
            'media',
            'roles',
            'permissions',
        ];

        foreach ($expectedTables as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "Expected table '{$table}' does not exist after migration."
            );
        }
    }

    public function test_mysql_connection(): void
    {
        config([
            'database.connections.mysql.host' => '127.0.0.1',
            'database.connections.mysql.port' => 3306,
            'database.connections.mysql.database' => 'seo_academy_test',
            'database.connections.mysql.username' => 'root',
            'database.connections.mysql.password' => '',
        ]);

        DB::purge('mysql');
        $connection = DB::connection('mysql');

        $this->assertSame('mysql', $connection->getDriverName());
        $this->assertInstanceOf(PDO::class, $connection->getPdo());

        $result = $connection->select('SELECT 1 as ok');
        $this->assertCount(1, $result);
        $this->assertEquals(1, $result[0]->ok);

        $engines = $connection->select('SHOW ENGINES');
        $innoDb = collect($engines)->first(
            static fn (object $engine): bool => strtoupper((string) ($engine->Engine ?? '')) === 'INNODB'
        );

        $this->assertNotNull($innoDb, 'InnoDB engine must be present on MySQL server.');
        $this->assertContains(
            strtoupper((string) ($innoDb->Support ?? '')),
            ['YES', 'DEFAULT'],
            'InnoDB engine must be enabled on MySQL server.'
        );
    }
}
