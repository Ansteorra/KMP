<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Backups;

use App\KMP\TenantContext;
use App\KMP\TenantMetadata;
use App\Services\Backups\JsonTenantBackupDumper;
use App\Services\BackupService;
use App\Services\Secrets\SensitiveString;
use App\Services\TenantConnectionManager;
use App\Test\TestCase\Support\ArraySecretStore;
use Cake\TestSuite\TestCase;
use RuntimeException;

class JsonTenantBackupDumperTest extends TestCase
{
    private string $outputPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->outputPath = TMP . 'json-tenant-backup-' . uniqid('', true) . '.json.gz';
    }

    protected function tearDown(): void
    {
        if (is_file($this->outputPath)) {
            unlink($this->outputPath);
        }
        parent::tearDown();
    }

    public function testDumpExportsLogicalArchiveInsideTenantScope(): void
    {
        $tenant = $this->tenant();
        $archiveData = gzencode('{"meta":{"version":2}}');
        $this->assertNotFalse($archiveData);
        $connections = new class extends TenantConnectionManager {
            public ?string $scopedTenant = null;

            public function __construct()
            {
                parent::__construct(new ArraySecretStore([]));
            }

            public function withTenant(TenantMetadata $tenant, callable $callback): mixed
            {
                $this->scopedTenant = $tenant->slug;

                return TenantContext::with($tenant, $callback);
            }
        };
        $backupService = new class ($archiveData) extends BackupService {
            public function __construct(private readonly string $archiveData)
            {
                parent::__construct();
            }

            public function exportLogicalArchive(): array
            {
                return [
                    'data' => $this->archiveData,
                    'meta' => [
                        'table_count' => 1,
                        'row_count' => 2,
                        'size_bytes' => strlen($this->archiveData),
                    ],
                ];
            }
        };
        $dumper = new JsonTenantBackupDumper($connections, static fn(): BackupService => $backupService);

        $result = $dumper->dump($tenant, new SensitiveString('database-password'), $this->outputPath);

        $this->assertSame('demo', $connections->scopedTenant);
        $this->assertNull(TenantContext::tryCurrent());
        $this->assertSame($archiveData, file_get_contents($this->outputPath));
        $this->assertSame(strlen($archiveData), $result->sizeBytes);
        $this->assertSame(['kmp-json-export', '--tenant', 'demo'], $result->argv);
        $this->assertSame(0600, fileperms($this->outputPath) & 0777);
    }

    public function testDumpRejectsMissingDatabasePasswordBeforeOpeningTenantScope(): void
    {
        $connections = new class extends TenantConnectionManager {
            public bool $called = false;

            public function __construct()
            {
                parent::__construct(new ArraySecretStore([]));
            }

            public function withTenant(TenantMetadata $tenant, callable $callback): mixed
            {
                $this->called = true;

                return $callback();
            }
        };
        $dumper = new JsonTenantBackupDumper($connections);

        try {
            $dumper->dump($this->tenant(), new SensitiveString(''), $this->outputPath);
            $this->fail('Expected an empty database password to fail.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('password is unavailable', $exception->getMessage());
        }

        $this->assertFalse($connections->called);
        $this->assertFileDoesNotExist($this->outputPath);
    }

    private function tenant(): TenantMetadata
    {
        return new TenantMetadata(
            'tenant-id',
            'demo',
            'Demo',
            'active',
            'database.test',
            'demo_db',
            'demo_role',
        );
    }
}
