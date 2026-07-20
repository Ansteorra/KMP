<?php
declare(strict_types=1);

namespace App\Services\Platform;

use Cake\Database\Connection;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Prunes terminal platform jobs while preserving active work and longer-lived failures.
 */
final class PlatformJobRetentionService
{
    public const DEFAULT_SCHEDULE_DAYS = 14;
    public const DEFAULT_COMPLETED_DAYS = 90;
    public const DEFAULT_FAILED_DAYS = 180;
    public const DEFAULT_LIMIT = 5000;

    /**
     * Constructor.
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array{schedule_completed: int, completed: int, failed: int}
     */
    public function prune(
        int $scheduleDays = self::DEFAULT_SCHEDULE_DAYS,
        int $completedDays = self::DEFAULT_COMPLETED_DAYS,
        int $failedDays = self::DEFAULT_FAILED_DAYS,
        int $limit = self::DEFAULT_LIMIT,
        ?DateTimeImmutable $now = null,
    ): array {
        $this->assertDays($scheduleDays, 'Schedule job');
        $this->assertDays($completedDays, 'Completed job');
        $this->assertDays($failedDays, 'Failed job');
        if ($limit < 1 || $limit > 10000) {
            throw new InvalidArgumentException('Platform job prune limit must be between 1 and 10000.');
        }

        $now ??= new DateTimeImmutable();

        return [
            'schedule_completed' => $this->deleteBatch(
                'completed',
                $now->modify(sprintf('-%d days', $scheduleDays))->format('Y-m-d H:i:s'),
                $limit,
                true,
            ),
            'completed' => $this->deleteBatch(
                'completed',
                $now->modify(sprintf('-%d days', $completedDays))->format('Y-m-d H:i:s'),
                $limit,
                false,
            ),
            'failed' => $this->deleteBatch(
                'failed',
                $now->modify(sprintf('-%d days', $failedDays))->format('Y-m-d H:i:s'),
                $limit,
                null,
            ),
        ];
    }

    /**
     * Validate a retention window.
     */
    private function assertDays(int $days, string $label): void
    {
        if ($days < 1 || $days > 3650) {
            throw new InvalidArgumentException(sprintf('%s retention must be between 1 and 3650 days.', $label));
        }
    }

    /**
     * Delete one bounded batch of terminal jobs.
     */
    private function deleteBatch(string $status, string $cutoff, int $limit, ?bool $scheduleOnly): int
    {
        $jobTypePredicate = match ($scheduleOnly) {
            true => 'AND job_type = :jobType',
            false => 'AND job_type != :jobType',
            null => '',
        };
        $params = ['status' => $status, 'cutoff' => $cutoff];
        if ($scheduleOnly !== null) {
            $params['jobType'] = 'platform_schedule';
        }

        return $this->connection->execute(
            sprintf(
                'DELETE FROM platform_jobs
                  WHERE id IN (
                        SELECT id
                          FROM platform_jobs
                         WHERE status = :status
                           AND COALESCE(finished_at, modified_at, created_at) < :cutoff
                           %s
                      ORDER BY COALESCE(finished_at, modified_at, created_at) ASC
                         LIMIT %d
                  )',
                $jobTypePredicate,
                $limit,
            ),
            $params,
        )->rowCount();
    }
}
