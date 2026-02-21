<?php

declare(strict_types=1);

namespace App\Services;

use Cake\Cache\Cache;

/**
 * Tracks restore lock and restore progress in shared cache.
 */
class RestoreStatusService
{
    private const CACHE_CONFIG = 'restore_status';
    private const LOCK_KEY = 'restore.lock';
    private const STATUS_KEY = 'restore.status';
    private const DEFAULT_LOCK_TTL_SECONDS = 1800;

    /**
     * Acquire restore lock and initialize running status.
     *
     * @param array<string, mixed> $context
     */
    public function acquireLock(array $context = [], ?int $ttlSeconds = null): bool
    {
        $this->clearExpiredLock();

        $seconds = max(60, (int)($ttlSeconds ?? self::DEFAULT_LOCK_TTL_SECONDS));
        $startedAt = $this->nowIso();
        $expiresAt = $this->isoAfterSeconds($seconds);

        $lockPayload = [
            'locked' => true,
            'started_at' => $startedAt,
            'expires_at' => $expiresAt,
        ];

        if (!Cache::add(self::LOCK_KEY, array_merge($lockPayload, $context), self::CACHE_CONFIG)) {
            return false;
        }

        $this->writeStatus(array_merge(
            $this->defaultStatus(),
            $context,
            [
                'locked' => true,
                'status' => 'running',
                'phase' => 'initializing',
                'message' => (string)($context['message'] ?? 'Restore operation starting.'),
                'started_at' => $startedAt,
                'updated_at' => $startedAt,
                'completed_at' => null,
                'expires_at' => $expiresAt,
            ],
        ));

        return true;
    }

    /**
     * Release the restore lock without changing status payload.
     */
    public function releaseLock(): void
    {
        Cache::delete(self::LOCK_KEY, self::CACHE_CONFIG);
    }

    /**
     * Mark status as running and update current phase message.
     *
     * @param array<string, mixed> $context
     */
    public function updateStatus(string $phase, string $message, array $context = []): void
    {
        $status = $this->getStatus();
        $status = array_merge(
            $status,
            $context,
            [
                'locked' => $this->isLocked(),
                'status' => 'running',
                'phase' => $phase,
                'message' => $message,
                'updated_at' => $this->nowIso(),
            ],
        );

        $this->writeStatus($status);
    }

    /**
     * Mark restore status as completed and clear lock.
     *
     * @param array<string, mixed> $context
     */
    public function markCompleted(string $message, array $context = []): void
    {
        Cache::delete(self::LOCK_KEY, self::CACHE_CONFIG);
        $now = $this->nowIso();
        $status = $this->readStatus();

        $this->writeStatus(array_merge(
            $status,
            $context,
            [
                'locked' => false,
                'status' => 'completed',
                'phase' => 'completed',
                'message' => $message,
                'updated_at' => $now,
                'completed_at' => $now,
                'expires_at' => null,
            ],
        ));
    }

    /**
     * Mark restore status as failed and clear lock.
     *
     * @param array<string, mixed> $context
     */
    public function markFailed(string $message, array $context = []): void
    {
        Cache::delete(self::LOCK_KEY, self::CACHE_CONFIG);
        $now = $this->nowIso();
        $status = $this->readStatus();

        $this->writeStatus(array_merge(
            $status,
            $context,
            [
                'locked' => false,
                'status' => 'failed',
                'phase' => 'failed',
                'message' => $message,
                'updated_at' => $now,
                'completed_at' => $now,
                'expires_at' => null,
            ],
        ));
    }

    /**
     * Return current restore status payload.
     *
     * @return array<string, mixed>
     */
    public function getStatus(): array
    {
        $status = $this->readStatus();
        $lock = $this->readActiveLock();

        if ($lock !== null) {
            $status['locked'] = true;
            $status['status'] = 'running';
            $status['started_at'] = $status['started_at'] ?? ($lock['started_at'] ?? null);
            $status['expires_at'] = $lock['expires_at'] ?? ($status['expires_at'] ?? null);

            return $status;
        }

        if (($status['status'] ?? null) === 'running') {
            $now = $this->nowIso();
            $status['locked'] = false;
            $status['status'] = 'failed';
            $status['phase'] = 'interrupted';
            $status['message'] = 'Restore lock expired before completion.';
            $status['updated_at'] = $now;
            $status['completed_at'] = $now;
            $status['expires_at'] = null;
            $this->writeStatus($status);

            return $status;
        }

        $status['locked'] = false;

        return $status;
    }

    public function isLocked(): bool
    {
        return $this->readActiveLock() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultStatus(): array
    {
        return [
            'locked' => false,
            'status' => 'idle',
            'phase' => 'idle',
            'message' => 'No restore currently running.',
            'started_at' => null,
            'updated_at' => null,
            'completed_at' => null,
            'expires_at' => null,
            'source' => null,
            'backup_id' => null,
            'actor' => null,
            'table_count' => null,
            'tables_processed' => 0,
            'row_count' => null,
            'rows_processed' => 0,
            'current_table' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readStatus(): array
    {
        $status = Cache::read(self::STATUS_KEY, self::CACHE_CONFIG);
        if (!is_array($status)) {
            return $this->defaultStatus();
        }

        return array_merge($this->defaultStatus(), $status);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readActiveLock(): ?array
    {
        $lock = Cache::read(self::LOCK_KEY, self::CACHE_CONFIG);
        if (!is_array($lock)) {
            return null;
        }

        if ($this->isExpired((string)($lock['expires_at'] ?? ''))) {
            Cache::delete(self::LOCK_KEY, self::CACHE_CONFIG);

            return null;
        }

        return $lock;
    }

    private function clearExpiredLock(): void
    {
        $this->readActiveLock();
    }

    /**
     * @param array<string, mixed> $status
     */
    private function writeStatus(array $status): void
    {
        Cache::write(self::STATUS_KEY, $status, self::CACHE_CONFIG);
    }

    private function nowIso(): string
    {
        return (new \DateTimeImmutable('now'))->format(\DateTimeInterface::ATOM);
    }

    private function isoAfterSeconds(int $seconds): string
    {
        return (new \DateTimeImmutable('now'))
            ->modify(sprintf('+%d seconds', $seconds))
            ->format(\DateTimeInterface::ATOM);
    }

    private function isExpired(string $isoValue): bool
    {
        if ($isoValue === '') {
            return false;
        }

        $expiresAt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $isoValue);
        if (!$expiresAt instanceof \DateTimeImmutable) {
            return false;
        }

        return $expiresAt <= new \DateTimeImmutable('now');
    }
}
