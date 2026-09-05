<?php
declare(strict_types=1);

namespace App\Test\TestCase\Log\Engine;

use App\Log\Engine\PrivateFileLog;
use App\Test\TestCase\BaseTestCase;

class PrivateFileLogTest extends BaseTestCase
{
    public function testDebugCopiesOfQueriesAndNestedContextAreSanitized(): void
    {
        $directory = sys_get_temp_dir() . '/kmp-log-' . bin2hex(random_bytes(6)) . '/';
        $logger = new PrivateFileLog(['path' => $directory, 'file' => 'debug', 'mask' => 0600, 'dirMask' => 0700]);
        try {
            $logger->log('debug', "SELECT * FROM members WHERE first_name = 'PRIVATE_SQL_CANARY'", [
                'scope' => ['cake.database.queries'],
            ]);
            $logger->log('error', 'waiver.failed {notes}', ['notes' => 'PRIVATE_NOTE_CANARY']);
            $contents = file_get_contents($directory . 'debug.log');
            $this->assertStringNotContainsString('PRIVATE_', $contents);
            $this->assertStringContainsString('waiver.failed', $contents);
            $this->assertSame(0600, fileperms($directory . 'debug.log') & 0777);
        } finally {
            if (is_file($directory . 'debug.log')) {
                unlink($directory . 'debug.log');
            }
            rmdir($directory);
        }
    }
}
