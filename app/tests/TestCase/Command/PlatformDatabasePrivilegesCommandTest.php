<?php
declare(strict_types=1);

namespace App\Test\TestCase\Command;

use App\Command\PlatformDatabasePrivilegesCommand;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class PlatformDatabasePrivilegesCommandTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function conflictingUrls(): array
    {
        return [
            'different runtime role' => ['postgres://other_runtime:synthetic-password-long-enough@db.example.test/shared'],
            'different runtime password' => ['postgres://runtime:other-synthetic-password-long@db.example.test/shared'],
        ];
    }

    #[DataProvider('conflictingUrls')]
    public function testConflictingRuntimeTargetsFailBeforeAnySql(string $platformUrl): void
    {
        $originalAdmin = Configure::read('Database.adminJob');
        $originalPlatform = ConnectionManager::getConfig('platform');
        $originalEnvironment = [];
        foreach (['DATABASE_URL', 'PLATFORM_DATABASE_URL'] as $variable) {
            $originalEnvironment[$variable] = [getenv($variable), $_ENV[$variable] ?? null, $_SERVER[$variable] ?? null];
        }
        $platform = $this->createMock(Connection::class);
        $platform->method('config')->willReturn(['host' => 'db.example.test']);
        $platform->expects($this->never())->method('execute');
        $platform->expects($this->never())->method('getSchemaCollection');
        ConnectionManager::drop('platform');
        ConnectionManager::setConfig('platform', $platform);
        Configure::write('Database.adminJob', true);
        foreach (
            [
            'DATABASE_URL' => 'postgres://runtime:synthetic-password-long-enough@db.example.test/shared',
            'PLATFORM_DATABASE_URL' => $platformUrl,
            ] as $variable => $value
        ) {
            putenv($variable . '=' . $value);
            $_ENV[$variable] = $value;
            $_SERVER[$variable] = $value;
        }
        try {
            $io = $this->createMock(ConsoleIo::class);
            $io->expects($this->once())->method('err')->with(
                'Database privilege reconciliation failed; inspect the private administrative job diagnostics.',
            );
            $this->assertSame(
                PlatformDatabasePrivilegesCommand::CODE_ERROR,
                (new PlatformDatabasePrivilegesCommand())->execute(new Arguments([], [], []), $io),
            );
        } finally {
            ConnectionManager::drop('platform');
            if ($originalPlatform !== null) {
                ConnectionManager::setConfig('platform', $originalPlatform);
            }
            Configure::write('Database.adminJob', $originalAdmin);
            foreach ($originalEnvironment as $variable => [$process, $environment, $server]) {
                putenv($process === false ? $variable : $variable . '=' . $process);
                unset($_ENV[$variable], $_SERVER[$variable]);
                if ($environment !== null) {
                    $_ENV[$variable] = $environment;
                }
                if ($server !== null) {
                    $_SERVER[$variable] = $server;
                }
            }
        }
    }
}
