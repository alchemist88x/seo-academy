<?php

namespace Tests\Feature\Analytics;

use App\Domain\Academies\Models\Academy;
use App\Domain\Analytics\Services\LearningAnalyticsService;
use App\Domain\Certificates\Models\Certificate;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Tenants\Services\TenantContext;
use App\Domain\Trainers\Models\TrainerProfile;
use App\Livewire\Admin\Analytics\AdminAnalyticsDashboard;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class LearningAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected Academy $academy;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->academy = Academy::factory()->create([
            'name' => 'SEO Analytics Academy',
            'subdomain' => 'analytics-academy',
            'is_active' => true,
        ]);

        app(TenantContext::class)->setAcademy($this->academy);

        $this->adminUser = User::factory()->create(['is_active' => true]);
        $this->adminUser->assignRole('admin');
        $this->adminUser->academies()->attach($this->academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'admin',
            'status' => 'active',
            'created_at' => now(),
        ]);
    }

    protected function createStudent(Academy $academy, bool $active = true): array
    {
        $user = User::factory()->create([
            'is_active' => $active,
            'suspended_at' => $active ? null : now(),
        ]);
        $user->assignRole('student');
        $user->academies()->attach($academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'student',
            'status' => $active ? 'active' : 'suspended',
            'created_at' => now(),
        ]);

        $profile = StudentProfile::factory()->create([
            'academy_id' => $academy->id,
            'user_id' => $user->id,
            'active' => $active,
        ]);

        return [$user, $profile];
    }

    protected function createCourseWithLessons(Academy $academy, int $lessonCount = 3): array
    {
        $course = Course::factory()->create([
            'academy_id' => $academy->id,
            'title' => 'Technical SEO Mastery',
            'status' => 'Published',
        ]);

        $module = Module::factory()->create([
            'academy_id' => $academy->id,
            'course_id' => $course->id,
            'display_order' => 1,
        ]);

        $lessons = collect();
        for ($i = 1; $i <= $lessonCount; $i++) {
            $lessons->push(Lesson::factory()->create([
                'academy_id' => $academy->id,
                'module_id' => $module->id,
                'title' => "Lesson {$i}",
                'display_order' => $i,
                'status' => 'Active',
            ]));
        }

        return [$course, $module, $lessons];
    }

    public function test_admin_dashboard_metrics_calculation(): void
    {
        // 2 active students, 1 suspended
        $this->createStudent($this->academy, true);
        $this->createStudent($this->academy, true);
        $this->createStudent($this->academy, false);

        // 1 trainer
        $trainerUser = User::factory()->create(['is_active' => true]);
        $trainerUser->assignRole('trainer');
        TrainerProfile::create([
            'id' => (string) Str::uuid(),
            'user_id' => $trainerUser->id,
            'academy_id' => $this->academy->id,
            'active' => true,
        ]);

        // 1 course with 3 lessons
        [$course, $module, $lessons] = $this->createCourseWithLessons($this->academy, 3);

        // Enrollments
        [$s1User, $s1Profile] = $this->createStudent($this->academy, true);
        [$s2User, $s2Profile] = $this->createStudent($this->academy, true);

        // 1 active, 1 completed
        $e1 = Enrollment::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'student_profile_id' => $s1Profile->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $e2 = Enrollment::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'student_profile_id' => $s2Profile->id,
            'course_id' => $course->id,
            'status' => 'completed',
            'enrolled_at' => now()->subDays(5),
            'completed_at' => now(),
        ]);

        // Certificate issued for e2
        Certificate::create([
            'id' => (string) Str::uuid(),
            'user_id' => $s2User->id,
            'course_id' => $course->id,
            'enrollment_id' => $e2->id,
            'certificate_number' => 'CERT-12345',
            'verification_code' => 'VERIF-ABC',
            'status' => Certificate::STATUS_ISSUED,
            'issued_at' => now(),
        ]);

        $service = app(LearningAnalyticsService::class);
        $metrics = $service->getAcademyOverviewMetrics($this->academy);

        $this->assertSame(5, $metrics['total_students']);
        $this->assertSame(4, $metrics['active_students']);
        $this->assertSame(1, $metrics['suspended_students']);
        $this->assertSame(1, $metrics['total_trainers']);
        $this->assertSame(1, $metrics['published_courses']);
        $this->assertSame(2, $metrics['total_enrollments']);
        $this->assertSame(1, $metrics['active_enrollments']);
        $this->assertSame(1, $metrics['completed_enrollments']);
        $this->assertSame(50.0, $metrics['course_completion_rate']);
        $this->assertSame(1, $metrics['certificates_issued']);
    }

    public function test_enrollment_trend_calculations_and_daily_bucketing(): void
    {
        [$course] = $this->createCourseWithLessons($this->academy, 2);
        [$s1User, $s1Profile] = $this->createStudent($this->academy, true);
        [$s2User, $s2Profile] = $this->createStudent($this->academy, true);

        Enrollment::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'student_profile_id' => $s1Profile->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => Carbon::now()->subDays(2),
        ]);

        Enrollment::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'student_profile_id' => $s2Profile->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => Carbon::now(),
        ]);

        $service = app(LearningAnalyticsService::class);
        $trends = $service->getEnrollmentTrends($this->academy, 7);

        $this->assertCount(7, $trends);
        $totalCount = array_sum(array_column($trends, 'count'));
        $this->assertSame(2, $totalCount);

        // Check structure
        $this->assertArrayHasKey('date', $trends[0]);
        $this->assertArrayHasKey('label', $trends[0]);
        $this->assertArrayHasKey('count', $trends[0]);
    }

    public function test_student_engagement_metrics_daily_and_monthly_active(): void
    {
        [$course, $module, $lessons] = $this->createCourseWithLessons($this->academy, 2);
        [$sUser, $sProfile] = $this->createStudent($this->academy, true);

        $enrollment = Enrollment::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'student_profile_id' => $sProfile->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        // Record lesson progress with last_accessed_at
        LessonProgress::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'enrollment_id' => $enrollment->id,
            'user_id' => $sUser->id,
            'lesson_id' => $lessons[0]->id,
            'status' => 'completed',
            'completed_at' => now(),
            'last_accessed_at' => now(),
        ]);

        $service = app(LearningAnalyticsService::class);
        $daily = $service->getDailyActiveStudents($this->academy, 5);
        $monthly = $service->getMonthlyActiveStudents($this->academy, 3);
        $completedTrends = $service->getLessonsCompletedTrends($this->academy, 5);

        $this->assertCount(5, $daily);
        $this->assertSame(1, end($daily)['count']);

        $this->assertCount(3, $monthly);
        $this->assertSame(1, end($monthly)['count']);

        $this->assertCount(5, $completedTrends);
        $this->assertSame(1, end($completedTrends)['count']);
    }

    public function test_inactive_student_detection_with_thresholds(): void
    {
        [$course] = $this->createCourseWithLessons($this->academy, 2);
        [$activeUser, $activeProfile] = $this->createStudent($this->academy, true);
        [$stalledUser, $stalledProfile] = $this->createStudent($this->academy, true);

        // Active student: studied today
        Enrollment::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'student_profile_id' => $activeProfile->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now()->subDays(20),
            'last_active_at' => now()->subHours(2),
        ]);

        // Stalled student: last active 25 days ago
        Enrollment::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'student_profile_id' => $stalledProfile->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now()->subDays(30),
            'last_active_at' => now()->subDays(25),
        ]);

        $service = app(LearningAnalyticsService::class);
        $inactive = $service->getInactiveStudents($this->academy, 14);

        $this->assertCount(1, $inactive);
        $this->assertSame($stalledUser->name, $inactive[0]['student_name']);
        $this->assertGreaterThanOrEqual(24, $inactive[0]['days_inactive']);
    }

    public function test_date_range_filtering_on_metrics(): void
    {
        [$course] = $this->createCourseWithLessons($this->academy, 2);
        [$s1User, $s1Profile] = $this->createStudent($this->academy, true);
        [$s2User, $s2Profile] = $this->createStudent($this->academy, true);

        // Enrollment 40 days ago
        Enrollment::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'student_profile_id' => $s1Profile->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => Carbon::now()->subDays(40),
        ]);

        // Enrollment 5 days ago
        Enrollment::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'student_profile_id' => $s2Profile->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => Carbon::now()->subDays(5),
        ]);

        $service = app(LearningAnalyticsService::class);

        // Window: last 10 days
        $recentMetrics = $service->getAcademyOverviewMetrics(
            $this->academy,
            Carbon::now()->subDays(10),
            Carbon::now()
        );

        $this->assertSame(1, $recentMetrics['total_enrollments']);

        // All-time
        $allMetrics = $service->getAcademyOverviewMetrics($this->academy);
        $this->assertSame(2, $allMetrics['total_enrollments']);
    }

    public function test_admin_analytics_dashboard_livewire_component_renders(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(AdminAnalyticsDashboard::class)
            ->assertStatus(200)
            ->assertSee('Learning Analytics')
            ->assertSee('Total Students')
            ->assertSee('Total Enrollments')
            ->assertSee('Completion Rate')
            ->assertSee('Enrollment Trends')
            ->call('toggleAccessibleTables')
            ->assertSet('showAccessibleTables', true);
    }
}
