<?php

namespace App\Domain\Platform\Concerns;

use App\Domain\Academies\Models\Academy;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Services\AuditLogService;
use App\Models\User;

trait RecordsAuditLog
{
    protected function recordAudit(
        Academy $academy,
        ?User $actor,
        string $action,
        string $subjectType,
        string $subjectId,
        array $properties = [],
    ): AuditLog {
        $service = app(AuditLogService::class);

        return $service->record($academy, $actor, $action, $subjectType, $subjectId, $properties);
    }
}
