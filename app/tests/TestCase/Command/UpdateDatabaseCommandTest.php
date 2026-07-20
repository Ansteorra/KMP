<?php
declare(strict_types=1);

namespace App\Test\TestCase\Command;

use App\Command\UpdateDatabaseCommand;
use Cake\Command\Command;
use Cake\Command\SchemacacheClearCommand;
use Cake\Console\Arguments;
use Cake\Console\CommandInterface;
use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\TestSuite\TestCase;

class UpdateDatabaseCommandTest extends TestCase
{
    public function testCustomConnectionAndNoLockArePassedToEveryMigrationCommand(): void
    {
        $command = new class extends UpdateDatabaseCommand {
            /**
             * @var list<array{command: object|string, args: array}>
             */
            public array $calls = [];

            public function executeCommand(
                CommandInterface|string $command,
                array $args = [],
                ?ConsoleIo $io = null,
            ): ?int {
                $this->calls[] = ['command' => $command, 'args' => $args];

                return Command::CODE_SUCCESS;
            }
        };
        $args = new Arguments(
            [],
            ['connection' => 'tenant', 'no-lock' => true],
            ['connection', 'no-lock'],
        );
        $io = new ConsoleIo(new StubConsoleOutput(), new StubConsoleOutput());

        $result = $command->execute($args, $io);

        $this->assertSame(Command::CODE_SUCCESS, $result);
        $this->assertNotEmpty($command->calls);
        $this->assertSame(SchemacacheClearCommand::class, $command->calls[0]['command']);
        $this->assertSame(['--connection', 'tenant'], $command->calls[0]['args']);
        foreach (array_slice($command->calls, 1) as $call) {
            $this->assertContains('--connection', $call['args']);
            $this->assertContains('tenant', $call['args']);
            $this->assertContains('--no-lock', $call['args']);
        }
    }

    public function testEmptyConnectionFailsBeforeRunningCommands(): void
    {
        $command = new class extends UpdateDatabaseCommand {
            public bool $called = false;

            public function executeCommand(
                CommandInterface|string $command,
                array $args = [],
                ?ConsoleIo $io = null,
            ): ?int {
                $this->called = true;

                return Command::CODE_SUCCESS;
            }
        };
        $args = new Arguments([], ['connection' => ''], ['connection']);
        $io = new ConsoleIo(new StubConsoleOutput(), new StubConsoleOutput());

        $result = $command->execute($args, $io);

        $this->assertSame(Command::CODE_ERROR, $result);
        $this->assertFalse($command->called);
    }
}
