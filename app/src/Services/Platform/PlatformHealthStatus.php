<?php
declare(strict_types=1);

namespace App\Services\Platform;

use DateTimeImmutable;

/**
 * Safe platform metadata database health report.
 */
final class PlatformHealthStatus
{
    public const STATE_HEALTHY = 'healthy';
    public const STATE_DEGRADED = 'degraded';

    /**
     * Constructor.
     *
     * @param string $state Health state
     * @param string $connectionName Datasource alias checked
     * @param string $message Safe diagnostic message
     * @param \DateTimeImmutable $checkedAt Time of check
     * @param string|null $errorClass Exception class, when degraded
     * @param int $retriesAttempted Number of retries attempted
     */
    public function __construct(
        public readonly string $state,
        public readonly string $connectionName,
        public readonly string $message,
        public readonly DateTimeImmutable $checkedAt,
        public readonly ?string $errorClass = null,
        public readonly int $retriesAttempted = 0,
    ) {
    }

    /**
     * Create a healthy status.
     *
     * @param string $connectionName Datasource alias checked
     * @param int $retriesAttempted Number of retries attempted
     * @return self
     */
    public static function healthy(string $connectionName, int $retriesAttempted = 0): self
    {
        return new self(
            self::STATE_HEALTHY,
            $connectionName,
            'Platform metadata database is available.',
            new DateTimeImmutable(),
            null,
            $retriesAttempted,
        );
    }

    /**
     * Create a degraded status without leaking connection settings.
     *
     * @param string $connectionName Datasource alias checked
     * @param string $errorClass Exception class
     * @param int $retriesAttempted Number of retries attempted
     * @return self
     */
    public static function degraded(string $connectionName, string $errorClass, int $retriesAttempted = 0): self
    {
        return new self(
            self::STATE_DEGRADED,
            $connectionName,
            'Platform metadata database is unavailable.',
            new DateTimeImmutable(),
            $errorClass,
            $retriesAttempted,
        );
    }

    /**
     * Whether platform metadata is available.
     *
     * @return bool
     */
    public function isHealthy(): bool
    {
        return $this->state === self::STATE_HEALTHY;
    }

    /**
     * Return a diagnostics-safe array without connection details or secrets.
     *
     * @return array<string, mixed>
     */
    public function toSafeArray(): array
    {
        return [
            'state' => $this->state,
            'connection' => $this->connectionName,
            'message' => $this->message,
            'checked_at' => $this->checkedAt->format(DATE_ATOM),
            'error_class' => $this->errorClass,
            'retries_attempted' => $this->retriesAttempted,
        ];
    }
}
