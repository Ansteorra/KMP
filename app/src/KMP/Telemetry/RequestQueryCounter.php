<?php
declare(strict_types=1);

namespace App\KMP\Telemetry;

use Cake\Database\Log\LoggedQuery;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Stringable;

/**
 * PSR-3 logger that counts queries and accumulates their durations for the
 * current process / request.
 *
 * This is installed on the primary CakePHP database connection so per-request
 * capacity signals (query_count, db_total_ms) are emitted regardless of
 * whether PERF_DB_QUERY_LOG_ENABLED is on. When the connection already had a
 * Cake QueryLogger configured, RequestQueryCounter delegates to it so the
 * existing queries log channel is preserved.
 *
 * A single shared instance is exposed via {@see RequestQueryCounter::instance()}
 * so the performance middleware can read totals at request end without
 * needing DI plumbing.
 */
final class RequestQueryCounter extends AbstractLogger
{
    private static ?self $instance = null;

    private int $count = 0;

    private float $totalMs = 0.0;

    private ?LoggerInterface $inner;

    /**
     * @param \Psr\Log\LoggerInterface|null $inner Optional inner logger to forward to (e.g. Cake QueryLogger).
     */
    public function __construct(?LoggerInterface $inner = null)
    {
        $this->inner = $inner;
    }

    /**
     * Returns (and lazily creates) the process-wide instance. Tests should
     * call {@see reset()} between cases.
     */
    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Replace the shared instance. Used at bootstrap when the connection's
     * existing logger needs to be wrapped.
     */
    public static function setInstance(self $instance): void
    {
        self::$instance = $instance;
    }

    /**
     * Resets per-request counters. Safe to call between requests under
     * long-lived workers (workerman, swoole) or in tests.
     */
    public function reset(): void
    {
        $this->count = 0;
        $this->totalMs = 0.0;
    }

    /**
     * @return int Number of queries observed during the current request.
     */
    public function count(): int
    {
        return $this->count;
    }

    /**
     * @return float Total query duration accumulated this request, in milliseconds.
     */
    public function totalMs(): float
    {
        return $this->totalMs;
    }

    /**
     * @param mixed $level PSR log level
     * @param \Stringable|string $message Log message
     * @param array<string, mixed> $context Log context (may include `query`)
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $query = $context['query'] ?? null;
        if ($query instanceof LoggedQuery) {
            $this->count++;
            // LoggedQuery::getContext() returns the public state including
            // `took` (ms). It is the only stable public accessor.
            $ctx = $query->getContext();
            if (isset($ctx['took'])) {
                $this->totalMs += (float)$ctx['took'];
            }
        } else {
            // Fallback: anything written without a LoggedQuery still counts
            // as a query event so the metric never under-reports.
            $this->count++;
        }

        if ($this->inner !== null) {
            $this->inner->log($level, $message, $context);
        }
    }
}
