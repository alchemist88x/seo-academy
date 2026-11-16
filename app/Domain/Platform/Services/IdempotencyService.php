<?php

namespace App\Domain\Platform\Services;

use App\Domain\Platform\Exceptions\IdempotencyConcurrentConflictException;
use App\Domain\Platform\Exceptions\IdempotencyPayloadMismatchException;
use App\Domain\Platform\Exceptions\IdempotencyStaleWorkerException;
use App\Domain\Platform\Models\IdempotencyRecord;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class IdempotencyService
{
    public const STATUS_RESERVED = 'reserved';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_RELEASED = 'released';

    public const DEFAULT_TTL_SECONDS = 60;

    /**
     * Compute a deterministic canonical SHA-256 hash of a request payload.
     */
    public static function hashPayload(mixed $payload): string
    {
        $copy = $payload;
        self::sortRecursive($copy);

        return hash('sha256', json_encode($copy, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Recursively sort array keys to guarantee canonical ordering.
     */
    protected static function sortRecursive(mixed &$data): void
    {
        if (is_array($data)) {
            // Check if associative
            if (array_keys($data) !== range(0, count($data) - 1)) {
                ksort($data);
            }
            foreach ($data as &$value) {
                if (is_array($value)) {
                    self::sortRecursive($value);
                }
            }
        }
    }

    /**
     * Atomically claim an idempotency reservation.
     *
     * @return array{is_new: bool, status: string, record_id: string, ownership_token: ?string, response_code: ?int, response_body: ?array}
     *
     * @throws IdempotencyPayloadMismatchException
     * @throws IdempotencyConcurrentConflictException
     */
    public function claimReservation(
        string $actorId,
        string $action,
        string $key,
        string $resourceType,
        string $resourceId,
        mixed $payload = [],
        int $ttlSeconds = self::DEFAULT_TTL_SECONDS
    ): array {
        $requestHash = self::hashPayload($payload);
        $ownershipToken = Str::random(64);
        $recordId = (string) Str::uuid();
        $reservedUntil = now()->addSeconds($ttlSeconds);

        try {
            // Attempt atomic insert
            DB::table('idempotency_records')->insert([
                'id' => $recordId,
                'actor_id' => $actorId,
                'action' => $action,
                'idempotency_key' => $key,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'request_hash' => $requestHash,
                'ownership_token' => $ownershipToken,
                'status' => self::STATUS_RESERVED,
                'response_code' => null,
                'response_body' => null,
                'reserved_until' => $reservedUntil,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'is_new' => true,
                'status' => self::STATUS_RESERVED,
                'record_id' => $recordId,
                'ownership_token' => $ownershipToken,
                'response_code' => null,
                'response_body' => null,
            ];
        } catch (QueryException $e) {
            // Unique key collision on (actor_id, action, idempotency_key)
            return $this->handleExistingRecord(
                $actorId,
                $action,
                $key,
                $requestHash,
                $ownershipToken,
                $ttlSeconds
            );
        }
    }

    /**
     * Handle state transition or resolution for an existing idempotency record.
     */
    protected function handleExistingRecord(
        string $actorId,
        string $action,
        string $key,
        string $requestHash,
        string $newOwnershipToken,
        int $ttlSeconds
    ): array {
        /** @var IdempotencyRecord|null $record */
        $record = IdempotencyRecord::where('actor_id', $actorId)
            ->where('action', $action)
            ->where('idempotency_key', $key)
            ->first();

        if (! $record) {
            throw new IdempotencyConcurrentConflictException(
                "Concurrent reservation conflict for action '{$action}' with key '{$key}'."
            );
        }

        // Verify request payload canonical hash
        if ($record->request_hash !== $requestHash) {
            throw new IdempotencyPayloadMismatchException(
                "Idempotency key '{$key}' was previously presented with a different request payload."
            );
        }

        // If completed, return cached response
        if ($record->status === self::STATUS_COMPLETED) {
            return [
                'is_new' => false,
                'status' => self::STATUS_COMPLETED,
                'record_id' => $record->id,
                'ownership_token' => null,
                'response_code' => $record->response_code,
                'response_body' => $record->response_body,
            ];
        }

        $now = now();

        // If reserved and not expired, this is a concurrent request in flight
        if ($record->status === self::STATUS_RESERVED && $record->reserved_until > $now) {
            throw new IdempotencyConcurrentConflictException(
                "Action '{$action}' with idempotency key '{$key}' is currently being processed by another worker."
            );
        }

        // If reserved but expired, or previously failed/released, reclaim via CAS
        $affected = DB::table('idempotency_records')
            ->where('id', $record->id)
            ->where(function ($query) use ($now): void {
                $query->where(function ($sub) use ($now): void {
                    $sub->where('status', self::STATUS_RESERVED)
                        ->where('reserved_until', '<=', $now);
                })->orWhereIn('status', [self::STATUS_FAILED, self::STATUS_RELEASED]);
            })
            ->update([
                'status' => self::STATUS_RESERVED,
                'ownership_token' => $newOwnershipToken,
                'reserved_until' => $now->copy()->addSeconds($ttlSeconds),
                'updated_at' => $now,
            ]);

        if ($affected === 1) {
            return [
                'is_new' => true,
                'status' => self::STATUS_RESERVED,
                'record_id' => $record->id,
                'ownership_token' => $newOwnershipToken,
                'response_code' => null,
                'response_body' => null,
            ];
        }

        // Another worker claimed or updated it concurrently
        throw new IdempotencyConcurrentConflictException(
            "Concurrent conflict: unable to reclaim reservation for action '{$action}' with key '{$key}'."
        );
    }

    /**
     * Atomically complete reservation with CAS ownership check.
     *
     * @throws IdempotencyStaleWorkerException
     */
    public function completeReservation(
        string $recordId,
        string $ownershipToken,
        int $responseCode,
        mixed $responseBody
    ): void {
        $now = now();
        $body = is_string($responseBody) ? $responseBody : json_encode($responseBody);

        $affected = DB::table('idempotency_records')
            ->where('id', $recordId)
            ->where('ownership_token', $ownershipToken)
            ->where('status', self::STATUS_RESERVED)
            ->update([
                'status' => self::STATUS_COMPLETED,
                'response_code' => $responseCode,
                'response_body' => $body,
                'updated_at' => $now,
            ]);

        if ($affected === 0) {
            throw new IdempotencyStaleWorkerException(
                "Failed to complete idempotency reservation '{$recordId}': ownership token expired or worker is stale."
            );
        }
    }

    /**
     * Mark reservation as failed.
     */
    public function failReservation(string $recordId, string $ownershipToken, ?string $reason = null): void
    {
        DB::table('idempotency_records')
            ->where('id', $recordId)
            ->where('ownership_token', $ownershipToken)
            ->where('status', self::STATUS_RESERVED)
            ->update([
                'status' => self::STATUS_FAILED,
                'response_body' => json_encode(['error' => $reason]),
                'updated_at' => now(),
            ]);
    }

    /**
     * Release reservation so it can be immediately retried.
     */
    public function releaseReservation(string $recordId, string $ownershipToken): void
    {
        DB::table('idempotency_records')
            ->where('id', $recordId)
            ->where('ownership_token', $ownershipToken)
            ->where('status', self::STATUS_RESERVED)
            ->update([
                'status' => self::STATUS_RELEASED,
                'updated_at' => now(),
            ]);
    }

    /**
     * High-level execution helper wrapping claim, execution, completion, and failure.
     */
    public function execute(
        string $actorId,
        string $action,
        string $key,
        string $resourceType,
        string $resourceId,
        mixed $payload,
        callable $callback,
        int $ttlSeconds = self::DEFAULT_TTL_SECONDS
    ): array {
        $claim = $this->claimReservation(
            $actorId,
            $action,
            $key,
            $resourceType,
            $resourceId,
            $payload,
            $ttlSeconds
        );

        if (! $claim['is_new']) {
            return [
                'status' => self::STATUS_COMPLETED,
                'cached' => true,
                'response_code' => $claim['response_code'],
                'response_body' => $claim['response_body'],
            ];
        }

        try {
            $result = $callback();
            $code = is_array($result) && isset($result['status_code']) ? (int) $result['status_code'] : 200;
            $data = is_array($result) && isset($result['data']) ? $result['data'] : $result;

            $this->completeReservation($claim['record_id'], $claim['ownership_token'], $code, $data);

            return [
                'status' => self::STATUS_COMPLETED,
                'cached' => false,
                'response_code' => $code,
                'response_body' => $data,
            ];
        } catch (Throwable $e) {
            $this->failReservation($claim['record_id'], $claim['ownership_token'], $e->getMessage());
            throw $e;
        }
    }
}
