<?php
declare(strict_types=1);

namespace App\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;

class TelemetryCheckCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    public function testCheckPrintsConfigAndRuntimeFingerprint(): void
    {
        $this->exec('telemetry_check');

        $this->assertExitSuccess();
        $this->assertOutputContains('KMP telemetry configuration');
        $this->assertOutputContains('PERF_REQUEST_LOG_ENABLED');
        $this->assertOutputContains('APPINSIGHTS_LOG_ENABLED');
        $this->assertOutputContains('Runtime fingerprint');
        $this->assertOutputContains('PHP_VERSION');
        $this->assertOutputContains('telemetry_schema_version');
        $this->assertOutputContains('Storage');
        $this->assertOutputContains('LOGS writable?');
    }

    public function testCheckWithSendFlagDeliversSmokeTrace(): void
    {
        $this->exec('telemetry_check --send');

        $this->assertOutputContains('Sending smoke trace');
        $this->assertOutputContains('smoke trace written');
    }
}
