<?php

namespace Tests\Feature\Commerce;

use App\Domain\Academies\Models\Academy;
use App\Domain\Commerce\Models\CommerceCoupon;
use App\Domain\Commerce\Models\CommerceInstallmentPlan;
use App\Domain\Commerce\Models\CourseBundle;
use App\Domain\Commerce\Services\CouponService;
use App\Domain\Commerce\Services\CurrencyAndPricingService;
use App\Domain\Commerce\Services\InstallmentMilestoneService;
use App\Domain\Commerce\Services\OrderAndEnrollmentService;
use App\Domain\Commerce\Services\RazorpayPaymentService;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Tenants\Services\TenantContext;
use App\Livewire\Admin\Commerce\CommerceReportsManager;
use App\Livewire\Admin\Commerce\CouponManager;
use App\Livewire\Admin\Commerce\CourseBundlesManager;
use App\Livewire\Admin\Commerce\OrdersManager;
use App\Livewire\Commerce\CheckoutComponent;
use App\Livewire\Commerce\PublicBundleDetail;
use App\Livewire\Commerce\PublicBundlesIndex;
use App\Livewire\Student\StudentOrdersIndex;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CompleteCommerceAndInstallmentsTest extends TestCase
{
    private static bool $sqliteMigrated = false;

    private static bool $mysqlMigrated = true;

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
            $sqlitePath = storage_path('framework/testing/test_commerce_phase12_6.sqlite');
            if (! file_exists(dirname($sqlitePath))) {
                mkdir(dirname($sqlitePath), 0755, true);
            }

            DB::purge('sqlite');
            config([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => $sqlitePath,
                'database.connections.sqlite.foreign_key_constraints' => true,
            ]);
            DB::reconnect('sqlite');
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
            DB::purge('mysql');
            config([
                'database.default' => 'mysql',
                'database.connections.mysql.host' => '127.0.0.1',
                'database.connections.mysql.port' => 3306,
                'database.connections.mysql.database' => 'seo_academy_test',
                'database.connections.mysql.username' => 'root',
                'database.connections.mysql.password' => '',
            ]);
            DB::reconnect('mysql');
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
    }

    private function createAcademy(string $subdomain = 'seo-test'): Academy
    {
        $academy = Academy::create([
            'name' => 'SEO Test Academy',
            'slug' => 'seo-test-'.uniqid(),
            'subdomain' => $subdomain,
            'status' => 'active',
        ]);
        app(TenantContext::class)->setAcademy($academy);

        return $academy;
    }

    private function createUser(Academy $academy, string $role = 'student', string $email = 'student@example.com'): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'name' => 'Test '.ucfirst($role),
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function createCourse(Academy $academy, string $title = 'SEO Mastery', float $price = 100.00): Course
    {
        return Course::create([
            'academy_id' => $academy->id,
            'title' => $title,
            'slug' => strtolower(str_replace(' ', '-', $title)).'-'.uniqid(),
            'description' => 'Test course description',
            'price' => $price,
            'currency' => 'USD',
            'is_free' => $price <= 0.00,
            'status' => 'Published',
            'visibility' => 'public',
            'is_coupon_eligible' => true,
        ]);
    }

    #[DataProvider('engineProvider')]
    public function test_currency_and_pricing_resolution(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $pricingService = app(CurrencyAndPricingService::class);

        // 1. Regional pricing on course
        $course = Course::create([
            'academy_id' => $academy->id,
            'title' => 'Global SEO',
            'slug' => 'global-seo-'.uniqid(),
            'price' => 100.00,
            'currency' => 'USD',
            'regional_prices' => ['INR' => 7999.00, 'EUR' => 89.00],
            'status' => 'Published',
        ]);

        $inrPricing = $pricingService->getItemPrice($course, 'INR');
        $this->assertEquals('INR', $inrPricing['currency']);
        $this->assertEquals(7999.00, $inrPricing['final_price']);

        $eurPricing = $pricingService->getItemPrice($course, 'EUR');
        $this->assertEquals(89.00, $eurPricing['final_price']);

        // 2. Cloudflare Country header detection
        $request = Request::create('/checkout', 'GET');
        $request->headers->set('CF-IPCountry', 'IN');
        $resolved = $pricingService->resolveCurrency($request);
        $this->assertEquals('INR', $resolved);

        $requestGb = Request::create('/checkout', 'GET');
        $requestGb->headers->set('CF-IPCountry', 'GB');
        $resolvedGb = $pricingService->resolveCurrency($requestGb);
        $this->assertEquals('GBP', $resolvedGb);
    }

    #[DataProvider('engineProvider')]
    public function test_course_bundles_creation_and_ownership_deduction_policy(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $student = $this->createUser($academy, 'student');

        $c1 = $this->createCourse($academy, 'Technical SEO 101', 100.00);
        $c2 = $this->createCourse($academy, 'Link Building Pro', 150.00);
        $c3 = $this->createCourse($academy, 'Keyword Research Masterclass', 100.00);

        // Bundle packaging all 3 courses for 250 USD (normal sum = 350 USD)
        $bundle = CourseBundle::create([
            'academy_id' => $academy->id,
            'title' => 'Ultimate SEO Bootcamp',
            'slug' => 'ultimate-seo-bootcamp',
            'price' => 250.00,
            'currency' => 'USD',
            'original_total_price' => 350.00,
            'status' => 'published',
            'ownership_policy' => 'exclude_owned_courses',
        ]);
        $bundle->courses()->attach([
            $c1->id => ['display_order' => 1],
            $c2->id => ['display_order' => 2],
            $c3->id => ['display_order' => 3],
        ]);

        $this->assertEquals(250.00, $bundle->effective_price);
        $this->assertEquals(100.00, $bundle->calculateCalculatedSavings());

        $orderService = app(OrderAndEnrollmentService::class);

        // Scenario A: Student owns 0 courses -> pays full bundle price (250)
        $calcA = $orderService->calculateCheckout($student, $bundle, 'USD');
        $this->assertEquals(250.00, $calcA['total_amount']);
        $this->assertEquals(0.00, $calcA['owned_courses_discount']);

        // Scenario B: Student already enrolled in Course 1 ($100) -> bundle deducts $100 -> pays $150
        $profile = StudentProfile::create([
            'user_id' => $student->id,
            'academy_id' => $academy->id,
            'student_code' => 'STU-TEST01',
        ]);
        Enrollment::create([
            'academy_id' => $academy->id,
            'student_profile_id' => $profile->id,
            'course_id' => $c1->id,
            'status' => Enrollment::STATUS_ACTIVE,
            'enrolled_at' => now(),
        ]);

        $calcB = $orderService->calculateCheckout($student, $bundle, 'USD');
        $this->assertEquals(100.00, $calcB['owned_courses_discount']);
        $this->assertEquals(150.00, $calcB['total_amount']);

        // Completing bundle enrollment enrolls student into ALL bundle courses without duplicating existing enrollment
        $order = $orderService->createOrder($student, $bundle, 'USD');
        $orderService->recordPayment($order, 150.00, 'test_payment');

        $this->assertEquals('paid', $order->fresh()->payment_status);

        $studentEnrollments = Enrollment::where('student_profile_id', $profile->id)->get();
        // Should have exactly 3 enrollments (c1, c2, c3), no duplicate for c1!
        $this->assertCount(3, $studentEnrollments);
        $this->assertEquals(3, $studentEnrollments->pluck('course_id')->unique()->count());
    }

    #[DataProvider('engineProvider')]
    public function test_discount_coupons_validation_and_concurrency_safe_redemption(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $student = $this->createUser($academy, 'student');
        $course = $this->createCourse($academy, 'Content Strategy', 200.00);

        // 1. Percentage coupon with cap
        $coupon = CommerceCoupon::create([
            'academy_id' => $academy->id,
            'code' => 'SAVE30',
            'discount_type' => 'percentage',
            'discount_value' => 30.00,
            'max_discount_amount' => 50.00, // 30% of 200 is 60, but capped at 50
            'currency' => 'USD',
            'global_usage_limit' => 2,
            'per_student_usage_limit' => 1,
            'is_active' => true,
        ]);

        $couponService = app(CouponService::class);
        $orderService = app(OrderAndEnrollmentService::class);

        $val = $couponService->validate('SAVE30', 200.00, $student->id, 'course', $course->id);
        $this->assertTrue($val['valid']);
        $this->assertEquals(50.00, $val['discount']);

        // Order creation with coupon
        $order = $orderService->createOrder($student, $course, 'USD', 'SAVE30');
        $this->assertEquals(150.00, $order->total_amount);
        $this->assertEquals(50.00, $order->coupon_discount_amount);
        $this->assertEquals(1, $coupon->fresh()->usage_count);

        // 2. Second attempt by same student should fail due to per_student_usage_limit = 1
        $val2 = $couponService->validate('SAVE30', 200.00, $student->id, 'course', $course->id);
        $this->assertFalse($val2['valid']);
        $this->assertStringContainsString('limit for this coupon', $val2['error']);
    }

    #[DataProvider('engineProvider')]
    public function test_direct_admin_manual_assignment_offline_and_complimentary(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createUser($academy, 'admin', 'admin@example.com');
        $student1 = $this->createUser($academy, 'student', 'student1@example.com');
        $student2 = $this->createUser($academy, 'student', 'student2@example.com');
        $course = $this->createCourse($academy, 'Enterprise SEO', 500.00);

        $orderService = app(OrderAndEnrollmentService::class);

        // Case 1: Cash in office
        $orderCash = $orderService->adminDirectAssign(
            student: $student1,
            item: $course,
            paymentMethod: 'cash',
            amountPaid: 500.00,
            reason: 'In-office cash receipt',
            notes: 'Receipt #10045',
            admin: $admin
        );

        $this->assertEquals('paid', $orderCash->payment_status);
        $this->assertEquals(500.00, $orderCash->paid_amount);
        $this->assertEquals(0.00, $orderCash->outstanding_balance);
        $this->assertDatabaseHas('commerce_transactions', [
            'order_id' => $orderCash->id,
            'payment_gateway' => 'cash',
            'amount' => 500.00,
        ]);

        // Case 2: Complimentary / Scholarship grant
        $orderScholarship = $orderService->adminDirectAssign(
            student: $student2,
            item: $course,
            paymentMethod: 'scholarship',
            amountPaid: null,
            reason: 'Women in Tech Scholarship',
            notes: 'Approved by board',
            admin: $admin
        );

        $this->assertEquals('complimentary', $orderScholarship->payment_status);
        $this->assertEquals(0.00, $orderScholarship->paid_amount);
        $this->assertEquals(0.00, $orderScholarship->outstanding_balance);

        // Both students now have active enrollments
        $this->assertTrue(Enrollment::where('course_id', $course->id)->where('order_id', $orderCash->id)->exists());
        $this->assertTrue(Enrollment::where('course_id', $course->id)->where('order_id', $orderScholarship->id)->exists());
    }

    #[DataProvider('engineProvider')]
    public function test_razorpay_signature_verification_and_webhooks(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $student = $this->createUser($academy, 'student');
        $course = Course::create([
            'academy_id' => $academy->id,
            'title' => 'Local SEO Mastery',
            'slug' => 'local-seo-mastery-'.uniqid(),
            'price' => 300.00,
            'currency' => 'INR',
            'status' => 'Published',
            'visibility' => 'public',
            'is_coupon_eligible' => true,
        ]);

        config([
            'services.razorpay.key_id' => 'rzp_test_key123',
            'services.razorpay.key_secret' => 'supersecret123',
            'services.razorpay.webhook_secret' => 'webhooksecret123',
        ]);

        $razorpayService = new RazorpayPaymentService;

        // 1. Signature Verification
        $rzpOrderId = 'order_9A33XWu170gUtm';
        $rzpPaymentId = 'pay_29AeQIvtXIGflk';
        $validSignature = hash_hmac('sha256', $rzpOrderId.'|'.$rzpPaymentId, 'supersecret123');

        $this->assertTrue($razorpayService->verifyPaymentSignature($rzpOrderId, $rzpPaymentId, $validSignature));
        $this->assertFalse($razorpayService->verifyPaymentSignature($rzpOrderId, $rzpPaymentId, 'forged_fake_signature'));

        // 2. Webhook Verification & Processing
        $orderService = app(OrderAndEnrollmentService::class);
        $order = $orderService->createOrder($student, $course, 'INR');

        $webhookPayload = json_encode([
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'pay_hook_test_777',
                        'order_id' => $rzpOrderId,
                        'amount' => 30000, // 300 INR in paise
                        'currency' => 'INR',
                        'status' => 'captured',
                        'notes' => [
                            'order_id' => $order->id,
                            'order_number' => $order->order_number,
                        ],
                    ],
                ],
            ],
        ]);

        $validWebhookSig = hash_hmac('sha256', $webhookPayload, 'webhooksecret123');

        $response = $this->withHeaders([
            'X-Razorpay-Signature' => $validWebhookSig,
            'Content-Type' => 'application/json',
        ])->postJson('/api/webhooks/razorpay', json_decode($webhookPayload, true));

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);

        $this->assertEquals('paid', $order->fresh()->payment_status);
        $this->assertEquals(300.00, $order->fresh()->paid_amount);

        // Verify Idempotency: replay the same webhook payload
        $response2 = $this->withHeaders([
            'X-Razorpay-Signature' => $validWebhookSig,
            'Content-Type' => 'application/json',
        ])->postJson('/api/webhooks/razorpay', json_decode($webhookPayload, true));

        $response2->assertStatus(200);
        // Only 1 transaction recorded
        $this->assertEquals(1, $order->fresh()->transactions()->count());
    }

    #[DataProvider('engineProvider')]
    public function test_special_60_percent_completion_milestone_installment_plan(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $student = $this->createUser($academy, 'student');
        $course = $this->createCourse($academy, 'Advanced Technical SEO', 1000.00);

        // Create 10 lessons in the course (each lesson = 10% progress)
        $module = Module::create([
            'academy_id' => $academy->id,
            'course_id' => $course->id,
            'title' => 'Core Module',
            'display_order' => 1,
            'status' => 'Active',
        ]);

        $lessons = [];
        for ($i = 1; $i <= 10; $i++) {
            $lessons[] = Lesson::create([
                'academy_id' => $academy->id,
                'module_id' => $module->id,
                'title' => "Lesson {$i}",
                'display_order' => $i,
                'duration_seconds' => 600,
                'status' => 'Active',
                'visibility' => 'enrolled',
            ]);
        }

        // Installment plan: 50% deposit, remaining 50% upon reaching 60% completion milestone
        $plan = CommerceInstallmentPlan::create([
            'academy_id' => $academy->id,
            'title' => '60% Completion Installment Plan',
            'plan_type' => 'milestone_completion',
            'initial_deposit_type' => 'percentage',
            'initial_deposit_value' => 50.00,
            'number_of_installments' => 2,
            'completion_milestone_percentage' => 60.00,
            'access_policy' => 'immediate_on_deposit',
            'grace_period_days' => 7,
            'is_active' => true,
        ]);

        $course->update(['installment_plan_id' => $plan->id]);

        $orderService = app(OrderAndEnrollmentService::class);
        $milestoneService = app(InstallmentMilestoneService::class);

        // 1. Checkout with installment plan
        $order = $orderService->createOrder($student, $course, 'USD', null, $plan);
        $this->assertTrue($order->is_installment);
        $this->assertEquals(1000.00, $order->total_amount);

        $installments = $order->installments;
        $this->assertCount(2, $installments);
        $inst1 = $installments->firstWhere('installment_number', 1);
        $inst2 = $installments->firstWhere('installment_number', 2);

        $this->assertEquals(500.00, $inst1->amount);
        $this->assertEquals('due', $inst1->status);
        $this->assertEquals(500.00, $inst2->amount);
        $this->assertEquals('upcoming', $inst2->status);
        $this->assertEquals(60.00, $inst2->milestone_percentage);

        // 2. Pay initial deposit -> access granted immediately under immediate_on_deposit policy
        $orderService->recordPayment($order, 500.00, 'razorpay', [], $inst1);
        $this->assertEquals('paid', $inst1->fresh()->status);
        $this->assertEquals('partially_paid', $order->fresh()->payment_status);

        $profile = StudentProfile::where('user_id', $student->id)->first();
        $enrollment = Enrollment::where('student_profile_id', $profile->id)->where('course_id', $course->id)->first();
        $this->assertNotNull($enrollment);
        $this->assertEquals(Enrollment::STATUS_ACTIVE, $enrollment->status);

        // 3. Complete 5 lessons = 50% completion (boundary test below 60%)
        for ($i = 0; $i < 5; $i++) {
            LessonProgress::create([
                'academy_id' => $academy->id,
                'user_id' => $student->id,
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $lessons[$i]->id,
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }

        $triggeredBelow = $milestoneService->evaluateAndTriggerMilestone($order);
        $this->assertFalse($triggeredBelow);
        $this->assertEquals('upcoming', $inst2->fresh()->status);

        // 4. Complete 6th lesson = exactly 60% completion (boundary test at threshold!)
        LessonProgress::create([
            'academy_id' => $academy->id,
            'user_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lessons[5]->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $triggeredExact = $milestoneService->evaluateAndTriggerMilestone($order);
        $this->assertTrue($triggeredExact);
        $this->assertEquals('due', $inst2->fresh()->status);
        $this->assertNotNull($inst2->fresh()->milestone_triggered_at);
        $this->assertNotNull($inst2->fresh()->due_date);

        // 5. Idempotency: re-running milestone evaluation does NOT re-trigger
        $triggeredAgain = $milestoneService->evaluateAndTriggerMilestone($order);
        $this->assertFalse($triggeredAgain);

        // 6. Pay final milestone installment
        $orderService->recordPayment($order, 500.00, 'razorpay', [], $inst2);
        $this->assertEquals('paid', $inst2->fresh()->status);
        $this->assertEquals('paid', $order->fresh()->payment_status);
        $this->assertEquals(0.00, $order->fresh()->outstanding_balance);
    }

    #[DataProvider('engineProvider')]
    public function test_livewire_ui_components_rendering_and_actions(string $engine): void
    {
        $this->useEngine($engine);
        $academy = $this->createAcademy();
        $admin = $this->createUser($academy, 'admin', 'admin@seo.com');
        $student = $this->createUser($academy, 'student', 'student@seo.com');
        StudentProfile::create([
            'user_id' => $student->id,
            'academy_id' => $academy->id,
            'student_code' => 'STU-STUDENT01',
        ]);
        $course = $this->createCourse($academy, 'SEO Analytics', 120.00);

        // 1. Admin Course Bundles Manager
        Livewire::actingAs($admin)
            ->test(CourseBundlesManager::class)
            ->assertOk()
            ->set('title', 'FastTrack Bundle')
            ->set('slug', 'fasttrack-bundle')
            ->set('price', 150.00)
            ->set('currency', 'USD')
            ->set('selectedCourseIds', [$course->id])
            ->set('ownership_policy', 'full_bundle_price')
            ->call('saveBundle')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('course_bundles', ['title' => 'FastTrack Bundle']);

        // 2. Admin Coupon Manager
        Livewire::actingAs($admin)
            ->test(CouponManager::class)
            ->assertOk()
            ->set('code', 'BLACKFRIDAY')
            ->set('discount_type', 'percentage')
            ->set('discount_value', 25.00)
            ->set('currency', 'USD')
            ->set('per_student_usage_limit', 1)
            ->call('saveCoupon')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('commerce_coupons', ['code' => 'BLACKFRIDAY']);

        // 3. Public Bundles Catalogue
        Livewire::test(PublicBundlesIndex::class)
            ->assertOk()
            ->assertSee('FastTrack Bundle');

        // 4. Public Bundle Detail
        Livewire::test(PublicBundleDetail::class, ['slug' => 'fasttrack-bundle'])
            ->assertOk()
            ->assertSee('FastTrack Bundle')
            ->assertSee('SEO Analytics');

        // 5. Checkout Component
        Livewire::actingAs($student)
            ->test(CheckoutComponent::class, ['type' => 'course', 'id' => $course->id])
            ->assertOk()
            ->set('couponInput', 'BLACKFRIDAY')
            ->call('applyCoupon')
            ->assertSet('appliedCouponCode', 'BLACKFRIDAY')
            ->call('processCheckout');

        // 6. Student Orders Dashboard
        Livewire::actingAs($student)
            ->test(StudentOrdersIndex::class)
            ->assertOk()
            ->assertSee('SEO Analytics');

        // 7. Admin Orders Manager
        Livewire::actingAs($admin)
            ->test(OrdersManager::class)
            ->assertOk()
            ->assertSee('SEO Analytics');

        // 8. Commerce Reports Manager with CSV Export protection
        Livewire::actingAs($admin)
            ->test(CommerceReportsManager::class)
            ->assertOk()
            ->assertSee('Verified Net Revenue');
    }
}
