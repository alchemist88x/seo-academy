<?php

namespace App\Providers;

use App\Domain\Academies\Models\Academy;
use App\Domain\Academies\Models\AcademyUser;
use App\Domain\Academies\Policies\AcademyPolicy;
use App\Domain\Academies\Policies\AcademyUserPolicy;
use App\Domain\AI\Contracts\AiProviderInterface;
use App\Domain\AI\Services\Providers\NullAiProvider;
use App\Domain\AI\Services\Providers\OpenAiCompatibleProvider;
use App\Domain\Assignments\Models\Assignment;
use App\Domain\Assignments\Policies\AssignmentPolicy;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\CourseCategory;
use App\Domain\Courses\Models\Module;
use App\Domain\Courses\Policies\CourseCategoryPolicy;
use App\Domain\Courses\Policies\CoursePolicy;
use App\Domain\Courses\Policies\ModulePolicy;
use App\Domain\Identity\Models\Invitation;
use App\Domain\Identity\Policies\InvitationPolicy;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Lessons\Policies\LessonPolicy;
use App\Domain\Questions\Models\QuestionBank;
use App\Domain\Questions\Policies\QuestionBankPolicy;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Students\Policies\EnrollmentPolicy;
use App\Domain\Students\Policies\StudentProfilePolicy;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Support\Policies\SupportTicketPolicy;
use App\Domain\Tenants\Services\TenantContext;
use App\Domain\Trainers\Models\TrainerProfile;
use App\Domain\Trainers\Policies\TrainerProfilePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);

        $this->app->bind(AiProviderInterface::class, function () {
            if (! config('lms.ai_enabled', false)) {
                return new NullAiProvider;
            }

            $driver = (string) config('lms.ai_driver', 'null');

            if ($driver === 'null' || $driver === '') {
                return new NullAiProvider;
            }

            return new OpenAiCompatibleProvider(
                driver: $driver,
                endpoint: (string) config('lms.ai_endpoint', ''),
                model: (string) config('lms.ai_model', 'gpt-4.1-mini'),
                apiKey: (string) config('lms.ai_api_key', ''),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Academy::class, AcademyPolicy::class);
        Gate::policy(AcademyUser::class, AcademyUserPolicy::class);
        Gate::policy(Course::class, CoursePolicy::class);
        Gate::policy(CourseCategory::class, CourseCategoryPolicy::class);
        Gate::policy(Enrollment::class, EnrollmentPolicy::class);
        Gate::policy(Invitation::class, InvitationPolicy::class);
        Gate::policy(Lesson::class, LessonPolicy::class);
        Gate::policy(Module::class, ModulePolicy::class);
        Gate::policy(StudentProfile::class, StudentProfilePolicy::class);
        Gate::policy(SupportTicket::class, SupportTicketPolicy::class);
        Gate::policy(TrainerProfile::class, TrainerProfilePolicy::class);
        Gate::policy(QuestionBank::class, QuestionBankPolicy::class);
        Gate::policy(Assignment::class, AssignmentPolicy::class);
    }
}
