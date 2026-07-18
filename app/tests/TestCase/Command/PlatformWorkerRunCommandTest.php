<?php
declare(strict_types=1);

namespace App\Test\TestCase\Command;

use App\Command\PlatformWorkerRunCommand;
use App\Services\Platform\PlatformWorkerService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleInput;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\Database\Connection;
use Cake\Database\Driver\Postgres;
use Cake\Database\Driver\Sqlite;
use Cake\Database\StatementInterface;
use Cake\TestSuite\TestCase;

class PlatformWorkerRunCommandTest extends TestCase
{
    public function testCommandEmitsJsonSummaryAndReturnsSuccess(): void
    {
        $worker = $this->createMock(PlatformWorkerService::class);
        $worker->expects($this->once())
            ->method('run')
            ->willReturn($this->workerResult());
        $output = new StubConsoleOutput();
        $command = new PlatformWorkerRunCommand($worker, $this->sqliteConnection());

        $status = $command->execute(
            $this->arguments(['json' => true]),
            new ConsoleIo($output, new StubConsoleOutput(), new StubConsoleInput([])),
        );

        $this->assertSame(Command::CODE_SUCCESS, $status);
        $payload = json_decode(implode("\n", $output->messages()), true);
        $this->assertIsArray($payload);
        $this->assertFalse($payload['overlapSkipped']);
        $this->assertSame(3, $payload['summary']['queueJobsProcessed']);
    }

    public function testCommandReturnsErrorWhenAnyLaneFails(): void
    {
        $worker = $this->createMock(PlatformWorkerService::class);
        $result = $this->workerResult();
        $result['queues']['failures'] = ['alpha' => 'queue failed'];
        $worker->method('run')->willReturn($result);
        $command = new PlatformWorkerRunCommand($worker, $this->sqliteConnection());

        $status = $command->execute(
            $this->arguments(['json' => false]),
            new ConsoleIo(new StubConsoleOutput(), new StubConsoleOutput(), new StubConsoleInput([])),
        );

        $this->assertSame(Command::CODE_ERROR, $status);
    }

    public function testCanaryReturnsErrorWhenAnotherWorkerOwnsTheLock(): void
    {
        $worker = $this->createMock(PlatformWorkerService::class);
        $worker->expects($this->never())->method('run');
        $statement = $this->createMock(StatementInterface::class);
        $statement->method('fetch')->willReturn(['acquired' => false]);
        $connection = $this->createMock(Connection::class);
        $connection->method('getDriver')->willReturn($this->createMock(Postgres::class));
        $connection->expects($this->once())
            ->method('execute')
            ->willReturn($statement);
        $command = new PlatformWorkerRunCommand($worker, $connection);

        $status = $command->execute(
            $this->arguments(['json' => true, 'fail-on-overlap' => true]),
            new ConsoleIo(new StubConsoleOutput(), new StubConsoleOutput(), new StubConsoleInput([])),
        );

        $this->assertSame(Command::CODE_ERROR, $status);
    }

    /**
     * @param array<string, bool|string> $overrides Option overrides
     */
    private function arguments(array $overrides): Arguments
    {
        return new Arguments([], array_merge([
            'schedule-limit' => '100',
            'max-jobs' => '100',
            'max-runtime' => '45',
            'cycle-budget' => '240',
            'platform-limit' => '1',
            'json' => false,
            'fail-on-overlap' => false,
        ], $overrides), []);
    }

    /**
     * @return array<string, mixed>
     */
    private function workerResult(): array
    {
        return [
            'schedules' => ['schedules' => 1, 'completed' => 1, 'failed' => 0, 'jobsCreated' => 0],
            'queues' => [
                'default' => 1,
                'tenants' => ['alpha' => 2],
                'failures' => [],
                'duplicateTenants' => [],
                'deferredTenants' => [],
                'datasourcesProcessed' => 2,
                'jobsProcessed' => 3,
                'elapsedMs' => 5.0,
            ],
            'platformJobs' => ['claimed' => 0, 'completed' => 0, 'failed' => 0],
            'errors' => [],
            'summary' => [
                'schedulesDispatched' => 1,
                'datasourcesProcessed' => 2,
                'queueJobsProcessed' => 3,
                'platformJobsCompleted' => 0,
                'platformJobsFailed' => 0,
            ],
            'elapsedMs' => 10.0,
        ];
    }

    private function sqliteConnection(): Connection
    {
        return new Connection([
            'driver' => new Sqlite(),
            'database' => ':memory:',
        ]);
    }
}
