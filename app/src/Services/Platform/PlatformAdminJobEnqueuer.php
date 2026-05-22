<?php
declare(strict_types=1);

namespace App\Services\Platform;

use Cake\Database\Connection;
use Cake\I18n\DateTime;
use Cake\Utility\Text;
use RuntimeException;

/**
 * Enqueues platform-admin initiated operations with idempotency and audit.
 */
class PlatformAdminJobEnqueuer
{
    /**
     * Constructor.
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly PlatformAuditService $auditService,
    ) {
    }

    /**
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $auditOptions
     * @return array<string, mixed> platform_jobs row
     */
    public function enqueue(
        string $jobType,
        ?string $tenantId,
        ?string $platformUserId,
        array $parameters,
        string $idempotencyKey,
        string $reason,
        array $auditOptions = [],
    ): array {
        $jobType = trim($jobType);
        $idempotencyKey = trim($idempotencyKey);
        if ($jobType === '' || !preg_match('/^[a-z][a-z0-9_.:-]{1,119}$/', $jobType)) {
            throw new RuntimeException('Platform job type is invalid.');
        }
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 255) {
            throw new RuntimeException('Platform job idempotency key is invalid.');
        }

        return $this->connection->transactional(function () use (
            $jobType,
            $tenantId,
            $platformUserId,
            $parameters,
            $idempotencyKey,
            $reason,
            $auditOptions,
        ): array {
            $existing = $this->findByIdempotencyKey($idempotencyKey);
            if ($existing !== null) {
                return $existing;
            }

            $now = (new DateTime('now'))->format('Y-m-d H:i:s');
            $job = [
                'id' => Text::uuid(),
                'tenant_id' => $tenantId,
                'requested_by_platform_user_id' => $platformUserId,
                'job_type' => $jobType,
                'status' => 'queued',
                'idempotency_key' => $idempotencyKey,
                'parameters' => $this->encodeParameters($parameters),
                'log_uri' => null,
                'last_error' => null,
                'created_at' => $now,
                'started_at' => null,
                'finished_at' => null,
                'modified_at' => $now,
            ];
            $this->connection->insert('platform_jobs', $job);
            $this->auditService->record(
                'platform_job.queued',
                $platformUserId,
                'platform_job',
                (string)$job['id'],
                $reason,
                [
                    'job_type' => $jobType,
                    'tenant_id' => $tenantId,
                    'idempotency_key' => $idempotencyKey,
                    'parameters' => $parameters,
                ],
                false,
                $auditOptions + ['tenantId' => $tenantId],
            );

            return $job;
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findByIdempotencyKey(string $idempotencyKey): ?array
    {
        $row = $this->connection->execute(
            'SELECT * FROM platform_jobs WHERE idempotency_key = :idempotencyKey LIMIT 1',
            ['idempotencyKey' => $idempotencyKey],
        )->fetch('assoc');

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function encodeParameters(array $parameters): string
    {
        $json = json_encode($parameters, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Unable to encode platform job parameters.');
        }

        return $json;
    }
}
