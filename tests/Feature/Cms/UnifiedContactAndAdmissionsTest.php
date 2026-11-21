<?php

namespace Tests\Feature\Cms;

use App\Domain\Academies\Models\Academy;
use App\Domain\Cms\Models\CmsContactInquiry;
use App\Domain\Cms\Services\ContactValidationService;
use App\Domain\Courses\Models\Course;
use App\Domain\Notifications\Models\AcademyNotification;
use App\Livewire\Admin\Contact\ContactInquiriesIndex;
use App\Livewire\Marketing\ChatbotWidget;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UnifiedContactAndAdmissionsTest extends TestCase
{
    private static bool $sqliteMigrated = false;

    private static bool $mysqlMigrated = false;

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
            'session.driver' => 'array',
        ]);
        $this->app->forgetInstance('cache');
        $this->app->forgetInstance('cache.store');
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

    protected function useEngine(string $engine): void
    {
        config([
            'cache.default' => 'array',
            'permission.cache.store' => 'array',
            'session.driver' => 'array',
        ]);
        $this->app->forgetInstance('cache');
        $this->app->forgetInstance('cache.store');

        if ($engine === 'sqlite') {
            $sqlitePath = storage_path('framework/testing/test_contact_phase12_4.sqlite');
            if (! file_exists(dirname($sqlitePath))) {
                mkdir(dirname($sqlitePath), 0755, true);
            }

            config([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => $sqlitePath,
                'database.connections.sqlite.foreign_key_constraints' => true,
            ]);
            DB::purge('sqlite');
            DB::setDefaultConnection('sqlite');

            if (! static::$sqliteMigrated) {
                if (file_exists($sqlitePath)) {
                    @unlink($sqlitePath);
                }
                touch($sqlitePath);
                Artisan::call('migrate:fresh', ['--database' => 'sqlite', '--force' => true]);
                static::$sqliteMigrated = true;
            } else {
                $tables = DB::connection('sqlite')->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                DB::connection('sqlite')->statement('PRAGMA foreign_keys = OFF;');
                foreach ($tables as $t) {
                    if ($t->name !== 'migrations') {
                        DB::connection('sqlite')->table($t->name)->delete();
                    }
                }
                DB::connection('sqlite')->statement('PRAGMA foreign_keys = ON;');
            }
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

            if (! static::$mysqlMigrated) {
                Artisan::call('migrate:fresh', ['--database' => 'mysql', '--force' => true]);
                static::$mysqlMigrated = true;
            } else {
                $tables = DB::connection('mysql')->select('SHOW TABLES');
                DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS = 0;');
                foreach ($tables as $t) {
                    $vals = array_values((array) $t);
                    $table = $vals[0];
                    if ($table !== 'migrations') {
                        DB::connection('mysql')->table($table)->delete();
                    }
                }
                DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS = 1;');
            }
        }

        Artisan::call('db:seed', ['--class' => RoleSeeder::class, '--database' => $engine]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Storage::fake('public');
    }

    private function createAcademy(): Academy
    {
        return Academy::create([
            'name' => 'SEO Contact Academy',
            'slug' => 'seo-contact-'.uniqid(),
            'status' => 'active',
            'is_active' => true,
            'default_locale' => 'en',
            'timezone' => 'UTC',
            'currency' => 'USD',
        ]);
    }

    private function createUserWithRole(string $role): User
    {
        $user = User::create([
            'name' => ucfirst($role).' User',
            'email' => $role.'_'.uniqid().'@seoacademy.test',
            'password' => bcrypt('Password123!'),
            'role' => $role,
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    #[DataProvider('engineProvider')]
    public function test_contact_inquiries_admin_access_authorization(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        $admin = $this->createUserWithRole('admin');
        $support = $this->createUserWithRole('support_staff');
        $trainer = $this->createUserWithRole('trainer');
        $student = $this->createUserWithRole('student');

        // Admin can access
        $this->actingAs($admin)
            ->get('/admin/contact-inquiries')
            ->assertStatus(200);

        // Support staff can access
        $this->actingAs($support)
            ->get('/admin/contact-inquiries')
            ->assertStatus(200);

        // Trainer is forbidden
        $this->actingAs($trainer)
            ->get('/admin/contact-inquiries')
            ->assertStatus(403);

        // Student is forbidden
        $this->actingAs($student)
            ->get('/admin/contact-inquiries')
            ->assertStatus(403);
    }

    #[DataProvider('engineProvider')]
    public function test_worldwide_email_validation(string $engine): void
    {
        $this->useEngine($engine);
        $validator = app(ContactValidationService::class);

        // Valid emails
        $this->assertTrue($validator->validateEmail('student@gmail.com')['valid']);
        $this->assertTrue($validator->validateEmail('user@outlook.com')['valid']);
        $this->assertTrue($validator->validateEmail('admissions@yahoo.co.uk')['valid']);
        $this->assertTrue($validator->validateEmail('director@company.org')['valid']);
        $this->assertTrue($validator->validateEmail('alex@agency.marketing')['valid']);

        // Domain normalization (lowercased)
        $normResult = $validator->validateEmail('Panda@EXAMPLE.COM');
        $this->assertTrue($normResult['valid']);
        $this->assertEquals('Panda@example.com', $normResult['normalized']);

        // Invalid emails rejected
        $this->assertFalse($validator->validateEmail('')['valid']);
        $this->assertFalse($validator->validateEmail('plainaddress')['valid']);
        $this->assertFalse($validator->validateEmail('missingdomain@')['valid']);
        $this->assertFalse($validator->validateEmail('@missinguser.com')['valid']);
        $this->assertFalse($validator->validateEmail('two@@ats.com')['valid']);
        $this->assertFalse($validator->validateEmail('spaces in@address.com')['valid']);
        $this->assertFalse($validator->validateEmail('user@localhost')['valid']);
        $this->assertFalse($validator->validateEmail('user@nodotdomain')['valid']);
        $this->assertFalse($validator->validateEmail(str_repeat('a', 250).'@domain.com')['valid']);
    }

    #[DataProvider('engineProvider')]
    public function test_worldwide_phone_validation_across_countries(string $engine): void
    {
        $this->useEngine($engine);
        $validator = app(ContactValidationService::class);

        // 1. India (+91)
        $in = $validator->validatePhone('9876543210', 'IN');
        $this->assertTrue($in['valid']);
        $this->assertEquals('+919876543210', $in['e164']);
        $this->assertEquals('IN', $in['country_code']);

        // 2. United States (+1)
        $us = $validator->validatePhone('2025550143', 'US');
        $this->assertTrue($us['valid']);
        $this->assertEquals('+12025550143', $us['e164']);
        $this->assertEquals('US', $us['country_code']);

        // 3. United Arab Emirates (+971)
        $ae = $validator->validatePhone('501234567', 'AE');
        $this->assertTrue($ae['valid']);
        $this->assertEquals('+971501234567', $ae['e164']);
        $this->assertEquals('AE', $ae['country_code']);

        // 4. United Kingdom (+44)
        $gb = $validator->validatePhone('2079460000', 'GB');
        $this->assertTrue($gb['valid']);
        $this->assertEquals('+442079460000', $gb['e164']);
        $this->assertEquals('GB', $gb['country_code']);

        // 5. Germany (+49)
        $de = $validator->validatePhone('15123456789', 'DE');
        $this->assertTrue($de['valid']);
        $this->assertEquals('+4915123456789', $de['e164']);

        // 6. Australia (+61)
        $au = $validator->validatePhone('412345678', 'AU');
        $this->assertTrue($au['valid']);
        $this->assertEquals('+61412345678', $au['e164']);

        // 7. Singapore (+65)
        $sg = $validator->validatePhone('81234567', 'SG');
        $this->assertTrue($sg['valid']);
        $this->assertEquals('+6581234567', $sg['e164']);

        // 8. Saudi Arabia (+966)
        $sa = $validator->validatePhone('512345678', 'SA');
        $this->assertTrue($sa['valid']);
        $this->assertEquals('+966512345678', $sa['e164']);

        // 9. Brazil (+55)
        $br = $validator->validatePhone('11987654321', 'BR');
        $this->assertTrue($br['valid']);
        $this->assertEquals('+5511987654321', $br['e164']);

        // 10. Japan (+81)
        $jp = $validator->validatePhone('9012345678', 'JP');
        $this->assertTrue($jp['valid']);
        $this->assertEquals('+819012345678', $jp['e164']);

        // 11. Canada (shared +1 country code)
        $ca = $validator->validatePhone('4165550198', 'CA');
        $this->assertTrue($ca['valid']);
        $this->assertEquals('+14165550198', $ca['e164']);

        // Invalid number rejection
        $invalid = $validator->validatePhone('1234', 'US');
        $this->assertFalse($invalid['valid']);
        $this->assertNotNull($invalid['error']);
    }

    #[DataProvider('engineProvider')]
    public function test_contact_inquiries_admin_component_and_lifecycle(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createUserWithRole('admin');
        $supportStaff = $this->createUserWithRole('support_staff');

        // Create sample inquiries
        $inquiry1 = CmsContactInquiry::create([
            'academy_id' => $academy->id,
            'name' => 'Alice Johnson',
            'email' => 'alice@example.com',
            'phone' => '+12025550143',
            'phone_country_code' => 'US',
            'subject' => 'Course Inquiry: SEO Audit',
            'message' => 'Interested in auditing classes.',
            'course_interest' => 'Technical SEO',
            'status' => 'new',
            'source' => 'contact_form',
            'consent_text' => 'I agree that SEO Academy may contact me.',
            'consented_at' => now(),
            'consent_version' => 'v1.0',
        ]);

        $inquiry2 = CmsContactInquiry::create([
            'academy_id' => $academy->id,
            'name' => 'Bob Builder',
            'email' => 'bob@builder.com',
            'phone' => '+919876543210',
            'phone_country_code' => 'IN',
            'subject' => 'Admissions Chatbot Lead',
            'message' => 'Looking for batch pricing.',
            'course_interest' => 'Link Building',
            'status' => 'contacted',
            'source' => 'chatbot',
            'consent_text' => 'I agree that SEO Academy may contact me.',
            'consented_at' => now(),
            'consent_version' => 'v1.0',
        ]);

        // Test Livewire component
        $component = Livewire::actingAs($admin)
            ->test(ContactInquiriesIndex::class)
            ->assertSee('Contact Inquiries')
            ->assertSee('Alice Johnson')
            ->assertSee('Bob Builder');

        // Test search
        $component->set('search', 'Alice')
            ->assertSee('Alice Johnson')
            ->assertDontSee('Bob Builder');

        // Test status filter
        $component->set('search', '')
            ->set('statusFilter', 'contacted')
            ->assertSee('Bob Builder')
            ->assertDontSee('Alice Johnson');

        // Test source filter
        $component->set('statusFilter', 'all')
            ->set('sourceFilter', 'chatbot')
            ->assertSee('Bob Builder')
            ->assertDontSee('Alice Johnson');

        // Test detail modal, assignment, and status change
        $component->call('openInquiryModal', $inquiry1->id)
            ->assertSet('selectedInquiryId', $inquiry1->id);

        $component->call('updateStatus', 'in_progress')
            ->assertSet('editStatus', 'in_progress');

        $inquiry1->refresh();
        $this->assertEquals('in_progress', $inquiry1->status);
        $this->assertNotEmpty($inquiry1->activity_history);

        // Test staff assignment
        $component->call('assignStaff', $supportStaff->id)
            ->assertSet('editAssignedStaffId', $supportStaff->id);

        $inquiry1->refresh();
        $this->assertEquals($supportStaff->id, $inquiry1->assigned_to_user_id);

        // Verify assignment notification sent to support staff
        $notification = AcademyNotification::where('target', (string) $supportStaff->id)->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('Alice Johnson', $notification->message);

        // Test internal notes
        $component->set('newInternalNote', 'Called student, scheduled demo for Monday.')
            ->call('submitInternalNote');

        $inquiry1->refresh();
        $this->assertStringContainsString('Called student, scheduled demo for Monday.', $inquiry1->admin_notes);

        // Test follow-up date
        $followDate = now()->addDays(3)->format('Y-m-d');
        $component->set('editFollowUpDate', $followDate)
            ->call('saveFollowUpDate');

        $inquiry1->refresh();
        $this->assertEquals($followDate, $inquiry1->follow_up_date->format('Y-m-d'));
    }

    #[DataProvider('engineProvider')]
    public function test_chatbot_visitor_welcome_and_natural_name_identification(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        // 1. First-time visitor opens chatbot: sees warm greeting asking for name
        $component = Livewire::test(ChatbotWidget::class, ['academyId' => $academy->id])
            ->set('isOpen', true);

        $conversation = $component->get('conversation');
        $firstMsg = $conversation[0]['text'];
        $this->assertStringContainsString('Before we begin, may I know your name?', $firstMsg);

        // 2. Natural language introduction: "My name is Panda"
        $component->call('submitVisitorName', 'My name is Panda');

        $this->assertEquals('Panda', $component->get('visitorName'));
        $this->assertTrue($component->get('hasIdentified'));
        $this->assertEquals('Panda', session()->get('seo_chatbot_visitor_name'));

        // Verify response acknowledges warmly
        $conversation = $component->get('conversation');
        $latestBotMsg = end($conversation)['text'];
        $this->assertStringContainsString('Hi Panda! Nice to meet you. How can I help you today?', $latestBotMsg);

        // Verify quick options
        $suggestions = $component->get('suggestedQuestions');
        $this->assertContains('Explore Courses', $suggestions);
        $this->assertContains('Course Fees & Pricing', $suggestions);
        $this->assertContains('Course Duration & Schedule', $suggestions);
        $this->assertContains('Talk to Admissions', $suggestions);
        $this->assertContains('Talk to Support', $suggestions);
    }

    #[DataProvider('engineProvider')]
    public function test_chatbot_visitor_skip_name_option(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        $component = Livewire::test(ChatbotWidget::class, ['academyId' => $academy->id])
            ->set('isOpen', true);

        // Skip name collection
        $component->call('skipVisitorName');

        $this->assertTrue($component->get('hasIdentified'));
        $conversation = $component->get('conversation');
        $latestBotMsg = end($conversation)['text'];
        $this->assertStringContainsString('No problem! How can I help you today?', $latestBotMsg);
    }

    #[DataProvider('engineProvider')]
    public function test_chatbot_returning_visitor_name_persistence(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();

        // Set session visitor name
        session()->put('seo_chatbot_visitor_name', 'Panda');
        session()->put('seo_chatbot_identified', true);

        $component = Livewire::test(ChatbotWidget::class, ['academyId' => $academy->id])
            ->set('isOpen', true);

        $this->assertEquals('Panda', $component->get('visitorName'));
        $conversation = $component->get('conversation');
        $firstMsg = $conversation[0]['text'];
        $this->assertStringContainsString('Welcome back, Panda! How can I assist you today?', $firstMsg);
    }

    #[DataProvider('engineProvider')]
    public function test_chatbot_admissions_intent_detection_and_proactive_lead_capture(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createUserWithRole('admin');

        // Create published course
        Course::create([
            'academy_id' => $academy->id,
            'title' => 'Technical SEO Mastery',
            'slug' => 'technical-seo-mastery',
            'description' => 'Advanced technical audit training.',
            'difficulty' => 'Advanced',
            'price' => 499,
            'currency' => 'USD',
            'status' => 'Published',
            'visibility' => 'public',
        ]);

        $component = Livewire::test(ChatbotWidget::class, ['academyId' => $academy->id])
            ->set('isOpen', true)
            ->call('submitVisitorName', 'Panda');

        // Visitor asks admissions question: "How much are the course fees and how do I enroll?"
        $component->set('message', 'How much are the course fees and how do I enroll?')
            ->call('sendMessage');

        // Lead form should automatically open
        $this->assertTrue($component->get('showLeadForm'));

        // Attempt submission without consent -> must be rejected
        $component->set('leadCountryCode', 'IN')
            ->set('leadPhone', '9876543210')
            ->set('leadConsent', false)
            ->call('submitLead');

        $this->assertNotNull($component->get('leadConsentError'));
        $this->assertDatabaseMissing('cms_contact_inquiries', ['phone' => '+919876543210']);

        // Now check consent and submit valid lead
        $component->set('leadConsent', true)
            ->set('leadEmail', 'panda@example.com')
            ->call('submitLead');

        $this->assertTrue($component->get('leadSubmitted'));
        $this->assertFalse($component->get('showLeadForm'));

        // Verify inquiry created with source 'chatbot', consent, and formatted phone
        $inquiry = CmsContactInquiry::where('academy_id', $academy->id)->first();
        $this->assertNotNull($inquiry);
        $this->assertEquals('Panda', $inquiry->name);
        $this->assertEquals('+919876543210', $inquiry->phone);
        $this->assertEquals('IN', $inquiry->phone_country_code);
        $this->assertEquals('chatbot', $inquiry->source);
        $this->assertStringContainsString('I agree that SEO Academy may contact me', $inquiry->consent_text);
        $this->assertNotNull($inquiry->consented_at);

        // Verify conversation in chat has confirmation message
        $conversation = $component->get('conversation');
        $latestBotMsg = end($conversation)['text'];
        $this->assertStringContainsString('Thank you, Panda! Your enquiry has been received.', $latestBotMsg);

        // Verify notification sent to admin
        $adminNotification = AcademyNotification::where('target', (string) $admin->id)->first();
        $this->assertNotNull($adminNotification);
        $this->assertStringContainsString('Panda', $adminNotification->title);
    }

    #[DataProvider('engineProvider')]
    public function test_public_contact_form_submission_with_worldwide_validation(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createUserWithRole('admin');

        // Test GET contact page loads with country codes
        $this->get('/contact')
            ->assertStatus(200)
            ->assertSee('Phone Number (Optional)')
            ->assertSee('I agree that SEO Academy may contact me about courses and admissions');

        // Test POST without consent fails validation
        $response = $this->from('/contact')->post('/contact', [
            'name' => 'Sarah Connor',
            'email' => 'sarah@resistance.org',
            'phone' => '2025550143',
            'phone_country_code' => 'US',
            'subject' => 'Corporate SEO Workshop',
            'message' => 'We would like to book private training for our team.',
        ]);
        $response->assertSessionHasErrors('consent');

        // Test POST with invalid phone fails validation
        $response = $this->from('/contact')->post('/contact', [
            'name' => 'Sarah Connor',
            'email' => 'sarah@resistance.org',
            'phone' => '1234', // invalid US phone
            'phone_country_code' => 'US',
            'subject' => 'Corporate SEO Workshop',
            'message' => 'We would like to book private training for our team.',
            'consent' => '1',
        ]);
        $response->assertSessionHasErrors('phone');

        // Test POST with valid details succeeds
        $response = $this->from('/contact')->post('/contact', [
            'name' => 'Sarah Connor',
            'email' => 'Sarah@RESISTANCE.ORG',
            'phone' => '2025550143',
            'phone_country_code' => 'US',
            'subject' => 'Corporate SEO Workshop',
            'message' => 'We would like to book private training for our team.',
            'interested_service' => 'Enterprise Team Training',
            'consent' => '1',
        ]);

        $response->assertSessionHas('success');

        // Verify inquiry in database
        $inquiry = CmsContactInquiry::where('name', 'Sarah Connor')->first();
        $this->assertNotNull($inquiry);
        $this->assertEquals('Sarah@resistance.org', $inquiry->email); // domain normalized lowercased
        $this->assertEquals('+12025550143', $inquiry->phone);
        $this->assertEquals('US', $inquiry->phone_country_code);
        $this->assertEquals('contact_form', $inquiry->source);
        $this->assertStringContainsString('I agree that SEO Academy may contact me', $inquiry->consent_text);
        $this->assertNotNull($inquiry->consented_at);

        // Verify admin notification
        $notification = AcademyNotification::where('target', (string) $admin->id)
            ->where('title', 'like', '%Sarah Connor%')
            ->first();
        $this->assertNotNull($notification);
    }
}
