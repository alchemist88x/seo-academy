<?php

namespace App\Domain\Platform\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\Platform\Models\AuditLog;
use App\Models\User;

class AuditLogService
{
    /**
     * Keys that must never be recorded in audit log properties.
     *
     * @var array<int, string>
     */
    public const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'new_password',
        'current_password',
        'secret',
        'token',
        'api_key',
        'smtp_password',
        'mail_password',
        'app_key',
        'private_key',
        'access_token',
        'remember_token',
        'authorization',
        'session_id',
    ];

    /**
     * Record an audit log entry with actor snapshot and sensitive field exclusions.
     */
    public function record(
        Academy $academy,
        ?User $actor,
        string $action,
        string $subjectType,
        string $subjectId,
        array $properties = [],
    ): AuditLog {
        $request = request();

        $safeProperties = $this->sanitizeProperties($properties);
        $safeProperties['academy_id'] = (string) $academy->getKey();

        if ($actor) {
            $safeProperties['_actor'] = [
                'name' => $actor->name,
                'email' => $actor->email,
                'role' => $actor->roles()->pluck('name')->first() ?? 'user',
            ];
        }

        return AuditLog::create([
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'properties' => $safeProperties,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? substr((string) $request->userAgent(), 0, 500) : null,
            'created_at' => now(),
        ]);
    }

    /**
     * Recursively sanitize properties array, redacting sensitive keys.
     */
    public function sanitizeProperties(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string) $key);

            $isSensitive = false;
            foreach (self::SENSITIVE_KEYS as $sensitive) {
                if (str_contains($lowerKey, $sensitive)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeProperties($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
