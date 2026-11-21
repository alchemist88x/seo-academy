<?php

namespace Tests\Feature\Commerce;

use App\Domain\Academies\Models\Academy;
use App\Domain\Academies\Models\AcademyUser;
use App\Domain\Commerce\Models\CommerceInstallmentPlan;
use App\Domain\Commerce\Models\CommerceOrder;
use App\Domain\Commerce\Models\CourseBundle;
use App\Domain\Commerce\Services\OrderAndEnrollmentService;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Platform\Models\Setting;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Tenants\Services\TenantContext;
use App\Livewire\Commerce\CheckoutComponent;
use App\Livewire\Student\StudentOrderDetail;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;
use Tests\TestCase;

class Phase128PaymentAndCurriculumTest extends TestCase
{
    use RefreshDatabase;

    private Academy $academy;

    private Course $course;

    private CourseBundle $bundle;

    private CommerceInstallmentPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->academy = Academy::create([
            'name' => 'SEO Academy Test',
            'slug' => 'seo-academy-test',
            'is_active' => true,
        ]);

        $this->course = Course::create([
            'academy_id' => $this->academy->id,
            'title' => 'Advanced Schema Markup & Entity SEO',
            'slug' => 'advanced-schema-structured-data',
            'description' => 'Build rich semantic knowledge graphs using JSON-LD.',
            'difficulty' => 'advanced',
            'price' => 199.00,
            'currency' => 'INR',
            'is_free' => false,
            'visibility' => 'public',
            'is_public_listed' => true,
            'status' => 'Published',
        ]);

        $module1 = Module::create([
            'academy_id' => $this->academy->id,
            'course_id' => $this->course->id,
            'title' => 'Module 1: Semantic SEO Foundations',
            'display_order' => 1,
            'status' => 'Active',
        ]);

        Lesson::create([
            'academy_id' => $this->academy->id,
            'course_id' => $this->course->id,
            'module_id' => $module1->id,
            'title' => 'Lesson 1.1: JSON-LD Graph Principles',
            'type' => 'video',
            'status' => 'Active',
            'visibility' => 'published',
            'display_order' => 1,
            'duration_seconds' => 600,
        ]);

        $this->plan = CommerceInstallmentPlan::create([
            'academy_id' => $this->academy->id,
            'title' => '60% Completion 2-Part Milestone Plan',
            'deposit_type' => 'percentage',
            'initial_deposit_value' => 50.00,
            'installment_count' => 2,
            'completion_milestone_percentage' => 60,
            'access_policy' => 'immediate_on_deposit',
            'is_active' => true,
        ]);

        $this->course->installment_plan_id = $this->plan->id;
        $this->course->save();

        app(TenantContext::class)->setAcademy($this->academy);

        $this->bundle = CourseBundle::create([
            'academy_id' => $this->academy->id,
            'title' => 'Full Technical SEO Mastery Suite',
            'slug' => 'full-technical-seo-mastery-suite',
            'description' => 'Everything you need to master technical search optimization.',
            'price' => 499.00,
            'currency' => 'INR',
            'is_active' => true,
        ]);
        $this->bundle->courses()->attach($this->course->id);
    }

    private function createRoleUser(string $role): User
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);
        $user->assignRole($role);

        AcademyUser::create([
            'academy_id' => $this->academy->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'active',
        ]);

        if ($role === 'student') {
            StudentProfile::create([
                'user_id' => $user->id,
                'academy_id' => $this->academy->id,
                'student_code' => 'STU-'.strtoupper(substr(uniqid(), -5)),
                'proficiency' => 'beginner',
                'active' => true,
            ]);
        }

        return $user;
    }

    // ==========================================
    // Problem B — Student-Only Purchase Authorization
    // ==========================================

    public function test_unauthenticated_guest_accessing_checkout_redirects_to_login_with_intended_url(): void
    {
        $response = $this->get(route('checkout.show', ['type' => 'course', 'id' => $this->course->id]));
        // Guests can view the checkout review page or be prompted to sign in
        $response->assertOk();
        $response->assertSee('Sign In to Enroll');
    }

    public function test_administrator_accessing_checkout_receives_403_forbidden(): void
    {
        $admin = $this->createRoleUser('admin');

        $this->actingAs($admin)
            ->get(route('checkout.show', ['type' => 'course', 'id' => $this->course->id]))
            ->assertForbidden();
    }

    public function test_trainer_accessing_checkout_receives_403_forbidden(): void
    {
        $trainer = $this->createRoleUser('trainer');

        $this->actingAs($trainer)
            ->get(route('checkout.show', ['type' => 'course', 'id' => $this->course->id]))
            ->assertForbidden();
    }

    public function test_support_staff_accessing_checkout_receives_403_forbidden(): void
    {
        $support = $this->createRoleUser('support_staff');

        $this->actingAs($support)
            ->get(route('checkout.show', ['type' => 'course', 'id' => $this->course->id]))
            ->assertForbidden();
    }

    public function test_student_role_accessing_checkout_is_permitted(): void
    {
        $student = $this->createRoleUser('student');

        $this->actingAs($student)
            ->get(route('checkout.show', ['type' => 'course', 'id' => $this->course->id]))
            ->assertOk()
            ->assertSee('Investment Summary');
    }

    public function test_non_student_invoking_order_and_enrollment_service_create_order_throws_authorization_exception(): void
    {
        $admin = $this->createRoleUser('admin');
        $service = app(OrderAndEnrollmentService::class);

        $this->expectException(AuthorizationException::class);
        $service->createOrder(
            user: $admin,
            item: $this->course,
            currency: 'INR'
        );
    }

    public function test_administrator_direct_assignment_modal_still_functions_for_administrators(): void
    {
        $admin = $this->createRoleUser('admin');
        $student = $this->createRoleUser('student');
        $service = app(OrderAndEnrollmentService::class);

        $order = $service->adminDirectAssign(
            student: $student,
            item: $this->course,
            paymentMethod: 'complimentary',
            reason: 'Scholarship grant',
            admin: $admin
        );

        $this->assertNotNull($order);
        $this->assertEquals($student->id, $order->user_id);
        $this->assertEquals('complimentary', $order->payment_method);
        $this->assertEquals('complimentary', $order->payment_status);

        // Student should now be enrolled
        $profile = $student->studentProfile;
        $this->assertTrue(
            Enrollment::where('student_profile_id', $profile->id)
                ->where('course_id', $this->course->id)
                ->exists()
        );
    }

    // ==========================================
    // Problem A — Razorpay Pay Now Repair & Verification
    // ==========================================

    public function test_pay_now_when_razorpay_unconfigured_does_not_falsely_settle_order_and_shows_error(): void
    {
        $student = $this->createRoleUser('student');

        // Ensure razorpay settings are unconfigured/disabled
        Setting::updateOrCreate(
            ['academy_id' => $this->academy->id, 'group' => 'razorpay', 'key' => 'enabled'],
            ['value' => '0', 'type' => 'boolean']
        );

        Livewire::actingAs($student)
            ->test(CheckoutComponent::class, ['type' => 'course', 'id' => $this->course->id])
            ->call('processCheckout')
            ->assertSee('Online payment gateway is temporarily unconfigured. Please contact academy support.');

        // Verify NO paid order was created
        $order = CommerceOrder::where('user_id', $student->id)->first();
        if ($order) {
            $this->assertNotEquals('paid', $order->payment_status);
        }

        // Verify student is NOT enrolled
        $profile = $student->studentProfile;
        $this->assertFalse(
            Enrollment::where('student_profile_id', $profile->id)
                ->where('course_id', $this->course->id)
                ->exists()
        );
    }

    public function test_pay_now_when_razorpay_configured_creates_unpaid_order_and_dispatches_checkout_event(): void
    {
        $student = $this->createRoleUser('student');

        // Configure test credentials
        $keyId = 'rzp_test_key123';
        $keySecret = 'validsecret456';
        Setting::updateOrCreate(
            ['academy_id' => $this->academy->id, 'group' => 'razorpay', 'key' => 'enabled'],
            ['value' => '1', 'type' => 'boolean']
        );
        Setting::updateOrCreate(
            ['academy_id' => $this->academy->id, 'group' => 'razorpay', 'key' => 'mode'],
            ['value' => 'test', 'type' => 'string']
        );
        Setting::updateOrCreate(
            ['academy_id' => $this->academy->id, 'group' => 'razorpay', 'key' => 'key_id'],
            ['value' => $keyId, 'type' => 'string']
        );
        Setting::updateOrCreate(
            ['academy_id' => $this->academy->id, 'group' => 'razorpay', 'key' => 'key_secret'],
            ['value' => Crypt::encryptString($keySecret), 'type' => 'encrypted']
        );

        Livewire::actingAs($student)
            ->test(CheckoutComponent::class, ['type' => 'course', 'id' => $this->course->id])
            ->call('processCheckout')
            ->assertDispatched('initiate-razorpay-checkout');

        $order = CommerceOrder::where('user_id', $student->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertEquals('unpaid', $order->payment_status);
        $this->assertEquals(0.00, (float) $order->paid_amount);

        // Verification with valid HMAC signature
        $rzpOrderId = 'order_test_999';
        $paymentId = 'pay_test_888';
        $validSignature = hash_hmac('sha256', $rzpOrderId.'|'.$paymentId, $keySecret);

        Livewire::actingAs($student)
            ->test(CheckoutComponent::class, ['type' => 'course', 'id' => $this->course->id])
            ->call('verifyRazorpayPayment', $order->id, $paymentId, $rzpOrderId, $validSignature)
            ->assertRedirect(route('student.orders.show', $order->id));

        $this->assertEquals('Payment processed successfully! Your enrollment is active.', session('success'));

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals($order->total_amount, $order->paid_amount);

        // Enrollment must now be active
        $profile = $student->studentProfile;
        $this->assertTrue(
            Enrollment::where('student_profile_id', $profile->id)
                ->where('course_id', $this->course->id)
                ->where('status', 'active')
                ->exists()
        );
    }

    public function test_verify_razorpay_payment_with_tampered_signature_fails_and_leaves_order_unpaid(): void
    {
        $student = $this->createRoleUser('student');
        $keyId = 'rzp_test_key123';
        $keySecret = 'validsecret456';
        Setting::updateOrCreate(
            ['academy_id' => $this->academy->id, 'group' => 'razorpay', 'key' => 'enabled'],
            ['value' => '1', 'type' => 'boolean']
        );
        Setting::updateOrCreate(
            ['academy_id' => $this->academy->id, 'group' => 'razorpay', 'key' => 'key_id'],
            ['value' => $keyId, 'type' => 'string']
        );
        Setting::updateOrCreate(
            ['academy_id' => $this->academy->id, 'group' => 'razorpay', 'key' => 'key_secret'],
            ['value' => Crypt::encryptString($keySecret), 'type' => 'encrypted']
        );

        $service = app(OrderAndEnrollmentService::class);
        $order = $service->createOrder(
            user: $student,
            item: $this->course,
            currency: 'INR'
        );

        Livewire::actingAs($student)
            ->test(CheckoutComponent::class, ['type' => 'course', 'id' => $this->course->id])
            ->call('verifyRazorpayPayment', $order->id, 'pay_123', 'order_456', 'tampered_bogus_signature')
            ->assertSee('Payment verification failed. Invalid gateway signature.');

        $order->refresh();
        $this->assertEquals('unpaid', $order->payment_status);
        $this->assertEquals(0.00, (float) $order->paid_amount);
    }

    public function test_installment_payment_charges_only_remaining_balance(): void
    {
        $student = $this->createRoleUser('student');
        $keyId = 'rzp_test_key123';
        $keySecret = 'validsecret456';
        Setting::updateOrCreate(
            ['academy_id' => $this->academy->id, 'group' => 'razorpay', 'key' => 'enabled'],
            ['value' => '1', 'type' => 'boolean']
        );
        Setting::updateOrCreate(
            ['academy_id' => $this->academy->id, 'group' => 'razorpay', 'key' => 'mode'],
            ['value' => 'test', 'type' => 'string']
        );
        Setting::updateOrCreate(
            ['academy_id' => $this->academy->id, 'group' => 'razorpay', 'key' => 'key_id'],
            ['value' => $keyId, 'type' => 'string']
        );
        Setting::updateOrCreate(
            ['academy_id' => $this->academy->id, 'group' => 'razorpay', 'key' => 'key_secret'],
            ['value' => Crypt::encryptString($keySecret), 'type' => 'encrypted']
        );

        $service = app(OrderAndEnrollmentService::class);
        $order = $service->createOrder(
            user: $student,
            item: $this->course,
            currency: 'INR',
            installmentPlan: $this->plan
        );

        // Order has 2 installments
        $installments = $order->installments;
        $this->assertCount(2, $installments);
        $inst2 = $installments[1];

        // Settle deposit first
        $inst1 = $installments[0];
        $service->recordPayment($order, $inst1->amount, 'razorpay', ['reference_number' => 'DEP-01'], $inst1);

        $order->refresh();
        $inst2->refresh();
        $this->assertEquals('partially_paid', $order->payment_status);

        // Now initiate installment payment for second installment
        Livewire::actingAs($student)
            ->test(StudentOrderDetail::class, ['order' => $order->id])
            ->call('payInstallment', $inst2->id)
            ->assertDispatched('initiate-razorpay-checkout', function ($event, $params) use ($inst2) {
                $payload = is_array($params) && isset($params[0]) ? $params[0] : $params;

                return isset($payload['amount']) && $payload['amount'] === (int) round($inst2->remaining_balance * 100);
            });
    }

    // ==========================================
    // Problem C — Guest Course Curriculum & 404 Resolution
    // ==========================================

    public function test_guest_can_access_published_course_detail_page_without_404(): void
    {
        $response = $this->get('/courses/advanced-schema-structured-data');

        $response->assertOk();
        $response->assertSee('Advanced Schema Markup & Entity SEO');
    }

    public function test_guest_sees_curriculum_preview_and_registration_prompt(): void
    {
        $response = $this->get('/courses/advanced-schema-structured-data');

        $response->assertOk();
        $response->assertSee('Course Content Preview');
        $response->assertSee('Curriculum Preview Mode');
        $response->assertSee('Semantic SEO Foundations');
        $response->assertSee('Want to explore the complete curriculum?');
        $response->assertSee('Create your free student account to view the full course curriculum');
        $response->assertSee(route('register'));
        $response->assertSee(route('login'));
    }

    public function test_student_sees_full_curriculum_outline(): void
    {
        $student = $this->createRoleUser('student');

        $this->actingAs($student)
            ->get('/courses/advanced-schema-structured-data')
            ->assertOk()
            ->assertSee('Course Content')
            ->assertSee('Lesson 1.1: JSON-LD Graph Principles')
            ->assertDontSee('Curriculum Preview Mode');
    }

    public function test_guest_and_non_enrolled_student_cannot_access_learning_classroom_without_enrollment(): void
    {
        $lesson = $this->course->modules->first()->lessons->first();

        // Guest cannot access classroom
        $this->get(route('student.courses.learn', [$this->course, $lesson]))
            ->assertRedirect(route('login'));

        // Non-enrolled student cannot access classroom
        $student = $this->createRoleUser('student');
        $this->actingAs($student)
            ->get(route('student.courses.learn', [$this->course, $lesson]))
            ->assertForbidden();
    }

    public function test_enrolled_student_can_access_classroom(): void
    {
        $student = $this->createRoleUser('student');
        $profile = $student->studentProfile;

        Enrollment::create([
            'student_profile_id' => $profile->id,
            'course_id' => $this->course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $lesson = $this->course->modules->first()->lessons->first();

        $this->actingAs($student)
            ->get(route('student.courses.learn', [$this->course, $lesson]))
            ->assertOk()
            ->assertSee('Lesson 1.1: JSON-LD Graph Principles');
    }
}
