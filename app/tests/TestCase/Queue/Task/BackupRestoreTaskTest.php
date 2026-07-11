<?php
declare(strict_types=1);

namespace App\Test\TestCase\Queue\Task;

use App\KMP\TenantContext;
use App\KMP\TenantMetadata;
use App\Queue\Task\BackupRestoreTask;
use Cake\TestSuite\TestCase;
use ReflectionClass;
use ReflectionMethod;

class BackupRestoreTaskTest extends TestCase
{
    public function testStandaloneRestoreMigrationsUseDefaultConnectionWithoutSchemaLockWrites(): void
    {
        $method = new ReflectionMethod(BackupRestoreTask::class, 'migrationCommandArgs');
        $task = (new ReflectionClass(BackupRestoreTask::class))->newInstanceWithoutConstructor();

        $this->assertSame(
            ['--connection', 'default', '--no-lock'],
            $method->invoke($task),
        );
    }

    public function testTenantRestoreMigrationsUseTenantConnectionWithoutSchemaLockWrites(): void
    {
        $method = new ReflectionMethod(BackupRestoreTask::class, 'migrationCommandArgs');
        $task = (new ReflectionClass(BackupRestoreTask::class))->newInstanceWithoutConstructor();
        $tenant = new TenantMetadata(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'alpha',
            'Alpha Kingdom',
            'active',
            'db',
            'alpha',
            'alpha_role',
        );

        $arguments = TenantContext::with($tenant, static fn(): array => $method->invoke($task));

        $this->assertSame(
            ['--connection', 'tenant', '--no-lock'],
            $arguments,
        );
    }
}
