<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationTest extends TestCase
{
    public function test_all_migrations_run_cleanly_on_fresh_sqlite_db(): void
    {
        $exitCode = Artisan::call('migrate:fresh', ['--force' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('academies'));
        $this->assertTrue(Schema::hasTable('courses'));
        $this->assertTrue(Schema::hasTable('roles'));
    }

    public function test_all_migrations_can_be_rolled_back_cleanly(): void
    {
        $this->assertSame(0, Artisan::call('migrate:fresh', ['--force' => true]));

        $rollbackExitCode = Artisan::call('migrate:rollback', ['--force' => true]);

        $this->assertSame(0, $rollbackExitCode);
        $this->assertFalse(Schema::hasTable('courses'));
    }

    public function test_rerunning_migrations_is_idempotent(): void
    {
        $this->assertSame(0, Artisan::call('migrate:fresh', ['--force' => true]));
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));

        $this->assertTrue(Schema::hasTable('courses'));
    }
}
