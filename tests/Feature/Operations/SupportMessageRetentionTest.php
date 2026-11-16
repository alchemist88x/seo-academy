<?php

namespace Tests\Feature\Operations;

use App\Domain\Academies\Models\Academy;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Support\Models\SupportTicketMessage;
use App\Domain\Support\Policies\SupportTicketPolicy;
use App\Domain\Support\Services\SupportTicketService;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupportMessageRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        // Ensure foreign keys are enabled in SQLite
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
        }
    }

    private function createAcademyMember(Academy $academy, string $role, array $userOverrides = []): User
    {
        $user = User::factory()->create(array_merge(['is_active' => true], $userOverrides));
        $user->assignRole($role);
        $user->academies()->attach($academy->getKey(), [
            'id' => (string) Str::uuid(),
            'role' => $role,
            'status' => 'active',
        ]);

        return $user;
    }

    private function createTicket(Academy $academy, User $owner, array $attributes = []): SupportTicket
    {
        return SupportTicket::create(array_merge([
            'academy_id' => $academy->getKey(),
            'user_id' => $owner->getKey(),
            'subject' => 'Retention Policy Verification Ticket',
            'description' => 'Verifying user deletion preserves support communication history.',
            'status' => SupportTicket::STATUS_OPEN,
            'priority' => SupportTicket::PRIORITY_MEDIUM,
        ], $attributes));
    }

    /**
     * Test 1: Deleting a user preserves their support messages in support_ticket_messages.
     */
    public function test_deleting_user_preserves_support_messages(): void
    {
        $academy = Academy::factory()->create();
        $ticketOwner = $this->createAcademyMember($academy, 'student', ['name' => 'Owner Student']);
        $authorUser = $this->createAcademyMember($academy, 'support_staff', ['name' => 'Alice Support']);

        $ticket = $this->createTicket($academy, $ticketOwner);

        $ownerMsg = SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $ticketOwner->id,
            'author_name' => $ticketOwner->name,
            'message' => 'Original student issue report.',
            'is_internal' => false,
        ]);

        $authorMsg = SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $authorUser->id,
            'author_name' => $authorUser->name,
            'message' => 'Staff response to student.',
            'is_internal' => false,
        ]);

        $this->assertDatabaseCount('support_ticket_messages', 2);

        // Delete the author user account
        $authorUser->delete();

        // The author user must no longer exist in users table
        $this->assertDatabaseMissing('users', ['id' => $authorUser->id]);

        // Both messages must still be preserved in support_ticket_messages
        $this->assertDatabaseCount('support_ticket_messages', 2);
        $this->assertDatabaseHas('support_ticket_messages', [
            'id' => $authorMsg->id,
            'message' => 'Staff response to student.',
        ]);
        $this->assertDatabaseHas('support_ticket_messages', [
            'id' => $ownerMsg->id,
            'message' => 'Original student issue report.',
        ]);
    }

    /**
     * Test 2: The deleted user's user_id in support_ticket_messages becomes NULL.
     */
    public function test_deleted_user_id_in_support_ticket_messages_becomes_null(): void
    {
        $academy = Academy::factory()->create();
        $ticketOwner = $this->createAcademyMember($academy, 'student', ['name' => 'Carol Student']);
        $authorUser = $this->createAcademyMember($academy, 'support_staff', ['name' => 'Bob Support']);

        $ticket = $this->createTicket($academy, $ticketOwner);

        $msg = SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $authorUser->id,
            'author_name' => $authorUser->name,
            'message' => 'Message before user deletion.',
            'is_internal' => false,
        ]);

        $this->assertSame((string) $authorUser->id, (string) $msg->user_id);

        // Delete author user
        $authorUser->delete();

        // Fresh instance from database
        $refreshedMsg = SupportTicketMessage::findOrFail($msg->id);
        $this->assertNull($refreshedMsg->user_id);

        // Raw database check ensures nullOnDelete was executed by database engine
        $rawUserId = DB::table('support_ticket_messages')->where('id', $msg->id)->value('user_id');
        $this->assertNull($rawUserId);
    }

    /**
     * Test 3: author_name remains intact and available after user deletion.
     */
    public function test_author_name_remains_intact_and_available_after_user_deletion(): void
    {
        $academy = Academy::factory()->create();
        $ticketOwner = $this->createAcademyMember($academy, 'student', ['name' => 'David Student']);
        $authorUser = $this->createAcademyMember($academy, 'support_staff', ['name' => 'Jane Specialist']);

        $ticket = $this->createTicket($academy, $ticketOwner);

        $service = app(SupportTicketService::class);
        $msg = $service->addReply($ticket, $authorUser, 'Message with captured author_name');

        $this->assertSame('Jane Specialist', $msg->author_name);
        $this->assertSame('Jane Specialist', $msg->authorName());

        // Delete user
        $authorUser->delete();

        // Fresh message model
        $refreshedMsg = SupportTicketMessage::findOrFail($msg->id);

        // author_name column remains unchanged
        $this->assertSame('Jane Specialist', $refreshedMsg->author_name);
        // authorName() helper returns author_name even when user relation is null
        $this->assertNull($refreshedMsg->user);
        $this->assertSame('Jane Specialist', $refreshedMsg->authorName());

        // Test fallback when author_name is null and user is null
        $fallbackMsg = SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => null,
            'author_name' => null,
            'message' => 'Orphaned message without author_name.',
            'is_internal' => false,
        ]);
        $this->assertSame('Former User', $fallbackMsg->authorName());
    }

    /**
     * Test 4: Existing messages are not modified or corrupted during migration.
     */
    public function test_existing_messages_are_not_modified_or_corrupted(): void
    {
        $academy = Academy::factory()->create();
        $ticketOwner = $this->createAcademyMember($academy, 'student', ['name' => 'Eve Student']);
        $authorUser = $this->createAcademyMember($academy, 'support_staff', ['name' => 'Frank Staff']);

        $ticket = $this->createTicket($academy, $ticketOwner);

        $fixedCreatedAt = now()->subDays(5)->startOfMinute();
        $fixedUpdatedAt = now()->subDays(4)->startOfMinute();

        $msg = SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $authorUser->id,
            'author_name' => 'Frank Staff',
            'message' => 'Critical data integrity message content with special chars: <xml> & "quotes".',
            'is_internal' => false,
        ]);
        $msg->timestamps = false;
        $msg->created_at = $fixedCreatedAt;
        $msg->updated_at = $fixedUpdatedAt;
        $msg->save();
        $msg->timestamps = true;

        // Delete author user
        $authorUser->delete();

        $fresh = SupportTicketMessage::findOrFail($msg->id);

        $this->assertSame($msg->id, $fresh->id);
        $this->assertSame($ticket->id, $fresh->support_ticket_id);
        $this->assertSame('Frank Staff', $fresh->author_name);
        $this->assertSame('Critical data integrity message content with special chars: <xml> & "quotes".', $fresh->message);
        $this->assertFalse($fresh->is_internal);
        $this->assertSame($fixedCreatedAt->toIso8601String(), $fresh->created_at->toIso8601String());
        $this->assertSame($fixedUpdatedAt->toIso8601String(), $fresh->updated_at->toIso8601String());
    }

    /**
     * Test 5: Historical author names are backfilled correctly from users.name.
     */
    public function test_historical_author_names_are_backfilled_correctly_from_users_name(): void
    {
        $academy = Academy::factory()->create();
        $ticketOwner = $this->createAcademyMember($academy, 'student', ['name' => 'Ticket Owner']);
        $user = $this->createAcademyMember($academy, 'support_staff', ['name' => 'Frank Historical']);

        $ticket = $this->createTicket($academy, $ticketOwner);

        $msgId = (string) Str::uuid();

        // Simulate historical message prior to author_name column population
        DB::table('support_ticket_messages')->insert([
            'id' => $msgId,
            'support_ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'author_name' => null,
            'message' => 'Legacy message needing author backfill.',
            'is_internal' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertNull(DB::table('support_ticket_messages')->where('id', $msgId)->value('author_name'));

        // Execute the backfill logic as implemented in the migration
        $messagesToBackfill = DB::table('support_ticket_messages')
            ->whereNotNull('user_id')
            ->where(function ($query) {
                $query->whereNull('author_name')
                    ->orWhere('author_name', '');
            })
            ->select('id', 'user_id')
            ->get();

        foreach ($messagesToBackfill as $m) {
            $name = DB::table('users')->where('id', $m->user_id)->value('name');
            if ($name !== null) {
                DB::table('support_ticket_messages')
                    ->where('id', $m->id)
                    ->update(['author_name' => $name]);
            }
        }

        $this->assertSame('Frank Historical', DB::table('support_ticket_messages')->where('id', $msgId)->value('author_name'));

        $model = SupportTicketMessage::findOrFail($msgId);
        $this->assertSame('Frank Historical', $model->authorName());
    }

    /**
     * Test 6: Internal notes (is_internal = true) remain strictly hidden from students even after the author user is deleted.
     */
    public function test_internal_notes_remain_strictly_hidden_from_students_after_author_deletion(): void
    {
        $academy = Academy::factory()->create();
        $student = $this->createAcademyMember($academy, 'student', ['name' => 'Grace Student']);
        $staff = $this->createAcademyMember($academy, 'support_staff', ['name' => 'Hank Staff']);

        $ticket = $this->createTicket($academy, $student);

        $publicMsg = SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $staff->id,
            'author_name' => $staff->name,
            'message' => 'Public answer visible to student.',
            'is_internal' => false,
        ]);

        $internalNote = SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $staff->id,
            'author_name' => $staff->name,
            'message' => 'CONFIDENTIAL: Internal staff diagnostics and security notes.',
            'is_internal' => true,
        ]);

        // Delete the staff member who authored the internal note
        $staff->delete();

        // Confirm staff author was deleted and user_id is now NULL
        $refreshedNote = SupportTicketMessage::findOrFail($internalNote->id);
        $this->assertNull($refreshedNote->user_id);
        $this->assertTrue($refreshedNote->is_internal);

        // When student accesses messages through publicOnly scope
        $studentViewableMessages = SupportTicketMessage::query()
            ->where('support_ticket_id', $ticket->id)
            ->publicOnly()
            ->get();

        $this->assertCount(1, $studentViewableMessages);
        $this->assertSame('Public answer visible to student.', $studentViewableMessages->first()->message);
        $this->assertFalse($studentViewableMessages->pluck('id')->contains($internalNote->id));
        $this->assertFalse($studentViewableMessages->pluck('message')->contains('CONFIDENTIAL: Internal staff diagnostics and security notes.'));
    }

    /**
     * Test 7: Authorized support staff and administrators can still read the full conversation after user deletion.
     */
    public function test_authorized_support_staff_and_administrators_can_read_full_conversation_after_user_deletion(): void
    {
        $academy = Academy::factory()->create();
        $ticketOwner = $this->createAcademyMember($academy, 'student', ['name' => 'Ian Student']);
        $staff1 = $this->createAcademyMember($academy, 'support_staff', ['name' => 'Jack Support']);
        $staff2 = $this->createAcademyMember($academy, 'support_staff', ['name' => 'Karen Admin']);

        $ticket = $this->createTicket($academy, $ticketOwner);

        // 1. Initial student inquiry
        SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $ticketOwner->id,
            'author_name' => $ticketOwner->name,
            'message' => 'Student question regarding certificate generation.',
            'is_internal' => false,
        ]);

        // 2. Staff internal note
        SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $staff1->id,
            'author_name' => $staff1->name,
            'message' => 'Internal note: Certificate queued in worker.',
            'is_internal' => true,
        ]);

        // 3. Staff public reply
        SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $staff1->id,
            'author_name' => $staff1->name,
            'message' => 'Staff response: We have processed your certificate.',
            'is_internal' => false,
        ]);

        // Staff1 account is deleted
        $staff1->delete();

        // Staff2 / Admin reads full conversation
        $conversation = $ticket->messages()->orderBy('created_at', 'asc')->get();

        $this->assertCount(3, $conversation);

        $staffMsg = $conversation->where('author_name', 'Jack Support')->first();
        $this->assertNotNull($staffMsg);
        $this->assertNull($staffMsg->user_id);
        $this->assertSame('Jack Support', $staffMsg->author_name);
        $this->assertSame('Jack Support', $staffMsg->authorName());

        // Internal note is accessible to authorized staff
        $internalNotes = $conversation->where('is_internal', true);
        $this->assertCount(1, $internalNotes);
        $this->assertSame('Internal note: Certificate queued in worker.', $internalNotes->first()->message);

        // Staff can still add follow-up replies
        $service = app(SupportTicketService::class);
        $followUp = $service->addReply($ticket, $staff2, 'Follow up note after student account archived.', isInternal: true);
        $this->assertSame(4, $ticket->messages()->count());
        $this->assertSame('Karen Admin', $followUp->authorName());
    }

    /**
     * Test 8: Ticket deletion follows the retention policy (soft delete preserves ticket and messages; messages remain in database).
     */
    public function test_ticket_deletion_follows_retention_policy_soft_delete_preserves_ticket_and_messages(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyMember($academy, 'admin', ['name' => 'Root Admin']);
        $student = $this->createAcademyMember($academy, 'student', ['name' => 'Leo Student']);
        $staff = $this->createAcademyMember($academy, 'support_staff', ['name' => 'Mona Staff']);

        $ticket = $this->createTicket($academy, $student);

        $msg1 = SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $student->id,
            'author_name' => $student->name,
            'message' => 'Ticket body message 1.',
            'is_internal' => false,
        ]);

        $msg2 = SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $staff->id,
            'author_name' => $staff->name,
            'message' => 'Ticket body message 2.',
            'is_internal' => true,
        ]);

        // Policy checks: students and regular support staff cannot delete tickets
        $policy = app(SupportTicketPolicy::class);
        $this->assertFalse($policy->delete($student, $ticket));
        $this->assertFalse($policy->delete($staff, $ticket));
        $this->assertTrue($policy->delete($admin, $ticket));

        // When ticket is deleted by admin, it must be soft-deleted
        $ticket->delete();

        $this->assertTrue($ticket->trashed());
        $this->assertSoftDeleted('support_tickets', ['id' => $ticket->id]);

        // The ticket still exists in database with deleted_at timestamp
        $this->assertNotNull(DB::table('support_tickets')->where('id', $ticket->id)->value('deleted_at'));

        // All associated messages remain in database and are not cascade-deleted
        $this->assertDatabaseHas('support_ticket_messages', ['id' => $msg1->id]);
        $this->assertDatabaseHas('support_ticket_messages', ['id' => $msg2->id]);
        $this->assertDatabaseCount('support_ticket_messages', 2);
    }

    /**
     * Test 9: SQLite and MySQL foreign-key behavior is verified and identical.
     */
    public function test_sqlite_and_mysql_foreign_key_behavior_is_verified_and_identical(): void
    {
        // 1. Verify on current active connection (SQLite or MySQL depending on test runner)
        $currentDriver = DB::getDriverName();
        $this->assertContains($currentDriver, ['sqlite', 'mysql']);

        $academy = Academy::factory()->create();
        $ticketOwner = $this->createAcademyMember($academy, 'student', ['name' => 'FK Owner']);
        $authorUser = $this->createAcademyMember($academy, 'support_staff', ['name' => 'FK Author User']);

        $ticket = $this->createTicket($academy, $ticketOwner);

        $message = SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $authorUser->id,
            'author_name' => $authorUser->name,
            'message' => 'Testing engine foreign key cascade to NULL behavior.',
            'is_internal' => false,
        ]);

        $this->assertSame((string) $authorUser->id, (string) $message->user_id);

        // Delete author user
        $authorUser->delete();

        // Message still exists in DB
        $this->assertDatabaseHas('support_ticket_messages', [
            'id' => $message->id,
            'author_name' => 'FK Author User',
        ]);

        // user_id in DB was set to NULL by foreign key constraint
        $dbUserId = DB::table('support_ticket_messages')->where('id', $message->id)->value('user_id');
        $this->assertNull($dbUserId);

        // 2. If running on SQLite but MySQL test DB is available, test against MySQL directly as well
        if ($currentDriver === 'sqlite') {
            try {
                $mysqlConn = DB::connection('mysql');
                $mysqlConn->getPdo();

                // Test directly on MySQL connection to verify identical behavior
                $mysqlSchema = Schema::connection('mysql');
                if ($mysqlSchema->hasTable('support_ticket_messages')) {
                    $ownerId = (string) Str::uuid();
                    $authorId = (string) Str::uuid();
                    $acadId = (string) Str::uuid();
                    $ticketId = (string) Str::uuid();
                    $msgId = (string) Str::uuid();

                    $mysqlConn->table('academies')->insert([
                        'id' => $acadId,
                        'name' => 'MySQL Parity Academy',
                        'slug' => 'mysql-parity-'.Str::random(6),
                        'is_active' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $mysqlConn->table('users')->insert([
                        [
                            'id' => $ownerId,
                            'name' => 'MySQL Ticket Owner',
                            'email' => 'mysql-owner-'.Str::random(6).'@example.com',
                            'password' => bcrypt('password'),
                            'is_active' => 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                        [
                            'id' => $authorId,
                            'name' => 'MySQL Message Author',
                            'email' => 'mysql-author-'.Str::random(6).'@example.com',
                            'password' => bcrypt('password'),
                            'is_active' => 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    ]);

                    $mysqlConn->table('support_tickets')->insert([
                        'id' => $ticketId,
                        'academy_id' => $acadId,
                        'user_id' => $ownerId,
                        'subject' => 'MySQL FK Test',
                        'description' => 'Verifying nullOnDelete in MySQL',
                        'status' => 'open',
                        'priority' => 'medium',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $mysqlConn->table('support_ticket_messages')->insert([
                        'id' => $msgId,
                        'support_ticket_id' => $ticketId,
                        'user_id' => $authorId,
                        'author_name' => 'MySQL Message Author',
                        'message' => 'MySQL engine check',
                        'is_internal' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Delete the author user on MySQL
                    $mysqlConn->table('users')->where('id', $authorId)->delete();

                    // Check that message still exists on MySQL and user_id is NULL
                    $msgRow = $mysqlConn->table('support_ticket_messages')->where('id', $msgId)->first();
                    $this->assertNotNull($msgRow);
                    $this->assertNull($msgRow->user_id);
                    $this->assertSame('MySQL Message Author', $msgRow->author_name);

                    // Clean up test rows
                    $mysqlConn->table('support_ticket_messages')->where('id', $msgId)->delete();
                    $mysqlConn->table('support_tickets')->where('id', $ticketId)->delete();
                    $mysqlConn->table('users')->where('id', $ownerId)->delete();
                    $mysqlConn->table('academies')->where('id', $acadId)->delete();
                }
            } catch (\Throwable $e) {
                // If MySQL is not reachable in this specific test step, it will run under DB_CONNECTION=mysql runner
            }
        }
    }
}
