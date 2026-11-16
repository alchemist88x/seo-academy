<?php

namespace App\Livewire\Profile;

use App\Domain\Courses\Models\Course;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Tenants\Services\TenantContext;
use App\Domain\Trainers\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('My Profile')]
class ProfileManager extends Component
{
    // Personal Info
    public string $name = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public string $phone = '';

    public string $role = 'student';

    // Password Update
    public string $current_password = '';

    public string $new_password = '';

    public string $new_password_confirmation = '';

    // Trainer specific
    public string $specialty = '';

    public string $bio = '';

    // Student specific (read-only)
    public ?string $student_code = null;

    public ?string $batch = null;

    public ?string $level = null;

    public ?string $enrolled_at = null;

    public function mount(TenantContext $tenantContext): void
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user) {
            redirect()->route('login');

            return;
        }

        $this->fillFromUser($user, $tenantContext);
    }

    public function updatedName(string $value): void
    {
        $parts = explode(' ', trim($value), 2);
        $this->first_name = $parts[0] ?? '';
        $this->last_name = $parts[1] ?? '';
    }

    public function updatedFirstName(): void
    {
        $this->name = trim("{$this->first_name} {$this->last_name}");
    }

    public function updatedLastName(): void
    {
        $this->name = trim("{$this->first_name} {$this->last_name}");
    }

    public function savePersonalInfo(TenantContext $tenantContext): void
    {
        /** @var User $user */
        $user = Auth::user();

        $fullName = $this->name !== '' ? $this->name : trim("{$this->first_name} {$this->last_name}");

        $validated = $this->validate([
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        if ($fullName === '') {
            $this->addError('first_name', 'The first name or full name is required.');

            return;
        }

        $user->update([
            'name' => $fullName,
            'email' => Str::lower(trim($validated['email'])),
            'phone' => $validated['phone'] !== '' ? $validated['phone'] : null,
        ]);

        if ($user->hasRole('trainer')) {
            $this->validate([
                'specialty' => ['nullable', 'string', 'max:255'],
                'bio' => ['nullable', 'string', 'max:5000'],
            ]);

            $academy = $tenantContext->getAcademy();
            $trainerProfile = TrainerProfile::query()
                ->where('user_id', $user->id)
                ->when($academy, fn ($q) => $q->where('academy_id', $academy->id))
                ->first();

            if ($trainerProfile) {
                $trainerProfile->update([
                    'specialty' => $this->specialty !== '' ? $this->specialty : null,
                    'bio' => $this->bio !== '' ? $this->bio : null,
                ]);
            }
        }

        $this->dispatch('toast', type: 'success', message: 'Profile details saved successfully.');
    }

    public function updatePassword(): void
    {
        /** @var User $user */
        $user = Auth::user();

        $this->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($this->current_password, $user->password)) {
            $this->addError('current_password', 'The provided current password does not match your active account password.');

            return;
        }

        $user->update([
            'password' => Hash::make($this->new_password),
        ]);

        $this->reset(['current_password', 'new_password', 'new_password_confirmation']);
        $this->dispatch('toast', type: 'success', message: 'Password changed securely.');
    }

    public function render(TenantContext $tenantContext): View
    {
        /** @var User $user */
        $user = Auth::user();
        $academy = $tenantContext->getAcademy();

        $studentProfile = null;
        $trainerCourses = collect();

        if ($user->hasRole('student') && $academy) {
            $studentProfile = StudentProfile::query()
                ->forAcademy($academy)
                ->where('user_id', $user->id)
                ->with(['enrollments.course', 'user.certificates'])
                ->first();
        } elseif ($user->hasRole('trainer') && $academy) {
            $trainerCourses = Course::query()
                ->where('academy_id', $academy->id)
                ->where(function ($q) use ($user) {
                    $q->where('instructor_id', $user->id)
                        ->orWhere('primary_trainer_id', $user->id);
                })
                ->get();
        }

        return view('livewire.profile.profile-manager', [
            'user' => $user,
            'academy' => $academy,
            'studentProfile' => $studentProfile,
            'trainerCourses' => $trainerCourses,
        ]);
    }

    private function fillFromUser(User $user, TenantContext $tenantContext): void
    {
        $this->name = (string) $user->name;
        $parts = explode(' ', $this->name, 2);
        $this->first_name = $parts[0] ?? '';
        $this->last_name = $parts[1] ?? '';
        $this->email = (string) $user->email;
        $this->phone = (string) ($user->phone ?? '');
        $this->role = $user->roles->first()?->name ?? 'student';

        $academy = $tenantContext->getAcademy();

        if ($user->hasRole('trainer')) {
            $trainerProfile = TrainerProfile::query()
                ->where('user_id', $user->id)
                ->when($academy, fn ($q) => $q->where('academy_id', $academy->id))
                ->first();

            $this->specialty = (string) ($trainerProfile?->specialty ?? '');
            $this->bio = (string) ($trainerProfile?->bio ?? '');
        }

        if ($user->hasRole('student')) {
            $studentProfile = StudentProfile::query()
                ->where('user_id', $user->id)
                ->when($academy, fn ($q) => $q->where('academy_id', $academy->id))
                ->first();

            $this->student_code = $studentProfile?->student_code;
            $this->batch = $studentProfile?->batch;
            $this->level = $studentProfile?->level;
            $this->enrolled_at = $studentProfile?->enrolled_at?->format('M d, Y');
        }
    }
}
