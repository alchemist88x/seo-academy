<?php

namespace Tests\Feature\Operations;

use App\Domain\Academies\Models\Academy;
use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class Migration63VerificationTest extends TestCase
{
    /**
     * Helper to set up a clean disposable SQLite connection.
     */
    private function setupDisposableSqlite(string $dbFilename): Connection
    {
        $dbPath = database_path($dbFilename);
        if (File::exists($dbPath)) {
            File::delete($dbPath);
        }
        touch($dbPath);

        $connectionName = 'disposable_'.md5($dbFilename);
        config(["database.connections.{$connectionName}" => [
            'driver' => 'sqlite',
            'database' => $dbPath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);

        $conn = DB::connection($connectionName);

        // Migrate all migrations
        Artisan::call('migrate', [
            '--database' => $connectionName,
            '--path' => 'database/migrations',
            '--realpath' => false,
            '--force' => true,
        ]);

        // Roll back migrations added after #63 (i.e. #64-#69) so #63 is the latest on the rollback stack
        Artisan::call('migrate:rollback', [
            '--database' => $connectionName,
            '--step' => 6,
            '--force' => true,
        ]);

        return $conn;
    }

    /**
     * Helper to clean up a disposable SQLite connection.
     */
    private function teardownDisposableSqlite(Connection $conn, string $dbFilename): void
    {
        $connectionName = $conn->getName();
        DB::purge($connectionName);
        unset($conn);

        $dbPath = database_path($dbFilename);
        if (File::exists($dbPath)) {
            File::delete($dbPath);
        }
    }

    /**
     * 1. Test SQLite table rebuild, UUID preservation, foreign keys, index, and user deletion.
     */
    public function test_sqlite_migration_rebuild_and_foreign_keys_integrity(): void
    {
        $conn = $this->setupDisposableSqlite('test_m63_rebuild.sqlite');

        // Check SQLite grammar schema transaction support
        $grammar = $conn->getSchemaGrammar();
        $this->assertFalse($grammar->supportsSchemaTransactions(), 'SQLiteGrammar must report supportsSchemaTransactions = false');
        $this->assertSame(0, $conn->transactionLevel(), 'Transaction level must be 0 prior to migration');

        // Schema verification
        $cols = $conn->select('PRAGMA table_info(support_ticket_messages)');
        $userIdCol = collect($cols)->firstWhere('name', 'user_id');
        $this->assertEquals(0, $userIdCol->notnull, 'user_id must be nullable (notnull = 0)');

        $fks = $conn->select('PRAGMA foreign_key_list(support_ticket_messages)');
        $userFk = collect($fks)->firstWhere('from', 'user_id');
        $this->assertSame('SET NULL', strtoupper($userFk->on_delete));

        $ticketFk = collect($fks)->firstWhere('from', 'support_ticket_id');
        $this->assertSame('CASCADE', strtoupper($ticketFk->on_delete));

        $indexes = $conn->select('PRAGMA index_list(support_ticket_messages)');
        $compositeIdx = collect($indexes)->firstWhere('name', 'stm_ticket_internal_created_idx');
        $this->assertNotNull($compositeIdx, 'stm_ticket_internal_created_idx must exist');

        // Insert academy, user, ticket, message
        $academyId = (string) Str::uuid();
        $conn->table('academies')->insert([
            'id' => $academyId,
            'name' => 'Integrity Academy',
            'slug' => 'integrity-academy',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ticketOwnerId = (string) Str::uuid();
        $conn->table('users')->insert([
            'id' => $ticketOwnerId,
            'name' => 'Ticket Owner',
            'email' => 'owner@test.local',
            'password' => 'secret',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $agentId = (string) Str::uuid();
        $conn->table('users')->insert([
            'id' => $agentId,
            'name' => 'Staff Agent',
            'email' => 'agent@test.local',
            'password' => 'secret',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ticketId = (string) Str::uuid();
        $conn->table('support_tickets')->insert([
            'id' => $ticketId,
            'academy_id' => $academyId,
            'user_id' => $ticketOwnerId,
            'subject' => 'Integrity Subject',
            'description' => 'Integrity Description',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $msgId = (string) Str::uuid();
        $conn->table('support_ticket_messages')->insert([
            'id' => $msgId,
            'support_ticket_id' => $ticketId,
            'user_id' => $agentId,
            'author_name' => 'Staff Agent',
            'message' => 'Agent response message',
            'is_internal' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Delete agent user: message must remain with user_id = null and author_name intact
        $conn->table('users')->where('id', $agentId)->delete();

        $savedMsg = $conn->table('support_ticket_messages')->where('id', $msgId)->first();
        $this->assertNotNull($savedMsg);
        $this->assertNull($savedMsg->user_id);
        $this->assertSame('Staff Agent', $savedMsg->author_name);
        $this->assertSame('Agent response message', $savedMsg->message);

        // PRAGMA foreign_key_check must be 0 violations
        $fkCheck = $conn->select('PRAGMA foreign_key_check');
        $this->assertEmpty($fkCheck, 'PRAGMA foreign_key_check must report 0 violations');

        $this->teardownDisposableSqlite($conn, 'test_m63_rebuild.sqlite');
    }

    /**
     * 2. Test rollback refuses to reassign NULL authors and leaves records/schema unchanged (SQLite).
     */
    public function test_rollback_refuses_to_reassign_null_authors_and_leaves_db_unchanged_sqlite(): void
    {
        $conn = $this->setupDisposableSqlite('test_m63_null_author.sqlite');
        $connectionName = $conn->getName();

        // Seed data
        $academyId = (string) Str::uuid();
        $conn->table('academies')->insert([
            'id' => $academyId,
            'name' => 'Null Author Academy',
            'slug' => 'null-author-academy',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = (string) Str::uuid();
        $conn->table('users')->insert([
            'id' => $userId,
            'name' => 'Student User',
            'email' => 'student@test.local',
            'password' => 'secret',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ticketId = (string) Str::uuid();
        $conn->table('support_tickets')->insert([
            'id' => $ticketId,
            'academy_id' => $academyId,
            'user_id' => $userId,
            'subject' => 'Student Ticket',
            'description' => 'Description',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $msgId = (string) Str::uuid();
        $conn->table('support_ticket_messages')->insert([
            'id' => $msgId,
            'support_ticket_id' => $ticketId,
            'user_id' => null, // Author was previously deleted
            'author_name' => 'Former Support Specialist',
            'message' => 'Valuable historical advice',
            'is_internal' => 0,
            'created_at' => '2026-11-10 12:00:00',
            'updated_at' => '2026-11-10 12:00:00',
        ]);

        // Attempt rollback: MUST throw RuntimeException
        $exceptionThrown = false;
        try {
            Artisan::call('migrate:rollback', [
                '--database' => $connectionName,
                '--step' => 1,
                '--force' => true,
            ]);
        } catch (\RuntimeException $e) {
            $exceptionThrown = true;
            $this->assertStringContainsString('NULL user_id', $e->getMessage());
            $this->assertStringContainsString('fabricating authorship', $e->getMessage());
        }

        $this->assertTrue($exceptionThrown, 'Rollback must throw RuntimeException when messages have NULL user_id');

        // VERIFY RECORDS ARE UNCHANGED
        $msgAfter = $conn->table('support_ticket_messages')->where('id', $msgId)->first();
        $this->assertNotNull($msgAfter, 'Message must NOT be deleted');
        $this->assertNull($msgAfter->user_id, 'user_id must remain NULL (never fabricated)');
        $this->assertSame('Former Support Specialist', $msgAfter->author_name, 'author_name must remain unchanged');
        $this->assertSame('Valuable historical advice', $msgAfter->message, 'message content must remain unchanged');
        $this->assertSame('2026-11-10 12:00:00', $msgAfter->created_at, 'timestamps must remain unchanged');

        // VERIFY SCHEMA IS UNCHANGED
        $cols = $conn->select('PRAGMA table_info(support_ticket_messages)');
        $userIdCol = collect($cols)->firstWhere('name', 'user_id');
        $this->assertEquals(0, $userIdCol->notnull, 'user_id column must remain nullable (0)');

        $fks = $conn->select('PRAGMA foreign_key_list(support_ticket_messages)');
        $userFk = collect($fks)->firstWhere('from', 'user_id');
        $this->assertSame('SET NULL', strtoupper($userFk->on_delete));

        // Migration 63 must still be recorded as ran in migrations table
        $migrationRan = $conn->table('migrations')
            ->where('migration', 'like', '%make_user_id_nullable_on_support_ticket_messages%')
            ->exists();
        $this->assertTrue($migrationRan, 'Migration 63 must remain recorded as ran in migrations repository');

        // PRAGMA foreign_key_check must be clean
        $this->assertEmpty($conn->select('PRAGMA foreign_key_check'));

        $this->teardownDisposableSqlite($conn, 'test_m63_null_author.sqlite');
    }

    /**
     * 3. Test rollback refuses to expose soft-deleted tickets and leaves records/schema unchanged (SQLite).
     */
    public function test_rollback_refuses_to_expose_soft_deleted_tickets_and_leaves_db_unchanged_sqlite(): void
    {
        $conn = $this->setupDisposableSqlite('test_m63_soft_ticket.sqlite');
        $connectionName = $conn->getName();

        // Seed data with a soft-deleted ticket
        $academyId = (string) Str::uuid();
        $conn->table('academies')->insert([
            'id' => $academyId,
            'name' => 'Soft Delete Safety Academy',
            'slug' => 'soft-delete-safety-academy',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = (string) Str::uuid();
        $conn->table('users')->insert([
            'id' => $userId,
            'name' => 'Student User',
            'email' => 'student@test.local',
            'password' => 'secret',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ticketId = (string) Str::uuid();
        $conn->table('support_tickets')->insert([
            'id' => $ticketId,
            'academy_id' => $academyId,
            'user_id' => $userId,
            'subject' => 'Sensitive Cancelled Ticket',
            'description' => 'Should never be exposed',
            'status' => 'closed',
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => '2026-11-12 15:30:00', // Soft-deleted
        ]);

        $msgId = (string) Str::uuid();
        $conn->table('support_ticket_messages')->insert([
            'id' => $msgId,
            'support_ticket_id' => $ticketId,
            'user_id' => $userId,
            'author_name' => 'Student User',
            'message' => 'Original inquiry',
            'is_internal' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Attempt rollback: MUST throw RuntimeException
        $exceptionThrown = false;
        try {
            Artisan::call('migrate:rollback', [
                '--database' => $connectionName,
                '--step' => 1,
                '--force' => true,
            ]);
        } catch (\RuntimeException $e) {
            $exceptionThrown = true;
            $this->assertStringContainsString('soft-deleted support ticket(s)', $e->getMessage());
            $this->assertStringContainsString('expose soft-deleted tickets', $e->getMessage());
        }

        $this->assertTrue($exceptionThrown, 'Rollback must throw RuntimeException when soft-deleted tickets exist');

        // VERIFY RECORDS ARE UNCHANGED
        $ticketAfter = $conn->table('support_tickets')->where('id', $ticketId)->first();
        $this->assertNotNull($ticketAfter);
        $this->assertSame('2026-11-12 15:30:00', $ticketAfter->deleted_at, 'deleted_at timestamp must remain intact');

        // VERIFY SCHEMA IS UNCHANGED
        $ticketCols = $conn->select('PRAGMA table_info(support_tickets)');
        $hasDeletedAt = collect($ticketCols)->contains('name', 'deleted_at');
        $this->assertTrue($hasDeletedAt, 'deleted_at column must remain in support_tickets table');

        $msgCols = $conn->select('PRAGMA table_info(support_ticket_messages)');
        $userIdCol = collect($msgCols)->firstWhere('name', 'user_id');
        $this->assertEquals(0, $userIdCol->notnull, 'user_id column must remain nullable (0)');

        // Migration 63 must still be recorded as ran
        $migrationRan = $conn->table('migrations')
            ->where('migration', 'like', '%make_user_id_nullable_on_support_ticket_messages%')
            ->exists();
        $this->assertTrue($migrationRan);

        $this->teardownDisposableSqlite($conn, 'test_m63_soft_ticket.sqlite');
    }

    /**
     * 4. Test successful rollback works when no preservation conflicts exist (SQLite).
     */
    public function test_successful_rollback_works_when_no_conflicts_exist_sqlite(): void
    {
        $conn = $this->setupDisposableSqlite('test_m63_clean_rollback.sqlite');
        $connectionName = $conn->getName();

        // Seed data with NO NULL authors and NO soft-deleted tickets
        $academyId = (string) Str::uuid();
        $conn->table('academies')->insert([
            'id' => $academyId,
            'name' => 'Clean Academy',
            'slug' => 'clean-academy',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = (string) Str::uuid();
        $conn->table('users')->insert([
            'id' => $userId,
            'name' => 'Valid User',
            'email' => 'valid@test.local',
            'password' => 'secret',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ticketId = (string) Str::uuid();
        $conn->table('support_tickets')->insert([
            'id' => $ticketId,
            'academy_id' => $academyId,
            'user_id' => $userId,
            'subject' => 'Clean Ticket',
            'description' => 'Clean Description',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null, // NOT soft-deleted
        ]);

        $msgId = (string) Str::uuid();
        $conn->table('support_ticket_messages')->insert([
            'id' => $msgId,
            'support_ticket_id' => $ticketId,
            'user_id' => $userId, // NOT NULL author
            'author_name' => 'Valid User',
            'message' => 'Clean message',
            'is_internal' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Rollback should succeed
        $exitCode = Artisan::call('migrate:rollback', [
            '--database' => $connectionName,
            '--step' => 1,
            '--force' => true,
        ]);
        $this->assertSame(0, $exitCode, 'Rollback should succeed with exit code 0 when no conflicts exist');

        // Schema verification: user_id is NOT NULL (1) again
        $cols = $conn->select('PRAGMA table_info(support_ticket_messages)');
        $userIdCol = collect($cols)->firstWhere('name', 'user_id');
        $this->assertEquals(1, $userIdCol->notnull, 'On clean rollback, user_id must be NOT NULL (1)');

        // Foreign key restored to CASCADE
        $fks = $conn->select('PRAGMA foreign_key_list(support_ticket_messages)');
        $userFk = collect($fks)->firstWhere('from', 'user_id');
        $this->assertSame('CASCADE', strtoupper($userFk->on_delete));

        // Message preserved verbatim without author fabrication
        $msgAfter = $conn->table('support_ticket_messages')->where('id', $msgId)->first();
        $this->assertNotNull($msgAfter);
        $this->assertSame($userId, $msgAfter->user_id);
        $this->assertSame('Valid User', $msgAfter->author_name);
        $this->assertSame('Clean message', $msgAfter->message);

        // deleted_at dropped
        $ticketCols = $conn->select('PRAGMA table_info(support_tickets)');
        $this->assertFalse(collect($ticketCols)->contains('name', 'deleted_at'));

        // PRAGMA foreign_key_check must be 0 violations
        $this->assertEmpty($conn->select('PRAGMA foreign_key_check'));

        // Re-migrating UP works cleanly
        $reUpExit = Artisan::call('migrate', [
            '--database' => $connectionName,
            '--path' => 'database/migrations/2026_11_13_140000_make_user_id_nullable_on_support_ticket_messages.php',
            '--force' => true,
        ]);
        $this->assertSame(0, $reUpExit);

        $colsReUp = $conn->select('PRAGMA table_info(support_ticket_messages)');
        $userIdColReUp = collect($colsReUp)->firstWhere('name', 'user_id');
        $this->assertEquals(0, $userIdColReUp->notnull);

        $this->assertEmpty($conn->select('PRAGMA foreign_key_check'));

        $this->teardownDisposableSqlite($conn, 'test_m63_clean_rollback.sqlite');
    }

    /**
     * 5. Test MySQL InnoDB rollback safety guards and clean rollback.
     */
    public function test_mysql_innodb_rollback_safety_guards(): void
    {
        config([
            'database.connections.test_mysql' => [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'port' => 3306,
                'database' => 'seo_academy_test',
                'username' => 'root',
                'password' => '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
                'engine' => 'InnoDB',
            ],
        ]);

        try {
            DB::connection('test_mysql')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL connection not available: '.$e->getMessage());
        }

        $conn = DB::connection('test_mysql');

        // Migrate fresh on MySQL
        Artisan::call('migrate:fresh', [
            '--database' => 'test_mysql',
            '--force' => true,
        ]);

        // Roll back migrations added after #63 so #63 is at the head of the rollback stack
        Artisan::call('migrate:rollback', [
            '--database' => 'test_mysql',
            '--step' => 6,
            '--force' => true,
        ]);

        // A. Test refusal when NULL author exists
        $academyId = (string) Str::uuid();
        $conn->table('academies')->insert([
            'id' => $academyId,
            'name' => 'MySQL Academy',
            'slug' => 'mysql-academy',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = (string) Str::uuid();
        $conn->table('users')->insert([
            'id' => $userId,
            'name' => 'MySQL User',
            'email' => 'mysqluser@test.local',
            'password' => 'secret',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ticketId = (string) Str::uuid();
        $conn->table('support_tickets')->insert([
            'id' => $ticketId,
            'academy_id' => $academyId,
            'user_id' => $userId,
            'subject' => 'MySQL Ticket',
            'description' => 'Testing MySQL safety',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $msgId = (string) Str::uuid();
        $conn->table('support_ticket_messages')->insert([
            'id' => $msgId,
            'support_ticket_id' => $ticketId,
            'user_id' => null, // Deleted author
            'author_name' => 'Deleted Agent',
            'message' => 'Message with deleted author',
            'is_internal' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $nullAuthorException = false;
        try {
            Artisan::call('migrate:rollback', [
                '--database' => 'test_mysql',
                '--step' => 1,
                '--force' => true,
            ]);
        } catch (\RuntimeException $e) {
            $nullAuthorException = true;
            $this->assertStringContainsString('NULL user_id', $e->getMessage());
        }
        $this->assertTrue($nullAuthorException, 'MySQL rollback must abort when NULL user_id exists');

        // Verify MySQL schema and records remain unchanged
        $colInfo = $conn->select("
            SELECT IS_NULLABLE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = 'seo_academy_test'
              AND TABLE_NAME = 'support_ticket_messages'
              AND COLUMN_NAME = 'user_id'
        ");
        $this->assertSame('YES', $colInfo[0]->IS_NULLABLE, 'MySQL user_id must remain nullable');

        $msgAfter = $conn->table('support_ticket_messages')->where('id', $msgId)->first();
        $this->assertNotNull($msgAfter);
        $this->assertNull($msgAfter->user_id);
        $this->assertSame('Deleted Agent', $msgAfter->author_name);

        // B. Fix the message by giving it a valid user_id, now test soft-deleted ticket guard
        $conn->table('support_ticket_messages')->where('id', $msgId)->update(['user_id' => $userId]);
        $conn->table('support_tickets')->where('id', $ticketId)->update(['deleted_at' => now()]);

        $softTicketException = false;
        try {
            Artisan::call('migrate:rollback', [
                '--database' => 'test_mysql',
                '--step' => 1,
                '--force' => true,
            ]);
        } catch (\RuntimeException $e) {
            $softTicketException = true;
            $this->assertStringContainsString('soft-deleted support ticket(s)', $e->getMessage());
        }
        $this->assertTrue($softTicketException, 'MySQL rollback must abort when soft-deleted tickets exist');

        // C. Restore ticket to active (deleted_at = null) and perform clean rollback
        $conn->table('support_tickets')->where('id', $ticketId)->update(['deleted_at' => null]);

        $cleanRollbackExit = Artisan::call('migrate:rollback', [
            '--database' => 'test_mysql',
            '--step' => 1,
            '--force' => true,
        ]);
        $this->assertSame(0, $cleanRollbackExit);

        // Verify MySQL schema after rollback
        $colInfoRollback = $conn->select("
            SELECT IS_NULLABLE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = 'seo_academy_test'
              AND TABLE_NAME = 'support_ticket_messages'
              AND COLUMN_NAME = 'user_id'
        ");
        $this->assertSame('NO', $colInfoRollback[0]->IS_NULLABLE, 'MySQL user_id must be NOT NULL after clean rollback');

        // Re-migrating UP on MySQL
        $reUpExit = Artisan::call('migrate', [
            '--database' => 'test_mysql',
            '--path' => 'database/migrations/2026_11_13_140000_make_user_id_nullable_on_support_ticket_messages.php',
            '--force' => true,
        ]);
        $this->assertSame(0, $reUpExit);
    }
}
