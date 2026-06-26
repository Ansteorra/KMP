<?php
declare(strict_types=1);

namespace App\Log\Formatter;

use App\KMP\Telemetry\RequestQueryCounter;
use Cake\Log\Formatter\DefaultFormatter;

/**
 * Adds HTTP request correlation details to database query log lines.
 */
class QueryLogFormatter extends DefaultFormatter
{
    private ?string $lastRequestId = null;

    private int $queryNumber = 0;

    /**
     * @param mixed $level Log level.
     * @param string $message Query log message.
     * @param array<string, mixed> $context Log context.
     * @return string Formatted query log line.
     */
    public function format($level, string $message, array $context = []): string
    {
        $requestContext = RequestQueryCounter::currentRequestContext();
        if ($requestContext !== []) {
            $message = $this->prefixRequestContext($message, $requestContext, $context);
        }

        return parent::format($level, $message, $context);
    }

    /**
     * @param string $message Query log message.
     * @param array<string, string|bool> $requestContext Current request context.
     * @param array<string, mixed> $context Log context.
     * @return string Query log message prefixed with request correlation details.
     */
    private function prefixRequestContext(string $message, array $requestContext, array $context): string
    {
        $requestId = (string)$requestContext['request_id'];
        $queryNumber = $context['request_query_number'] ?? $this->nextQueryNumber($requestId);

        return sprintf(
            '[request_id=%s query_number=%s method=%s host=%s path=%s target=%s turbo_frame=%s ajax=%s] %s',
            $requestId,
            (string)$queryNumber,
            (string)$requestContext['request_method'],
            (string)$requestContext['request_host'],
            (string)$requestContext['request_path'],
            (string)$requestContext['request_target'],
            $requestContext['turbo_frame'] !== '' ? (string)$requestContext['turbo_frame'] : '-',
            $requestContext['is_ajax'] ? '1' : '0',
            $message,
        );
    }

    /**
     * @param string $requestId Current request ID.
     * @return int Monotonic query number within the request for direct QueryLogger output.
     */
    private function nextQueryNumber(string $requestId): int
    {
        if ($this->lastRequestId !== $requestId) {
            $this->lastRequestId = $requestId;
            $this->queryNumber = 0;
        }

        return ++$this->queryNumber;
    }
}
