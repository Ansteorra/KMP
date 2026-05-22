<?php
declare(strict_types=1);

namespace App\Services\Platform;

use Cake\Database\Connection;
use Cake\Database\Driver\Postgres;
use Cake\Datasource\ConnectionManager;
use Cake\I18n\DateTime;
use Cake\Utility\Text;
use DateTimeInterface;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class PlatformQueueService
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_DEAD_LETTER = 'dead_letter';

    private const DEFAULT_LEASE_SECONDS = 300;

    /**
     * Constructor.
     *
     * @param \Cake\Database\Connection|null $connection Platform connection override
     */
    public function __construct(private readonly ?Connection $connection = null)
    {
    }

    /**
     * Enqueue a tenant-scoped platform queue message.
     *
     * @param array<string, mixed> $payload Opaque payload; callers must avoid PII
     * @param array<string, mixed> $options Queue options
     * @return array<string, mixed>
     */
    public function enqueue(string $tenantId, string $jobClass, array $payload, array $options = []): array
    {
        $jobClass = trim($jobClass);
        if ($tenantId === '' || $jobClass === '') {
            throw new InvalidArgumentException('Queue messages require tenant_id and job_class.');
        }

        $idempotencyKey = isset($options['idempotencyKey']) ? (string)$options['idempotencyKey'] : null;
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $existing = $this->findByIdempotencyKey($idempotencyKey);
            if ($existing !== null) {
                return $existing;
            }
        } else {
            $idempotencyKey = null;
        }

        $now = $this->formatTimestamp($options['now'] ?? new DateTime('now'));
        $message = [
            'id' => Text::uuid(),
            'tenant_id' => $tenantId,
            'job_class' => $jobClass,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'status' => self::STATUS_QUEUED,
            'priority' => (int)($options['priority'] ?? 100),
            'not_before' => $this->nullableTimestamp($options['notBefore'] ?? null),
            'attempts' => 0,
            'max_attempts' => max(1, (int)($options['maxAttempts'] ?? 3)),
            'locked_by' => null,
            'locked_until' => null,
            'started_at' => null,
            'finished_at' => null,
            'failed_at' => null,
            'last_error' => null,
            'producer_schema' => $this->nullableString($options['producerSchema'] ?? null),
            'min_consumer_schema' => $this->nullableString($options['minConsumerSchema'] ?? null),
            'idempotency_key' => $idempotencyKey,
            'created_at' => $now,
            'modified_at' => $now,
        ];

        try {
            $this->connection()->insert('queue_messages', $message);
        } catch (Throwable $e) {
            if ($idempotencyKey !== null) {
                $existing = $this->findByIdempotencyKey($idempotencyKey);
                if ($existing !== null) {
                    return $existing;
                }
            }

            throw $e;
        }

        return $this->normalizeMessage($message);
    }

    /**
     * Atomically claim messages using PostgreSQL row locks and tenant caps.
     *
     * @return list<array<string, mixed>>
     */
    public function claim(
        int $limit,
        string $workerId,
        ?string $consumerSchema = null,
        int $leaseSeconds = self::DEFAULT_LEASE_SECONDS,
        DateTimeInterface|string|null $now = null,
    ): array {
        if (!$this->connection()->getDriver() instanceof Postgres) {
            throw new RuntimeException('Platform queue claim requires PostgreSQL FOR UPDATE SKIP LOCKED support.');
        }
        if ($limit < 1 || trim($workerId) === '') {
            throw new InvalidArgumentException('Claim requires a positive limit and non-empty worker id.');
        }

        $nowValue = $this->formatTimestamp($now ?? new DateTime('now'));
        $leaseUntil = $this->formatTimestamp(strtotime($nowValue . ' UTC') + $leaseSeconds);
        $rows = $this->connection()->execute($this->claimSql($limit), [
            'now' => $nowValue,
            'workerId' => $workerId,
            'leaseUntil' => $leaseUntil,
            'consumerSchema' => $consumerSchema,
        ])->fetchAll('assoc');

        return array_map(fn(array $row): array => $this->normalizeMessage($row), $rows);
    }

    /**
     * Preview claimable rows without taking locks. Intended for non-Postgres tests/diagnostics only.
     *
     * @return list<array<string, mixed>>
     */
    public function previewClaimable(
        int $limit,
        ?string $consumerSchema = null,
        DateTimeInterface|string|null $now = null,
    ): array {
        if ($limit < 1) {
            throw new InvalidArgumentException('Preview requires a positive limit.');
        }

        $rows = $this->connection()->execute($this->previewClaimableSql($limit), [
            'now' => $this->formatTimestamp($now ?? new DateTime('now')),
            'consumerSchema' => $consumerSchema,
        ])->fetchAll('assoc');

        return array_map(fn(array $row): array => $this->normalizeMessage($row), $rows);
    }

    /**
     * Mark a claimed message as completed.
     *
     * @param string $messageId Queue message UUID
     * @param \DateTimeInterface|string|null $now Completion timestamp
     * @return void
     */
    public function finish(string $messageId, DateTimeInterface|string|null $now = null): void
    {
        $nowValue = $this->formatTimestamp($now ?? new DateTime('now'));
        $this->connection()->update('queue_messages', [
            'status' => self::STATUS_COMPLETED,
            'locked_by' => null,
            'locked_until' => null,
            'finished_at' => $nowValue,
            'modified_at' => $nowValue,
        ], ['id' => $messageId]);
    }

    /**
     * Release a claimed message back to the queue.
     *
     * @param string $messageId Queue message UUID
     * @param int $delaySeconds Seconds to delay the next attempt
     * @param \Throwable|string|null $error Optional scrubbed error source
     * @param \DateTimeInterface|string|null $now Release timestamp
     * @return void
     */
    public function release(
        string $messageId,
        int $delaySeconds = 0,
        string|Throwable|null $error = null,
        DateTimeInterface|string|null $now = null,
    ): void {
        $nowValue = $this->formatTimestamp($now ?? new DateTime('now'));
        $notBefore = $delaySeconds > 0 ? $this->formatTimestamp(strtotime($nowValue . ' UTC') + $delaySeconds) : null;
        $update = [
            'status' => self::STATUS_QUEUED,
            'not_before' => $notBefore,
            'locked_by' => null,
            'locked_until' => null,
            'modified_at' => $nowValue,
        ];
        if ($error !== null) {
            $update['last_error'] = self::scrubError($this->errorMessage($error));
        }

        $this->connection()->update('queue_messages', $update, ['id' => $messageId]);
    }

    /**
     * Fail a message, releasing or dead-lettering based on retry budget.
     *
     * @param string $messageId Queue message UUID
     * @param \Throwable|string $error Error source
     * @param int $retryDelaySeconds Seconds to delay retry when attempts remain
     * @param \DateTimeInterface|string|null $now Failure timestamp
     * @return void
     */
    public function fail(
        string $messageId,
        Throwable|string $error,
        int $retryDelaySeconds = 0,
        DateTimeInterface|string|null $now = null,
    ): void {
        $message = $this->find($messageId);
        if ($message === null) {
            throw new RuntimeException('Queue message was not found.');
        }

        if ((int)$message['attempts'] >= (int)$message['max_attempts']) {
            $this->deadLetter($message, $this->errorMessage($error), $now);

            return;
        }

        $this->release($messageId, $retryDelaySeconds, $error, $now);
    }

    /**
     * Copy a message to queue_dead_letter and mark the original as dead-lettered.
     *
     * @param array|string $messageOrId Message row or UUID
     * @param \Throwable|string $reason Failure reason
     * @param \DateTimeInterface|string|null $now Dead-letter timestamp
     * @return void
     */
    public function deadLetter(
        array|string $messageOrId,
        Throwable|string $reason,
        DateTimeInterface|string|null $now = null,
    ): void {
        $message = is_array($messageOrId) ? $messageOrId : $this->find($messageOrId);
        if (!is_array($message)) {
            throw new RuntimeException('Queue message was not found.');
        }

        $error = self::scrubError($this->errorMessage($reason));
        $nowValue = $this->formatTimestamp($now ?? new DateTime('now'));
        $this->connection()->transactional(function () use ($message, $error, $nowValue): void {
            $this->connection()->insert('queue_dead_letter', [
                'id' => Text::uuid(),
                'original_message_id' => $message['id'],
                'tenant_id' => $message['tenant_id'],
                'job_class' => $message['job_class'],
                'payload' => $this->encodePayload($message['payload']),
                'status' => self::STATUS_DEAD_LETTER,
                'priority' => (int)$message['priority'],
                'not_before' => $message['not_before'],
                'attempts' => (int)$message['attempts'],
                'max_attempts' => (int)$message['max_attempts'],
                'locked_by' => null,
                'locked_until' => null,
                'started_at' => $message['started_at'],
                'finished_at' => $message['finished_at'],
                'failed_at' => $nowValue,
                'last_error' => $error,
                'producer_schema' => $message['producer_schema'],
                'min_consumer_schema' => $message['min_consumer_schema'],
                'idempotency_key' => $message['idempotency_key'],
                'failed_reason' => $error,
                'dead_lettered_at' => $nowValue,
                'created_at' => $message['created_at'],
                'modified_at' => $nowValue,
            ]);
            $this->connection()->update('queue_messages', [
                'status' => self::STATUS_DEAD_LETTER,
                'locked_by' => null,
                'locked_until' => null,
                'failed_at' => $nowValue,
                'last_error' => $error,
                'modified_at' => $nowValue,
            ], ['id' => $message['id']]);
        });
    }

    /**
     * Scrub sensitive values from stored queue errors.
     *
     * @param string $message Error message
     * @return string
     */
    public static function scrubError(string $message): string
    {
        $message = (string)preg_replace(
            '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i',
            '[redacted-email]',
            $message,
        );
        $message = (string)preg_replace('/Bearer\s+[A-Za-z0-9._\-]+/i', 'Bearer [redacted-token]', $message);

        return mb_substr($message, 0, 2000);
    }

    /**
     * Build PostgreSQL claim SQL using tenant locks and SKIP LOCKED.
     *
     * @param int $limit Maximum rows to claim
     * @return string
     */
    public static function claimSql(int $limit): string
    {
        return self::claimableCteSql($limit, true) . "\n" .
            'UPDATE queue_messages qm
SET status = \'running\',
    locked_by = :workerId,
    locked_until = :leaseUntil,
    attempts = qm.attempts + 1,
    started_at = COALESCE(qm.started_at, :now),
    modified_at = :now
FROM claimable
WHERE qm.id = claimable.id
RETURNING qm.*';
    }

    /**
     * Build non-locking claimability SQL for diagnostics and tests.
     *
     * @param int $limit Maximum rows to preview
     * @return string
     */
    public static function previewClaimableSql(int $limit): string
    {
        return self::claimableCteSql($limit, false) . "\n" .
            'SELECT qm.*
FROM queue_messages qm
INNER JOIN claimable ON claimable.id = qm.id
ORDER BY claimable.tenant_rank ASC, qm.priority ASC, qm.created_at ASC, qm.id ASC';
    }

    /**
     * Build the shared claimability CTE.
     *
     * @param int $limit Maximum rows
     * @param bool $locking Whether to include PostgreSQL row locks
     * @return string
     */
    private static function claimableCteSql(int $limit, bool $locking): string
    {
        $limit = max(1, $limit);
        $tenantLock = $locking ? "\n    FOR UPDATE OF t SKIP LOCKED" : '';
        $messageLock = $locking ? "\n    FOR UPDATE OF qm SKIP LOCKED" : '';

        return sprintf('WITH lockable_tenants AS (
    SELECT t.id, COALESCE(t.queue_concurrency_limit, 0) AS queue_concurrency_limit
    FROM tenants t
    WHERE t.status = \'active\'
      AND COALESCE(t.queue_concurrency_limit, 0) > 0%s
), tenant_slots AS (
    SELECT
        lt.id AS tenant_id,
        lt.queue_concurrency_limit - COUNT(running.id) AS available_slots
    FROM lockable_tenants lt
    LEFT JOIN queue_messages running
        ON running.tenant_id = lt.id
       AND running.status = \'running\'
       AND running.locked_until > :now
    GROUP BY lt.id, lt.queue_concurrency_limit
    HAVING lt.queue_concurrency_limit - COUNT(running.id) > 0
), ranked_messages AS (
    SELECT
        qm.id,
        qm.priority,
        qm.created_at,
        ROW_NUMBER() OVER (
            PARTITION BY qm.tenant_id
            ORDER BY qm.priority ASC, qm.created_at ASC, qm.id ASC
        ) AS tenant_rank,
        ts.available_slots
    FROM queue_messages qm
    INNER JOIN tenant_slots ts ON ts.tenant_id = qm.tenant_id
    WHERE qm.status = \'queued\'
      AND (qm.not_before IS NULL OR qm.not_before <= :now)
      AND (qm.locked_until IS NULL OR qm.locked_until <= :now)
      AND qm.attempts < qm.max_attempts
      AND (
          qm.min_consumer_schema IS NULL
          OR (CAST(:consumerSchema AS varchar) IS NOT NULL
              AND qm.min_consumer_schema <= CAST(:consumerSchema AS varchar))
      )
), claimable AS (
    SELECT qm.id, rm.tenant_rank
    FROM queue_messages qm
    INNER JOIN ranked_messages rm ON rm.id = qm.id
    WHERE rm.tenant_rank <= rm.available_slots
    ORDER BY rm.tenant_rank ASC, rm.priority ASC, rm.created_at ASC, qm.id ASC
    LIMIT %d%s
)', $tenantLock, $limit, $messageLock);
    }

    /**
     * Find a queue message.
     *
     * @param string $messageId Queue message UUID
     * @return array<string, mixed>|null
     */
    private function find(string $messageId): ?array
    {
        $row = $this->connection()->execute(
            'SELECT * FROM queue_messages WHERE id = :id LIMIT 1',
            ['id' => $messageId],
        )->fetch('assoc');

        return is_array($row) ? $this->normalizeMessage($row) : null;
    }

    /**
     * Find a queue message by idempotency key.
     *
     * @param string $idempotencyKey Idempotency key
     * @return array<string, mixed>|null
     */
    private function findByIdempotencyKey(string $idempotencyKey): ?array
    {
        $row = $this->connection()->execute(
            'SELECT * FROM queue_messages WHERE idempotency_key = :idempotencyKey LIMIT 1',
            ['idempotencyKey' => $idempotencyKey],
        )->fetch('assoc');

        return is_array($row) ? $this->normalizeMessage($row) : null;
    }

    /**
     * Normalize database message rows for callers.
     *
     * @param array<string, mixed> $row Raw row
     * @return array<string, mixed>
     */
    private function normalizeMessage(array $row): array
    {
        $row['payload'] = $this->decodePayload($row['payload'] ?? []);

        return $row;
    }

    /**
     * Decode JSON payload values from supported drivers.
     *
     * @param mixed $payload Raw payload
     * @return array<string, mixed>
     */
    private function decodePayload(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }
        if ($payload === null || $payload === '') {
            return [];
        }
        $decoded = json_decode((string)$payload, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Encode a normalized payload for storage.
     *
     * @param mixed $payload Raw payload
     * @return string
     */
    private function encodePayload(mixed $payload): string
    {
        return json_encode($this->decodePayload($payload), JSON_THROW_ON_ERROR);
    }

    /**
     * Format timestamps consistently for SQL comparisons.
     *
     * @param \DateTimeInterface|string|int $value Timestamp value
     * @return string
     */
    private function formatTimestamp(DateTimeInterface|string|int $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_int($value)) {
            return gmdate('Y-m-d H:i:s', $value);
        }

        return (new DateTime((string)$value))->format('Y-m-d H:i:s');
    }

    /**
     * Normalize nullable timestamp input.
     *
     * @param mixed $value Timestamp value
     * @return string|null
     */
    private function nullableTimestamp(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->formatTimestamp($value);
    }

    /**
     * Normalize nullable string input.
     *
     * @param mixed $value String value
     * @return string|null
     */
    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string)$value;
    }

    /**
     * Extract a message from a string or throwable.
     *
     * @param \Throwable|string $error Error source
     * @return string
     */
    private function errorMessage(Throwable|string $error): string
    {
        return $error instanceof Throwable ? $error->getMessage() : $error;
    }

    /**
     * Return the platform connection.
     *
     * @return \Cake\Database\Connection
     */
    private function connection(): Connection
    {
        return $this->connection ?? ConnectionManager::get('platform');
    }
}
