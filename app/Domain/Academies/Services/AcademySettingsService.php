<?php

namespace App\Domain\Academies\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\Setting;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Support\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AcademySettingsService
{
    public function updateProfile(Academy $academy, array $data, User $actor): Academy
    {
        $validated = Validator::make($data, [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('academies', 'slug')->ignore($academy->id),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'timezone' => ['sometimes', 'required', 'string', 'max:64', 'timezone:all'],
            'locale' => ['sometimes', 'required', 'string', 'max:16'],
        ])->validate();

        $attributes = Arr::only($validated, [
            'name',
            'slug',
            'description',
            'support_email',
            'contact_phone',
            'website_url',
            'address',
            'timezone',
            'locale',
        ]);

        return DB::transaction(function () use ($academy, $attributes, $actor): Academy {
            $academy->update($attributes);

            $this->recordAudit($academy, $actor, 'academy.profile_updated', [
                'updated_fields' => array_keys($attributes),
            ]);

            return $academy->fresh();
        });
    }

    public function updateBranding(
        Academy $academy,
        array $data,
        ?UploadedFile $logo,
        ?UploadedFile $favicon,
        User $actor,
    ): Academy {
        $validated = Validator::make([
            ...$data,
            'logo' => $logo,
            'favicon' => $favicon,
        ], [
            'primary_color' => ['sometimes', 'required', 'string', 'max:20', 'regex:/^#[0-9A-Fa-f]{3,8}$/'],
            'secondary_color' => ['sometimes', 'required', 'string', 'max:20', 'regex:/^#[0-9A-Fa-f]{3,8}$/'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'favicon' => ['nullable', 'file', 'mimes:png,ico,webp,jpg,jpeg', 'max:512'],
        ])->validate();

        $attributes = Arr::only($validated, ['primary_color', 'secondary_color']);
        $directory = "academies/{$academy->id}/branding";

        if ($logo !== null) {
            $oldLogoPath = $academy->logo_path;
            $attributes['logo_path'] = $this->storeBrandingFile($logo, $directory);

            if ($oldLogoPath !== null && $oldLogoPath !== '' && $oldLogoPath !== $attributes['logo_path']) {
                Storage::disk('public')->delete($oldLogoPath);
            }
        }

        if ($favicon !== null) {
            $oldFaviconPath = $academy->favicon_path;
            $attributes['favicon_path'] = $this->storeBrandingFile($favicon, $directory);

            if ($oldFaviconPath !== null && $oldFaviconPath !== '' && $oldFaviconPath !== $attributes['favicon_path']) {
                Storage::disk('public')->delete($oldFaviconPath);
            }
        }

        return DB::transaction(function () use ($academy, $attributes, $actor): Academy {
            $academy->update($attributes);

            $this->recordAudit($academy, $actor, 'academy.branding_updated', [
                'updated_fields' => array_keys($attributes),
            ]);

            return $academy->fresh();
        });
    }

    public function updateDomain(Academy $academy, array $data, User $actor): Academy
    {
        $normalized = [
            'subdomain' => $this->normalizeDomainValue($data['subdomain'] ?? null),
            'custom_domain' => $this->normalizeDomainValue($data['custom_domain'] ?? null),
        ];

        $validated = Validator::make($normalized, [
            'subdomain' => [
                'nullable',
                'string',
                'max:63',
                'regex:/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/',
                Rule::unique('academies', 'subdomain')->ignore($academy->id),
            ],
            'custom_domain' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/',
                Rule::unique('academies', 'custom_domain')->ignore($academy->id),
            ],
        ])->validate();

        return DB::transaction(function () use ($academy, $validated, $actor): Academy {
            $academy->update([
                'subdomain' => $validated['subdomain'] ?? null,
                'custom_domain' => $validated['custom_domain'] ?? null,
            ]);

            $this->recordAudit($academy, $actor, 'academy.domain_updated', [
                'subdomain' => $academy->subdomain,
                'custom_domain' => $academy->custom_domain,
            ]);

            return $academy->fresh();
        });
    }

    public function updateSettings(Academy $academy, array $settings, User $actor): Academy
    {
        $entries = $this->normalizeSettingsEntries($settings);

        return DB::transaction(function () use ($academy, $entries, $actor): Academy {
            $jsonSettings = $academy->settings ?? [];

            foreach ($entries as $entry) {
                Setting::updateOrCreate(
                    [
                        'academy_id' => $academy->id,
                        'group' => $entry['group'],
                        'key' => $entry['key'],
                    ],
                    [
                        'value' => $this->serializeSettingValue($entry['value'], $entry['type']),
                        'type' => $entry['type'],
                        'is_public' => $entry['is_public'],
                    ],
                );

                $jsonSettings[$entry['key']] = $entry['value'];
            }

            $academy->update(['settings' => $jsonSettings]);

            $this->recordAudit($academy, $actor, 'academy.settings_updated', [
                'keys' => array_column($entries, 'key'),
            ]);

            return $academy->fresh();
        });
    }

    public function dashboardMetrics(Academy $academy): array
    {
        $activeStudents = StudentProfile::forAcademy($academy)->active()->count();
        $publishedCourses = Course::forAcademy($academy)->active()->count();
        $totalCourses = Course::forAcademy($academy)->count();
        $activeEnrollments = Enrollment::active()
            ->whereHas('course', fn ($query) => $query->where('academy_id', $academy->id))
            ->count();
        $trainersCount = User::role('trainer')
            ->where('is_active', true)
            ->count();
        $openTickets = SupportTicket::forAcademy($academy)
            ->whereIn('status', ['Pending', 'In Progress'])
            ->count();

        $recentAuditLogs = AuditLog::query()
            ->with('user:id,name,email')
            ->where(function ($query) use ($academy): void {
                $query->where(function ($subjectQuery) use ($academy): void {
                    $subjectQuery->where('subject_type', Academy::class)
                        ->where('subject_id', $academy->id);
                })->orWhere('properties->academy_id', $academy->id);
            })
            ->latest('created_at')
            ->limit(10)
            ->get();

        return [
            'active_students' => $activeStudents,
            'published_courses' => $publishedCourses,
            'total_courses' => $totalCourses,
            'active_enrollments' => $activeEnrollments,
            'trainers_count' => $trainersCount,
            'open_tickets' => $openTickets,
            'recent_audit_logs' => $recentAuditLogs,
        ];
    }

    private function storeBrandingFile(UploadedFile $file, string $directory): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'png');
        $filename = bin2hex(random_bytes(16)).'.'.$extension;

        return $file->storeAs($directory, $filename, 'public');
    }

    private function normalizeDomainValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = Str::lower(trim((string) $value));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @return array<int, array{group: string, key: string, value: mixed, type: string, is_public: bool}>
     */
    private function normalizeSettingsEntries(array $settings): array
    {
        $entries = [];

        foreach ($settings as $key => $value) {
            if (is_array($value) && array_key_exists('key', $value)) {
                $itemKey = (string) $value['key'];
                $itemValue = $value['value'] ?? null;
                $entries[] = [
                    'group' => (string) ($value['group'] ?? 'general'),
                    'key' => $itemKey,
                    'value' => $itemValue,
                    'type' => (string) ($value['type'] ?? $this->inferSettingType($itemValue)),
                    'is_public' => (bool) ($value['is_public'] ?? false),
                ];

                continue;
            }

            $entries[] = [
                'group' => 'general',
                'key' => (string) $key,
                'value' => $value,
                'type' => $this->inferSettingType($value),
                'is_public' => false,
            ];
        }

        return $entries;
    }

    private function inferSettingType(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'float',
            is_array($value) => 'json',
            default => 'string',
        };
    }

    private function serializeSettingValue(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean', 'bool' => $value ? '1' : '0',
            'json', 'array' => json_encode($value, JSON_THROW_ON_ERROR),
            default => (string) $value,
        };
    }

    private function recordAudit(Academy $academy, User $actor, string $action, array $properties = []): void
    {
        $request = request();

        AuditLog::create([
            'user_id' => $actor->id,
            'action' => $action,
            'subject_type' => Academy::class,
            'subject_id' => $academy->id,
            'properties' => array_merge(['academy_id' => $academy->id], $properties),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
