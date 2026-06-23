<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Services\RestoreStagingService;
use Cake\TestSuite\TestCase;
use RuntimeException;

/**
 * Tests restore payload staging and single-use token consumption.
 */
class RestoreStagingServiceTest extends TestCase
{
    private string $directory;

    /**
     * Create an isolated staging directory for each test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'kmp_restore_staging_test_'
            . bin2hex(random_bytes(4));
    }

    /**
     * Remove staged files and the test staging directory.
     */
    protected function tearDown(): void
    {
        if (is_dir($this->directory)) {
            foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->directory);
        }
        parent::tearDown();
    }

    /**
     * Verify staged payloads round-trip and remain claimed until success cleanup.
     */
    public function testStageAndConsumeRoundTripRetainsClaimedPayloadUntilDiscard(): void
    {
        $service = new RestoreStagingService($this->directory);
        $token = $service->stage('encrypted-bytes', 'restore-key', [
            'source' => 'backup.kmpbackup',
            'actor' => '42',
        ]);

        $this->assertMatchesRegularExpression('/^[a-f0-9]{48}$/', $token);
        $payloadPath = $this->directory . DIRECTORY_SEPARATOR . $token . '.json';
        $this->assertFileExists($payloadPath);
        $this->assertStringNotContainsString('restore-key', (string)file_get_contents($payloadPath));

        $payload = $service->consume($token);

        $this->assertSame('encrypted-bytes', $payload['encrypted_data']);
        $this->assertSame('restore-key', $payload['encryption_key']);
        $this->assertSame('backup.kmpbackup', $payload['context']['source']);
        $this->assertFileDoesNotExist($payloadPath);
        $this->assertCount(1, glob($payloadPath . '.*.claimed') ?: []);

        $payload = $service->consume($token);
        $this->assertSame('encrypted-bytes', $payload['encrypted_data']);

        $service->discard($token);
        $this->assertSame([], glob($payloadPath . '.*.claimed') ?: []);
    }

    /**
     * Keep invalid claimed payloads for troubleshooting instead of deleting them.
     */
    public function testConsumeKeepsClaimedPayloadWhenValidationFails(): void
    {
        $service = new RestoreStagingService($this->directory);
        $token = $service->stage('encrypted-bytes', 'restore-key');
        $payloadPath = $this->directory . DIRECTORY_SEPARATOR . $token . '.json';
        file_put_contents($payloadPath, '{invalid');

        try {
            $service->consume($token);
            $this->fail('Expected corrupt staging payload to fail validation.');
        } catch (RuntimeException $e) {
            $this->assertSame('Restore staging payload is invalid.', $e->getMessage());
        }

        $this->assertFileDoesNotExist($payloadPath);
        $this->assertCount(1, glob($payloadPath . '.*.claimed') ?: []);
    }
}
