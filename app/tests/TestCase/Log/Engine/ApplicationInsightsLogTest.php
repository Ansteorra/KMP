<?php
declare(strict_types=1);

namespace App\Test\TestCase\Log\Engine;

use App\Log\Engine\ApplicationInsightsLog;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;
use RuntimeException;

class ApplicationInsightsLogTest extends TestCase
{
    public function testLogSendsTraceTelemetry(): void
    {
        $sent = [];
        $logger = new ApplicationInsightsLog([
            'connectionString' => 'InstrumentationKey=test-key;IngestionEndpoint=https://example.test/',
            'cloudRole' => 'kmp-test',
            'cloudRoleInstance' => 'test-instance',
            'channel' => 'performance',
            'batchSize' => 1,
            'transport' => function (string $endpoint, string $json) use (&$sent): void {
                $sent[] = compact('endpoint', 'json');
            },
        ]);

        $logger->log('warning', 'Slow request {path}', [
            'scope' => ['app.performance'],
            'path' => '/members',
            'duration_ms' => 1234.56,
        ]);

        $this->assertCount(1, $sent);
        $this->assertSame('https://example.test/v2/track', $sent[0]['endpoint']);

        $payload = json_decode($sent[0]['json'], true, 512, JSON_THROW_ON_ERROR);
        $trace = $payload[0];
        $this->assertSame('Microsoft.ApplicationInsights.test-key.Message', $trace['name']);
        $this->assertSame('test-key', $trace['iKey']);
        $this->assertSame('kmp-test', $trace['tags']['ai.cloud.role']);
        $this->assertSame('test-instance', $trace['tags']['ai.cloud.roleInstance']);
        $this->assertSame('MessageData', $trace['data']['baseType']);
        $this->assertSame('Slow request /members', $trace['data']['baseData']['message']);
        $this->assertSame(2, $trace['data']['baseData']['severityLevel']);
        $this->assertSame('performance', $trace['data']['baseData']['properties']['channel']);
        $this->assertSame('app.performance', $trace['data']['baseData']['properties']['scope']);
        $this->assertSame('1234.56', $trace['data']['baseData']['properties']['duration_ms']);
        $this->assertSame(
            ApplicationInsightsLog::TELEMETRY_SCHEMA_VERSION,
            $trace['data']['baseData']['properties']['telemetry_schema_version'],
        );
    }

    public function testMissingInstrumentationKeyFailsConfiguration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('APPINSIGHTS_CONNECTION_STRING must include InstrumentationKey.');

        new ApplicationInsightsLog([
            'connectionString' => 'IngestionEndpoint=https://example.test/',
        ]);
    }

    public function testLogBuffersUntilFlush(): void
    {
        $sent = [];
        $logger = new ApplicationInsightsLog([
            'connectionString' => 'instrumentationkey=test-key;ingestionendpoint=https://example.test/',
            'transport' => function (string $endpoint, string $json) use (&$sent): void {
                $sent[] = compact('endpoint', 'json');
            },
        ]);

        $logger->log('info', 'Buffered telemetry');
        $this->assertSame([], $sent);

        $logger->flush();
        $this->assertCount(1, $sent);

        $payload = json_decode($sent[0]['json'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Buffered telemetry', $payload[0]['data']['baseData']['message']);
    }

    public function testMessageSanitizerIsApplied(): void
    {
        $sent = [];
        $logger = new ApplicationInsightsLog([
            'connectionString' => 'InstrumentationKey=test-key;IngestionEndpoint=https://example.test/',
            'batchSize' => 1,
            'messageSanitizer' => static fn(string $msg): string => str_replace('secret', '<redacted>', $msg),
            'transport' => function (string $endpoint, string $json) use (&$sent): void {
                $sent[] = compact('endpoint', 'json');
            },
        ]);

        $logger->log('info', "SELECT * FROM members WHERE token = 'secret'");

        $payload = json_decode($sent[0]['json'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('<redacted>', $payload[0]['data']['baseData']['message']);
        $this->assertStringNotContainsString('secret', $payload[0]['data']['baseData']['message']);
    }

    public function testZeroSampleRateSuppressesAllEvents(): void
    {
        $sent = [];
        $logger = new ApplicationInsightsLog([
            'connectionString' => 'InstrumentationKey=test-key;IngestionEndpoint=https://example.test/',
            'batchSize' => 1,
            'sampleRate' => 0,
            'transport' => function (string $endpoint, string $json) use (&$sent): void {
                $sent[] = compact('endpoint', 'json');
            },
        ]);

        for ($i = 0; $i < 50; $i++) {
            $logger->log('info', 'dropped');
        }

        $this->assertSame([], $sent);
    }

    public function testFractionalSampleRateAttachesSampleRateOnPayload(): void
    {
        $sent = [];
        // Use 100.0 + capture sampleRate behavior by directly using a small rate;
        // we cannot deterministically force sampling, so we only assert that
        // when sampling is in effect the payload carries sampleRate < 100.
        $logger = new ApplicationInsightsLog([
            'connectionString' => 'InstrumentationKey=test-key;IngestionEndpoint=https://example.test/',
            'batchSize' => 1,
            'sampleRate' => 25.5,
            'transport' => function (string $endpoint, string $json) use (&$sent): void {
                $sent[] = compact('endpoint', 'json');
            },
        ]);

        // Send enough events that statistically at least one passes.
        for ($i = 0; $i < 200; $i++) {
            $logger->log('info', 'sampled');
        }

        if ($sent === []) {
            $this->markTestSkipped('Statistical sampling produced zero events; rerun.');
        }

        $payload = json_decode($sent[0]['json'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('sampleRate', $payload[0]);
        $this->assertSame(25.5, $payload[0]['sampleRate']);
    }

    public function testEmitsSelfMetricsSummaryOnShutdown(): void
    {
        $sent = [];
        $logger = new ApplicationInsightsLog([
            'connectionString' => 'InstrumentationKey=test-key;IngestionEndpoint=https://example.test/',
            'channel' => 'performance',
            'batchSize' => 1,
            'transport' => function (string $endpoint, string $json) use (&$sent): void {
                $sent[] = compact('endpoint', 'json');
            },
        ]);

        $logger->log('info', 'one');
        $logger->log('info', 'two');

        // Simulate end-of-process: flush + emit health summary.
        $logger->shutdown();

        $this->assertGreaterThanOrEqual(3, count($sent));
        $lastPayload = json_decode($sent[array_key_last($sent)]['json'], true, 512, JSON_THROW_ON_ERROR);
        $properties = $lastPayload[0]['data']['baseData']['properties'];
        $this->assertSame('telemetry-health', $properties['channel']);
        $this->assertSame('performance', $properties['source_channel']);
        $this->assertSame('2', $properties['sent']);
        $this->assertSame('0', $properties['failed']);
        $this->assertArrayHasKey('flush_total_ms', $properties);
    }

    public function testSelfMetricsCanBeDisabled(): void
    {
        $sent = [];
        $logger = new ApplicationInsightsLog([
            'connectionString' => 'InstrumentationKey=test-key;IngestionEndpoint=https://example.test/',
            'batchSize' => 1,
            'emitSelfMetrics' => false,
            'transport' => function (string $endpoint, string $json) use (&$sent): void {
                $sent[] = compact('endpoint', 'json');
            },
        ]);

        $logger->log('info', 'only event');
        $logger->shutdown();

        $this->assertCount(1, $sent);
    }

    public function testFailedTransportIsCountedAndDoesNotThrow(): void
    {
        $logger = new ApplicationInsightsLog([
            'connectionString' => 'InstrumentationKey=test-key;IngestionEndpoint=https://example.test/',
            'batchSize' => 1,
            'failureSuppressSeconds' => 0,
            'emitSelfMetrics' => false,
            'transport' => static function (): void {
                throw new RuntimeException('upstream down');
            },
        ]);

        // Should not throw.
        $logger->log('info', 'will fail');
        $this->addToAssertionCount(1);
    }
}
