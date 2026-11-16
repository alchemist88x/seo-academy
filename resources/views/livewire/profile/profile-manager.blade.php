<div class="max-w-4xl space-y-6">
    <x-page-header
        title="My Profile &amp; Account Settings"
        subtitle="Manage your personal information, contact details, and account security."
    />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Profile Summary Card (Left Column) --}}
        <div class="lg:col-span-1 space-y-6">
            <x-card>
                <div class="text-center pb-4 border-b border-border-default">
                    @php
                        $initials = collect(explode(' ', $user->name))
                            ->filter()
                            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
                            ->take(2)
                            ->implode('');
                        $roleColor = match ($role) {
                            'admin' => 'purple',
                            'trainer' => 'blue',
                            'support_staff' => 'yellow',
                            default => 'green',
                        };
                    @endphp
                    <div class="inline-flex h-20 w-20 rounded-full bg-primary-600 text-white text-xl font-bold items-center justify-center shadow-md mb-3">
                        {{ $initials ?: 'U' }}
                    </div>
                    <h3 class="text-base font-semibold text-text-primary">{{ $user->name }}</h3>
                    <p class="text-xs text-text-muted mt-0.5">{{ $user->email }}</p>
                    <div class="mt-2.5">
                        <x-badge :color="$roleColor">
                            {{ ucfirst(str_replace('_', ' ', $role)) }}
                        </x-badge>
                    </div>
                </div>

                <dl class="pt-4 space-y-3 text-xs">
                    <div>
                        <dt class="text-text-muted font-medium">Academy</dt>
                        <dd class="text-text-primary font-semibold mt-0.5">{{ $academy?->name ?? 'SEO Academy' }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted font-medium">Account Status</dt>
                        <dd class="text-text-primary mt-0.5">
                            <span class="inline-flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-medium">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Active
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-text-muted font-medium">Member Since</dt>
                        <dd class="text-text-primary mt-0.5">{{ $user->created_at?->format('M d, Y') ?? '—' }}</dd>
                    </div>

                    @if ($studentProfile)
                        <div class="pt-3 border-t border-border-default">
                            <dt class="text-text-muted font-medium">Student Code</dt>
                            <dd class="font-mono text-text-primary mt-0.5 font-semibold">{{ $student_code }}</dd>
                        </div>
                        @if ($batch)
                            <div>
                                <dt class="text-text-muted font-medium">Batch</dt>
                                <dd class="text-text-primary mt-0.5">{{ $batch }}</dd>
                            </div>
                        @endif
                        @if ($level)
                            <div>
                                <dt class="text-text-muted font-medium">Current Level</dt>
                                <dd class="text-text-primary mt-0.5">{{ $level }}</dd>
                            </div>
                        @endif
                    @endif
                </dl>
            </x-card>
        </div>

        {{-- Form Column (Right 2 Columns) --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Personal Information Card --}}
            <x-card title="Personal Information" description="Update your display name, contact email, and phone number.">
                <form wire:submit="savePersonalInfo" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-input
                            name="first_name"
                            label="First Name"
                            wire:model="first_name"
                            required
                        />

                        <x-input
                            name="last_name"
                            label="Last Name"
                            wire:model="last_name"
                        />

                        <x-input
                            name="email"
                            label="Email Address"
                            type="email"
                            wire:model="email"
                            required
                        />

                        <x-input
                            name="phone"
                            label="Phone Number"
                            wire:model="phone"
                            placeholder="+1 (555) 000-0000"
                        />
                    </div>

                    @if ($role === 'trainer')
                        <div class="pt-3 border-t border-border-default space-y-4">
                            <h4 class="text-xs font-semibold uppercase tracking-wider text-text-muted">Instructor Details</h4>

                            <x-input
                                name="specialty"
                                label="Specialty / Areas of Expertise"
                                wire:model="specialty"
                                placeholder="e.g. Technical SEO, Keyword Research, Semantic SEO"
                            />

                            <div>
                                <label for="trainer-bio" class="form-label">Professional Bio</label>
                                <textarea
                                    id="trainer-bio"
                                    rows="3"
                                    wire:model="bio"
                                    class="form-input @error('bio') error @enderror"
                                    placeholder="Brief background and expertise..."
                                ></textarea>
                                @error('bio')
                                    <p class="form-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    @endif

                    <div class="pt-3 border-t border-border-default flex justify-end">
                        <x-button variant="primary" type="submit" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="savePersonalInfo">Save Personal Info</span>
                            <span wire:loading wire:target="savePersonalInfo">Saving...</span>
                        </x-button>
                    </div>
                </form>
            </x-card>

            {{-- Change Password Card --}}
            <x-card title="Change Password" description="Ensure your account is using a long, random password to stay secure.">
                <form wire:submit="updatePassword" class="space-y-4">
                    <x-input
                        name="current_password"
                        label="Current Password"
                        type="password"
                        wire:model="current_password"
                        required
                    />

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-input
                            name="new_password"
                            label="New Password"
                            type="password"
                            wire:model="new_password"
                            hint="Minimum 8 characters."
                            required
                        />

                        <x-input
                            name="new_password_confirmation"
                            label="Confirm New Password"
                            type="password"
                            wire:model="new_password_confirmation"
                            required
                        />
                    </div>

                    <div class="pt-3 border-t border-border-default flex justify-end">
                        <x-button variant="secondary" type="submit" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="updatePassword">Update Password</span>
                            <span wire:loading wire:target="updatePassword">Updating...</span>
                        </x-button>
                    </div>
                </form>
            </x-card>

            {{-- Student Academic Records (Read Only for Students) --}}
            @if ($studentProfile && $studentProfile->enrollments->isNotEmpty())
                <x-card title="Enrolled Courses" description="Active course enrollments in {{ $academy?->name ?? 'SEO Academy' }}.">
                    <div class="divide-y divide-border-default text-xs">
                        @foreach ($studentProfile->enrollments as $enrollment)
                            <div class="py-2.5 flex items-center justify-between">
                                <div>
                                    <p class="font-medium text-text-primary">{{ $enrollment->course?->title ?? 'Course #' . $enrollment->course_id }}</p>
                                    <p class="text-text-muted">Enrolled on {{ $enrollment->enrolled_at?->format('M d, Y') ?? '—' }}</p>
                                </div>
                                <x-badge :color="match($enrollment->status) { 'active' => 'green', 'completed' => 'blue', default => 'gray' }">
                                    {{ ucfirst($enrollment->status) }}
                                </x-badge>
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endif

            {{-- Trainer Assigned Courses (Read Only for Trainers) --}}
            @if ($role === 'trainer' && $trainerCourses->isNotEmpty())
                <x-card title="Assigned Courses" description="Courses you instruct or lead at {{ $academy?->name ?? 'SEO Academy' }}.">
                    <div class="divide-y divide-border-default text-xs">
                        @foreach ($trainerCourses as $course)
                            <div class="py-2.5 flex items-center justify-between">
                                <div>
                                    <p class="font-medium text-text-primary">{{ $course->title }}</p>
                                    <p class="text-text-muted">{{ $course->category?->name ?? 'Curriculum' }}</p>
                                </div>
                                <x-badge color="blue">Instructor</x-badge>
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endif
        </div>
    </div>
</div>
