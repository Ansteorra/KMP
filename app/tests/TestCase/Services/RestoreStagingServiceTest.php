<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Services\RestoreStagingService;
use Cake\TestSuite\TestCase;

class RestoreStagingServiceTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'kmp_restore_staging_test_'
            . bin2hex(random_bytes(4));
    }

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

    public function testStageAndConsumeRoundTripDeletesPayload(): void
    {
        $service = new RestoreStagingService($this->directory);
        $token = $service->stage('encrypted-bytes', 'restore-key', [
            'source' => 'backup.kmpbackup',
            'actor' => '42',
        ]);

        $this->assertMatchesRegularExpression('/^[a-f0-9]{48}$/', $token);
        $payloadPath = $this->directory . DIRECTORY_SEPARATOR . $token . '.json';
        $this->assertFileExists($payloadPath);

        $payload = $service->consume($token);

        $this->assertSame('encrypted-bytes', $payload['encrypted_data']);
        $this->assertSame('restore-key', $payload['encryption_key']);
        $this->assertSame('backup.kmpbackup', $payload['context']['source']);
        $this->assertFileDoesNotExist($payloadPath);
    }
}
