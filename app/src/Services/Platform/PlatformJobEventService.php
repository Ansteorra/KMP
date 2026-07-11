<?php
declare(strict_types=1);

namespace App\Services\Platform;

use Cake\Database\Connection;
use Cake\I18n\DateTime;
use Cake\Utility\Text;
use InvalidArgumentException;

/**
 * Persists operator-safe progress events for asynchronous platform jobs.
 */
final class PlatformJobEventService
{
    private const LEVELS = ['debug', 'info', 'warning', 'error', 'success'];

    /**
     * Constructor.
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Append a sanitized event to a platform job timeline.
     */
    public function record(string $jobId, string $level, string $code, string $message): void
    {
        $level = strtolower(trim($level));
        if (!in_array($level, self::LEVELS, true)) {
            throw new InvalidArgumentException('Platform job event level is invalid.');
        }
        $code = strtolower(trim($code));
        if ($code === '' || !preg_match('/\A[a-z0-9_.-]+\z/', $code)) {
            throw new InvalidArgumentException('Platform job event code is invalid.');
        }
        $message = trim(preg_replace('/\s+/', ' ', PlatformScheduleRunner::scrubError($message)) ?? '');
        if ($message === '') {
            $message = 'No additional detail was provided.';
        }
        $sequenceNumber = (int)$this->connection->execute(
            'SELECT COALESCE(MAX(sequence_number), 0) + 1
               FROM platform_job_events
              WHERE platform_job_id = :jobId',
            ['jobId' => $jobId],
        )->fetchColumn(0);

        $this->connection->insert('platform_job_events', [
            'id' => Text::uuid(),
            'platform_job_id' => $jobId,
            'sequence_number' => $sequenceNumber,
            'event_level' => $level,
            'event_code' => mb_substr($code, 0, 80),
            'message' => mb_substr($message, 0, 500),
            'created_at' => DateTime::now('UTC')->format('Y-m-d H:i:s.u'),
        ]);
    }
}
