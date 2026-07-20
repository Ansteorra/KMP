<?php
declare(strict_types=1);

namespace App\Log\Engine;

use Cake\Log\Engine\BaseLog;
use Cake\Log\Formatter\DefaultFormatter;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use JsonException;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\API\Logs\LoggerInterface;
use OpenTelemetry\API\Logs\Severity;
use OpenTelemetry\API\Signals;
use OpenTelemetry\Contrib\Grpc\GrpcTransportFactory;
use OpenTelemetry\Contrib\Otlp\LogsExporter;
use OpenTelemetry\Contrib\Otlp\OtlpUtil;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Logs\LoggerProvider;
use OpenTelemetry\SDK\Logs\LoggerProviderInterface;
use OpenTelemetry\SDK\Logs\Processor\BatchLogRecordProcessor;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use Stringable;
use Throwable;

/**
 * Sends CakePHP log events to Azure Application Insights.
 *
 * Emits Application Insights-compatible trace telemetry either directly over
 * HTTPS or through a local OpenTelemetry collector over OTLP/gRPC.
 */
class ApplicationInsightsLog extends BaseLog
{
    public const TELEMETRY_SCHEMA_VERSION = '1';

    private const MAX_MESSAGE_LENGTH = 32768;

    private const MAX_PROPERTY_LENGTH = 8192;

    /**
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [
        'levels' => [],
        'scopes' => [],
        'formatter' => DefaultFormatter::class,
        'connectionString' => null,
        'cloudRole' => 'kmp',
        'cloudRoleInstance' => null,
        'timeout' => 2.0,
        'batchSize' => 25,
        'sampleRate' => 100.0,
        'channel' => 'application',
        'transport' => null,
        'otlpEndpoint' => null,
        'otlpTimeout' => 0.05,
        'otlpEmitter' => null,
        // Suppress repeated send-failure log lines for this many seconds across
        // every instance in the process. 0 disables suppression.
        'failureSuppressSeconds' => 60,
        // Optional callable that may rewrite/redact the interpolated message
        // before it is buffered. Signature: function (string $message): string.
        // Use this on channels that may contain PII (e.g. SQL queries).
        'messageSanitizer' => null,
        // When true, emit a single summary trace (channel=telemetry-health)
        // during the shutdown flush so exporter overhead is observable.
        'emitSelfMetrics' => true,
    ];

    private string $instrumentationKey;

    private string $endpoint;

    private bool $otlpEnabled = false;

    private static ?LoggerProviderInterface $sharedOtlpLoggerProvider = null;

    private static ?LoggerInterface $sharedOtlpLogger = null;

    private static int $sharedOtlpInstanceCount = 0;

    private bool $usesSharedOtlpProvider = false;

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $buffer = [];

    private bool $reportedSendFailure = false;

    private int $sendCount = 0;

    private int $failCount = 0;

    private int $droppedCount = 0;

    private float $totalFlushMs = 0.0;

    private int $flushCount = 0;

    private bool $selfMetricsEmitted = false;

    private bool $shutdownComplete = false;

    /**
     * @param array<string, mixed> $config Configuration array
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        $otlpEndpoint = trim((string)$this->getConfig('otlpEndpoint'));
        $this->otlpEnabled = $otlpEndpoint !== '' || is_callable($this->getConfig('otlpEmitter'));
        if ($this->otlpEnabled) {
            $this->instrumentationKey = 'otlp';
            $this->endpoint = '';
            if (!is_callable($this->getConfig('otlpEmitter'))) {
                $this->initializeOtlp($otlpEndpoint);
            }
        } else {
            $parsed = $this->parseConnectionString((string)$this->getConfig('connectionString'));
            $this->instrumentationKey = $parsed['instrumentationkey'] ?? '';
            if ($this->instrumentationKey === '') {
                throw new InvalidArgumentException('APPINSIGHTS_CONNECTION_STRING must include InstrumentationKey.');
            }

            $ingestionEndpoint = rtrim(
                $parsed['ingestionendpoint'] ?? 'https://dc.services.visualstudio.com',
                '/',
            );
            $this->endpoint = $ingestionEndpoint . '/v2/track';
        }

        register_shutdown_function([$this, 'shutdown']);
    }

    /**
     * Shutdown hook: flush remaining telemetry then emit one self-metrics
     * trace describing this process' exporter activity. Called via
     * register_shutdown_function so the request response has already been
     * sent under FPM (see flush()).
     *
     * @return void
     */
    public function shutdown(): void
    {
        if ($this->shutdownComplete) {
            return;
        }

        $this->flush();
        $this->emitSelfMetrics();
        if ($this->usesSharedOtlpProvider) {
            self::$sharedOtlpInstanceCount--;
            if (self::$sharedOtlpInstanceCount === 0) {
                self::$sharedOtlpLoggerProvider?->shutdown();
                self::$sharedOtlpLoggerProvider = null;
                self::$sharedOtlpLogger = null;
            }
        }
        $this->shutdownComplete = true;
    }

    /**
     * Sends a log message as Application Insights trace telemetry.
     *
     * @param mixed $level The severity level of the message being written
     * @param \Stringable|string $message The message to log
     * @param array $context Additional log context
     * @return void
     */
    public function log($level, Stringable|string $message, array $context = []): void
    {
        if (!$this->shouldSample()) {
            return;
        }

        $message = $this->interpolate($message, $context);

        $sanitizer = $this->getConfig('messageSanitizer');
        if (is_callable($sanitizer)) {
            $message = (string)$sanitizer($message);
        }

        $this->buffer[] = $this->buildPayload((string)$level, $message, $context);

        if (count($this->buffer) >= max(1, (int)$this->getConfig('batchSize'))) {
            $this->flush();
        }
    }

    /**
     * Sends buffered telemetry to Application Insights.
     *
     * Under PHP-FPM this attempts fastcgi_finish_request() first so the
     * worker is released back to the pool before the HTTPS round-trip.
     *
     * @return void
     */
    public function flush(): void
    {
        if ($this->buffer === []) {
            return;
        }

        $batch = $this->buffer;
        $this->buffer = [];

        // Under FPM: release the worker so the response is delivered before
        // we synchronously POST to Application Insights. This is the single
        // biggest mitigation against telemetry distorting request timing.
        if (function_exists('fastcgi_finish_request') && PHP_SAPI === 'fpm-fcgi') {
            // Suppress any warning from fastcgi_finish_request itself; it is
            // safe to call repeatedly but may emit notices on some builds.
            $previousHandler = set_error_handler(static fn(): bool => true);
            try {
                fastcgi_finish_request();
            } finally {
                if ($previousHandler !== null) {
                    set_error_handler($previousHandler);
                } else {
                    restore_error_handler();
                }
            }
        }

        try {
            $json = json_encode($batch, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->droppedCount += count($batch);
            $this->reportSendFailure('Application Insights log export failed: ' . $exception->getMessage());

            return;
        }

        $flushStart = hrtime(true);
        $ok = $this->send($json);
        $this->totalFlushMs += (hrtime(true) - $flushStart) / 1_000_000;
        $this->flushCount++;

        if ($ok) {
            $this->sendCount += count($batch);
        } else {
            $this->failCount += count($batch);
        }
    }

    /**
     * @param array<string, mixed> $context Additional log context
     * @return array<string, mixed>
     */
    protected function buildPayload(string $level, string $message, array $context): array
    {
        $properties = $this->normalizeContext($context);
        $properties['channel'] = (string)$this->getConfig('channel');
        $properties['level'] = $level;
        $properties['telemetry_schema_version'] = self::TELEMETRY_SCHEMA_VERSION;

        $tags = [
            'ai.cloud.role' => (string)$this->getConfig('cloudRole'),
            'ai.cloud.roleInstance' => (string)($this->getConfig('cloudRoleInstance') ?: gethostname() ?: ''),
        ];
        $tags = array_filter($tags, static fn(string $value): bool => $value !== '');

        $payload = [
            'name' => 'Microsoft.ApplicationInsights.' . $this->instrumentationKey . '.Message',
            'time' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z'),
            'iKey' => $this->instrumentationKey,
            'tags' => $tags,
            'data' => [
                'baseType' => 'MessageData',
                'baseData' => [
                    'ver' => 2,
                    'message' => $this->truncate($message, self::MAX_MESSAGE_LENGTH),
                    'severityLevel' => $this->severityLevel($level),
                    'properties' => $properties,
                ],
            ],
        ];
        $sampleRate = $this->sampleRate();
        if ($sampleRate < 100.0) {
            $payload['sampleRate'] = $sampleRate;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $context Additional log context
     * @return array<string, string>
     */
    private function normalizeContext(array $context): array
    {
        $properties = [];
        foreach ($context as $key => $value) {
            if ($key === 'scope') {
                $properties[$key] = $this->truncate(
                    is_array($value) ? implode(',', $value) : (string)$value,
                    self::MAX_PROPERTY_LENGTH,
                );

                continue;
            }

            if ($value === null || is_scalar($value) || $value instanceof Stringable) {
                $properties[(string)$key] = $this->truncate((string)$value, self::MAX_PROPERTY_LENGTH);

                continue;
            }

            try {
                $properties[(string)$key] = $this->truncate(
                    json_encode($value, JSON_THROW_ON_ERROR),
                    self::MAX_PROPERTY_LENGTH,
                );
            } catch (JsonException) {
                $properties[(string)$key] = '[unserializable context value]';
            }
        }

        return $properties;
    }

    /**
     * @return array<string, string>
     */
    private function parseConnectionString(string $connectionString): array
    {
        $parts = [];
        foreach (explode(';', $connectionString) as $segment) {
            if (!str_contains($segment, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $segment, 2);
            $parts[strtolower(trim($key))] = trim($value);
        }

        return $parts;
    }

    /**
     * Maps PSR log levels to Application Insights severity levels.
     *
     * @param string $level PSR log level
     * @return int Application Insights severity level
     */
    private function severityLevel(string $level): int
    {
        return match ($level) {
            'debug' => 0,
            'info', 'notice' => 1,
            'warning' => 2,
            'error' => 3,
            'critical', 'alert', 'emergency' => 4,
            default => 1,
        };
    }

    /**
     * Sends telemetry JSON through the configured transport.
     *
     * @param string $json Encoded Application Insights envelope
     * @return bool True when the request was accepted (2xx), false on any failure.
     */
    private function send(string $json): bool
    {
        if ($this->otlpEnabled) {
            return $this->sendOtlp($json);
        }

        $transport = $this->getConfig('transport');
        if (is_callable($transport)) {
            try {
                $transport($this->endpoint, $json);
            } catch (Throwable $exception) {
                $this->reportSendFailure(
                    'Application Insights log export failed: ' . $exception->getMessage(),
                );

                return false;
            }

            return true;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $json,
                'ignore_errors' => true,
                'timeout' => (float)$this->getConfig('timeout'),
            ],
        ]);

        $lastError = null;
        set_error_handler(static function (int $severity, string $message) use (&$lastError): bool {
            $lastError = $message;

            return true;
        });
        try {
            $result = file_get_contents($this->endpoint, false, $context);
        } finally {
            restore_error_handler();
        }

        if ($result === false) {
            $this->reportSendFailure(
                'Application Insights log export failed: ' . ($lastError ?? 'request could not be sent.'),
            );

            return false;
        }

        $statusLine = $http_response_header[0] ?? '';
        if (!preg_match('/^HTTP\/\S+\s+2\d\d\b/', $statusLine)) {
            $this->reportSendFailure(
                'Application Insights log export failed: ' . ($statusLine ?: 'unknown HTTP status'),
            );

            return false;
        }

        return true;
    }

    /**
     * Configures an OTLP/gRPC logger that exports to the local Container Apps
     * managed OpenTelemetry agent.
     */
    private function initializeOtlp(string $endpoint): void
    {
        if (!extension_loaded('grpc')) {
            throw new InvalidArgumentException('OTLP logging requires the grpc PHP extension.');
        }

        if (self::$sharedOtlpLoggerProvider === null) {
            $grpcEndpoint = rtrim($endpoint, '/') . OtlpUtil::method(Signals::LOGS);
            $timeout = max(0.01, (float)$this->getConfig('otlpTimeout'));
            $transport = (new GrpcTransportFactory())->create(
                $grpcEndpoint,
                timeout: $timeout,
                maxRetries: 0,
            );
            $exporter = new LogsExporter($transport);
            $batchSize = max(1, (int)$this->getConfig('batchSize'));
            $processor = new BatchLogRecordProcessor(
                $exporter,
                Clock::getDefault(),
                maxQueueSize: max(4096, $batchSize * 8),
                scheduledDelayMillis: 60000,
                exportTimeoutMillis: max(10, (int)ceil($timeout * 1000)),
                maxExportBatchSize: max(512, $batchSize),
                autoFlush: false,
            );
            $cloudRoleInstance = (string)$this->getConfig('cloudRoleInstance');
            if ($cloudRoleInstance === '') {
                $cloudRoleInstance = gethostname() ?: '';
            }
            $resource = ResourceInfoFactory::defaultResource()->merge(ResourceInfo::create(Attributes::create([
                'service.name' => (string)$this->getConfig('cloudRole'),
                'service.instance.id' => $cloudRoleInstance,
            ])));
            self::$sharedOtlpLoggerProvider = LoggerProvider::builder()
                ->setResource($resource)
                ->addLogRecordProcessor($processor)
                ->build();
            self::$sharedOtlpLogger = self::$sharedOtlpLoggerProvider->getLogger(
                'kmp.telemetry',
                self::TELEMETRY_SCHEMA_VERSION,
            );
        }

        self::$sharedOtlpInstanceCount++;
        $this->usesSharedOtlpProvider = true;
    }

    /**
     * Sends a buffered Application Insights-compatible batch through OTLP.
     */
    private function sendOtlp(string $json): bool
    {
        try {
            $payloads = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payloads)) {
                throw new JsonException('Telemetry payload is not an array.');
            }

            $records = array_map([$this, 'toOtlpRecord'], $payloads);
            $emitter = $this->getConfig('otlpEmitter');
            if (is_callable($emitter)) {
                $emitter($records);

                return true;
            }
            if (self::$sharedOtlpLogger === null || self::$sharedOtlpLoggerProvider === null) {
                throw new InvalidArgumentException('OTLP logger is not configured.');
            }

            foreach ($records as $record) {
                self::$sharedOtlpLogger->logRecordBuilder()
                    ->setBody($record['message'])
                    ->setSeverityNumber($record['severity'])
                    ->setSeverityText(strtoupper($record['level']))
                    ->setAttributes($record['attributes'])
                    ->emit();
            }

            return true;
        } catch (Throwable $exception) {
            $this->reportSendFailure('OpenTelemetry log export failed: ' . $exception->getMessage());

            return false;
        }
    }

    /**
     * @param array<string, mixed> $payload Application Insights envelope
     * @return array{
     *   message: string,
     *   severity: \OpenTelemetry\API\Logs\Severity,
     *   level: string,
     *   attributes: array<string, mixed>
     * }
     */
    private function toOtlpRecord(array $payload): array
    {
        $baseData = $payload['data']['baseData'] ?? [];
        $attributes = is_array($baseData['properties'] ?? null) ? $baseData['properties'] : [];
        if (isset($payload['sampleRate'])) {
            $attributes['sample_rate'] = (string)$payload['sampleRate'];
        }
        $level = (string)($attributes['level'] ?? 'info');

        return [
            'message' => (string)($baseData['message'] ?? ''),
            'severity' => $this->otelSeverity((int)($baseData['severityLevel'] ?? 1)),
            'level' => $level,
            'attributes' => $attributes,
        ];
    }

    /**
     * Maps the legacy Application Insights severity scale to OpenTelemetry.
     */
    private function otelSeverity(int $applicationInsightsSeverity): Severity
    {
        return match ($applicationInsightsSeverity) {
            0 => Severity::DEBUG,
            2 => Severity::WARN,
            3 => Severity::ERROR,
            4 => Severity::FATAL,
            default => Severity::INFO,
        };
    }

    /**
     * Checks whether the current event should be sampled in.
     *
     * @return bool
     */
    private function shouldSample(): bool
    {
        $sampleRate = $this->sampleRate();
        if ($sampleRate >= 100.0) {
            return true;
        }
        if ($sampleRate <= 0.0) {
            return false;
        }

        return random_int(1, 10000) <= (int)round($sampleRate * 100);
    }

    /**
     * Reads the configured Application Insights sample rate.
     *
     * @return float Sample percentage from 0 to 100
     */
    private function sampleRate(): float
    {
        return max(0.0, min(100.0, (float)$this->getConfig('sampleRate')));
    }

    /**
     * Truncates telemetry fields to Application Insights-safe sizes.
     *
     * @param string $value Telemetry field value
     * @param int $maxLength Maximum allowed length
     * @return string Truncated value
     */
    private function truncate(string $value, int $maxLength): string
    {
        if (strlen($value) <= $maxLength) {
            return $value;
        }

        return substr($value, 0, $maxLength - 14) . '...[truncated]';
    }

    /**
     * Reports one exporter failure per suppression window across the process.
     *
     * Tries APCu first (process-wide for FPM workers in the same pool), then
     * a small marker file in TMP keyed by the ingestion endpoint host, then
     * falls back to per-instance suppression. error_log() output is always
     * emitted at least once per window so operators still see failures.
     *
     * @param string $message Failure message
     * @return void
     */
    private function reportSendFailure(string $message): void
    {
        $window = max(0, (int)$this->getConfig('failureSuppressSeconds'));

        if ($window > 0 && $this->failureRecentlyReported($window)) {
            return;
        }

        if ($this->reportedSendFailure) {
            return;
        }

        $this->reportedSendFailure = true;
        $this->markFailureReported($window);
        error_log($message);
    }

    /**
     * @param int $window Suppression window in seconds
     */
    private function failureRecentlyReported(int $window): bool
    {
        $key = $this->failureSuppressionKey();

        if (function_exists('apcu_fetch') && function_exists('apcu_enabled') && apcu_enabled()) {
            $found = false;
            $value = apcu_fetch($key, $found);
            if ($found && is_int($value) && ($value + $window) > time()) {
                return true;
            }
        }

        $markerFile = $this->failureMarkerPath($key);
        if ($markerFile !== null && is_file($markerFile)) {
            $mtime = $this->silenced(static fn() => filemtime($markerFile));
            if ($mtime !== false && ($mtime + $window) > time()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param int $window Suppression window in seconds (used for APCu TTL)
     */
    private function markFailureReported(int $window): void
    {
        $key = $this->failureSuppressionKey();
        $now = time();

        if (function_exists('apcu_store') && function_exists('apcu_enabled') && apcu_enabled()) {
            apcu_store($key, $now, max(1, $window));
        }

        $markerFile = $this->failureMarkerPath($key);
        if ($markerFile === null) {
            return;
        }

        $dir = dirname($markerFile);
        if (!is_dir($dir)) {
            $this->silenced(static fn() => mkdir($dir, 0777, true));
        }
        $this->silenced(static fn() => file_put_contents($markerFile, (string)$now));
    }

    /**
     * Returns the cache/marker key used for cross-process suppression.
     *
     * @return string Cache key derived from the ingestion endpoint.
     */
    private function failureSuppressionKey(): string
    {
        return 'kmp_appinsights_send_failure_' . md5($this->endpoint);
    }

    /**
     * Returns the path to the file-backed suppression marker, or null when
     * TMP is unavailable.
     *
     * @param string $key Cache key from failureSuppressionKey()
     * @return string|null Absolute path or null when filesystem fallback is unavailable.
     */
    private function failureMarkerPath(string $key): ?string
    {
        if (!defined('TMP')) {
            return null;
        }

        return TMP . 'telemetry' . DIRECTORY_SEPARATOR . $key . '.marker';
    }

    /**
     * Emits a single trace describing the exporter's activity this process.
     * Bypasses sampling because it is already aggregated and infrequent.
     *
     * @return void
     */
    private function emitSelfMetrics(): void
    {
        if ($this->selfMetricsEmitted || $this->otlpEnabled) {
            return;
        }
        $this->selfMetricsEmitted = true;

        if (!(bool)$this->getConfig('emitSelfMetrics')) {
            return;
        }

        if ($this->sendCount + $this->failCount + $this->droppedCount === 0) {
            return;
        }

        $originalChannel = (string)$this->getConfig('channel');
        $context = [
            'scope' => 'app.telemetry',
            'sent' => $this->sendCount,
            'failed' => $this->failCount,
            'dropped' => $this->droppedCount,
            'flush_count' => $this->flushCount,
            'flush_total_ms' => round($this->totalFlushMs, 3),
            'flush_avg_ms' => $this->flushCount > 0
                ? round($this->totalFlushMs / $this->flushCount, 3)
                : 0.0,
            'source_channel' => $originalChannel,
            'php_sapi' => PHP_SAPI,
        ];

        // Temporarily relabel so this single trace lands under telemetry-health
        $this->setConfig('channel', 'telemetry-health');
        try {
            $message = sprintf(
                '[telemetry_health] channel=%s sent=%d failed=%d dropped=%d flush_count=%d flush_total_ms=%.2f',
                $originalChannel,
                $this->sendCount,
                $this->failCount,
                $this->droppedCount,
                $this->flushCount,
                $this->totalFlushMs,
            );
            $this->buffer[] = $this->buildPayload('info', $message, $context);

            // Force send: do not recurse into emitSelfMetrics here.
            if ($this->buffer !== []) {
                $batch = $this->buffer;
                $this->buffer = [];
                try {
                    $json = json_encode($batch, JSON_THROW_ON_ERROR);
                    $this->send($json);
                } catch (JsonException) {
                    // best-effort; nothing more to log
                }
            }
        } finally {
            $this->setConfig('channel', $originalChannel);
        }
    }

    /**
     * Runs a callable with PHP errors silenced. Used in place of `@` which
     * PHPCS disallows in this codebase.
     *
     * @param callable $fn Operation to run
     * @return mixed
     */
    private function silenced(callable $fn): mixed
    {
        set_error_handler(static fn(): bool => true);
        try {
            return $fn();
        } finally {
            restore_error_handler();
        }
    }
}
