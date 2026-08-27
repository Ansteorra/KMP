<?php
declare(strict_types=1);

namespace App\Test\TestCase\Command;

use App\Test\TestCase\BaseTestCase;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;

/**
 * Tests the legacy single-database backup CLI contract.
 */
class BackupCommandTest extends BaseTestCase
{
    use ConsoleIntegrationTestTrait;

    private string|false $originalEncryptionKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalEncryptionKey = getenv('BACKUP_ENCRYPTION_KEY');
        $this->setEncryptionKey('environment-test-key');
    }

    protected function tearDown(): void
    {
        $this->setEncryptionKey($this->originalEncryptionKey);
        parent::tearDown();
    }

    public function testEncryptionKeyCanBeReadFromEnvironment(): void
    {
        $this->exec('backup restore');

        $this->assertExitError();
        $this->assertErrorContains('Filename required for restore');
        $this->assertStringNotContainsString(
            'No encryption key provided',
            implode("\n", $this->_err?->messages() ?? []),
        );
    }

    private function setEncryptionKey(string|false $value): void
    {
        if ($value === false) {
            putenv('BACKUP_ENCRYPTION_KEY');
            unset($_ENV['BACKUP_ENCRYPTION_KEY'], $_SERVER['BACKUP_ENCRYPTION_KEY']);

            return;
        }

        putenv("BACKUP_ENCRYPTION_KEY={$value}");
        $_ENV['BACKUP_ENCRYPTION_KEY'] = $value;
        $_SERVER['BACKUP_ENCRYPTION_KEY'] = $value;
    }
}
