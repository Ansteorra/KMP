<?php
declare(strict_types=1);

namespace App\Services\Platform;

use App\Services\Platform\Audit\WormAuditSinkFactory;
use App\Services\Platform\Audit\WormAuditSinkInterface;
use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use DateTimeInterface;
use RuntimeException;
use Throwable;

class PlatformAuditService
{
    /**
     * Constructor.
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly ?WormAuditSinkInterface $wormSink = null,
        private readonly ?bool $wormFailClosed = null,
    ) {
    }

    /**
     * Persist a platform audit event and mirror it to the configured immutable sink.
     *
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $options
     * @return array<string, mixed> Persisted audit event payload
     */
    public function record(
        string $action,
        ?string $platformUserId = null,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?string $reason = null,
        array $metadata = [],
        bool $withTransaction = true,
        array $options = [],
    ): array {
        $insert = function () use (
            $action,
            $platformUserId,
            $subjectType,
            $subjectId,
            $reason,
            $metadata,
            $options,
        ): array {
            $previousHash = $this->lastAuditHash();
            $createdAt = $this->formatTime($options['createdAt'] ?? new DateTime('now'));
            $event = [
                'tenant_id' => $options['tenantId'] ?? null,
                'platform_user_id' => $platformUserId,
                'action' => $action,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'reason' => $reason,
                'metadata' => $metadata,
                'ip_address' => $options['ipAddress'] ?? 'cli',
                'user_agent' => $options['userAgent'] ?? 'bin/cake platform',
                'previous_hash' => $previousHash,
                'created_at' => $createdAt,
            ];
            $event['event_hash'] = hash('sha256', $this->canonicalEventJson($event));

            $this->connection->insert('audit_events', [
                'tenant_id' => $event['tenant_id'],
                'platform_user_id' => $event['platform_user_id'],
                'action' => $event['action'],
                'subject_type' => $event['subject_type'],
                'subject_id' => $event['subject_id'],
                'reason' => $event['reason'],
                'metadata' => $this->encodeMetadata($metadata),
                'ip_address' => $event['ip_address'],
                'user_agent' => $event['user_agent'],
                'previous_hash' => $event['previous_hash'],
                'event_hash' => $event['event_hash'],
                'created_at' => $event['created_at'],
            ]);

            $this->mirror($event);

            return $event;
        };

        if (!$withTransaction) {
            return $insert();
        }

        return $this->connection->transactional($insert);
    }

    /**
     * Return the latest audit hash for platform database hash chaining.
     */
    private function lastAuditHash(): ?string
    {
        $row = $this->connection
            ->execute('SELECT event_hash FROM audit_events ORDER BY created_at DESC, id DESC LIMIT 1')
            ->fetch('assoc');

        return is_array($row) && !empty($row['event_hash']) ? (string)$row['event_hash'] : null;
    }

    /**
     * Mirror an event to the configured WORM sink.
     *
     * @param array<string, mixed> $event
     * @return void
     */
    private function mirror(array $event): void
    {
        try {
            ($this->wormSink ?? WormAuditSinkFactory::fromConfig())->append($event);
        } catch (Throwable $exception) {
            if ($this->shouldFailClosed()) {
                throw $exception;
            }

            Log::error('Platform audit WORM mirror failed: ' . $exception->getMessage());
        }
    }

    /**
     * Return whether audit persistence should fail when WORM mirroring fails.
     */
    private function shouldFailClosed(): bool
    {
        if ($this->wormFailClosed !== null) {
            return $this->wormFailClosed;
        }

        $configured = Configure::read('PlatformAudit.worm.failClosed');
        if ($configured !== null) {
            return (bool)$configured;
        }

        return filter_var(env('PLATFORM_AUDIT_WORM_FAIL_CLOSED', false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function encodeMetadata(array $metadata): string
    {
        $metadataJson = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($metadataJson === false) {
            throw new RuntimeException('Unable to encode audit metadata.');
        }

        return $metadataJson;
    }

    /**
     * @param array<string, mixed> $event
     */
    private function canonicalEventJson(array $event): string
    {
        $payload = [
            'tenant_id' => $event['tenant_id'],
            'platform_user_id' => $event['platform_user_id'],
            'action' => $event['action'],
            'subject_type' => $event['subject_type'],
            'subject_id' => $event['subject_id'],
            'reason' => $event['reason'],
            'metadata' => $event['metadata'],
            'ip_address' => $event['ip_address'],
            'user_agent' => $event['user_agent'],
            'created_at' => $event['created_at'],
            'previous_hash' => $event['previous_hash'],
        ];
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Unable to encode audit event payload.');
        }

        return $json;
    }

    /**
     * Format timestamps consistently for the platform database.
     */
    private function formatTime(DateTimeInterface|string $time): string
    {
        if ($time instanceof DateTimeInterface) {
            return $time->format('Y-m-d H:i:s');
        }

        return (new DateTime($time))->format('Y-m-d H:i:s');
    }
}
