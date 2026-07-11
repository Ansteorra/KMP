<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Backups;

use App\Services\Backups\PlatformDatabaseBackupStorageInterface;
use App\Services\Backups\RoutedPlatformDatabaseBackupStorage;
use App\Services\Backups\RoutedTenantBackupStorage;
use App\Services\Backups\TenantBackupStorageInterface;
use App\Services\Backups\TenantBackupStoredObject;
use Cake\TestSuite\TestCase;

class RoutedBackupStorageTest extends TestCase
{
    public function testTenantLocalUriUsesHistoricalAdapter(): void
    {
        $configured = $this->createMock(TenantBackupStorageInterface::class);
        $configured->expects($this->never())->method('retrieve');
        $local = $this->createMock(TenantBackupStorageInterface::class);
        $stored = new TenantBackupStoredObject('local://example/archive.pgdump.enc', 10, str_repeat('a', 64));
        $local->expects($this->once())
            ->method('retrieve')
            ->with('local://example/archive.pgdump.enc', '/tmp/archive')
            ->willReturn($stored);
        $storage = new RoutedTenantBackupStorage($configured, $local);

        $this->assertSame(
            $stored,
            $storage->retrieve('local://example/archive.pgdump.enc', '/tmp/archive'),
        );
    }

    public function testTenantConfiguredUriUsesCurrentAdapter(): void
    {
        $configured = $this->createMock(TenantBackupStorageInterface::class);
        $stored = new TenantBackupStoredObject('backup://tenants/example/archive.json.gz.enc', 10, str_repeat('a', 64));
        $configured->expects($this->once())
            ->method('retrieve')
            ->with('backup://tenants/example/archive.json.gz.enc', '/tmp/archive')
            ->willReturn($stored);
        $local = $this->createMock(TenantBackupStorageInterface::class);
        $local->expects($this->never())->method('retrieve');
        $storage = new RoutedTenantBackupStorage($configured, $local);

        $this->assertSame(
            $stored,
            $storage->retrieve('backup://tenants/example/archive.json.gz.enc', '/tmp/archive'),
        );
    }

    public function testPlatformLocalUriUsesHistoricalAdapter(): void
    {
        $configured = $this->createMock(PlatformDatabaseBackupStorageInterface::class);
        $configured->expects($this->never())->method('delete');
        $local = $this->createMock(PlatformDatabaseBackupStorageInterface::class);
        $local->expects($this->once())
            ->method('delete')
            ->with('local://platform/archive.pgdump.enc');
        $storage = new RoutedPlatformDatabaseBackupStorage($configured, $local);

        $storage->delete('local://platform/archive.pgdump.enc');
    }
}
