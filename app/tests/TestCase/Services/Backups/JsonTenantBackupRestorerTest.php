<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Backups;

use App\KMP\TenantContext;
use App\KMP\TenantMetadata;
use App\Services\Backups\JsonTenantBackupRestorer;
use App\Services\BackupService;
use App\Services\Secrets\SensitiveString;
use App\Services\TenantConnectionManager;
use App\Test\TestCase\Support\ArraySecretStore;
use Cake\TestSuite\TestCase;
use RuntimeException;

class JsonTenantBackupRestorerTest extends TestCase
{
    private string $archivePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->archivePath = TMP . 'json-tenant-restore-' . uniqid('', true) . '.json.gz';
        file_put_contents($this->archivePath, 'logical-archive');
    }

    protected function tearDown(): void
    {
        if (is_file($this->archivePath)) {
            unlink($this->archivePath);
        }
        parent::tearDown();
    }

    public function testValidateAndRestoreRunInsideTenantScopeAndApplyMigrations(): void
    {
        $connections = new class extends TenantConnectionManager {
            /**
             * @var list<string>
             */
            public array $scopedTenants = [];

            public function __construct()
            {
                parent::__construct(new ArraySecretStore([]));
            }

            public function withTenant(TenantMetadata $tenant, callable $callback): mixed
            {
                $this->scopedTenants[] = $tenant->slug;

                return TenantContext::with($tenant, $callback);
            }
        };
        $migrationRuns = 0;
        $backupService = new class extends BackupService {
            public ?string $validatedArchive = null;

            public ?string $importedArchive = null;

            public function validateLogicalArchive(string $compressedData): void
            {
                $this->validatedArchive = $compressedData;
            }

            public function importLogicalArchive(
                string $compressedData,
                ?callable $progressReporter = null,
                array|callable|null $options = [],
                ?callable $migrationRunner = null,
            ): array {
                $this->importedArchive = $compressedData;
                if ($migrationRunner !== null) {
                    $migrationRunner();
                }
                if ($progressReporter !== null) {
                    $progressReporter(['phase' => 'completed']);
                }

                return ['table_count' => 3, 'row_count' => 12];
            }
        };
        $restorer = new JsonTenantBackupRestorer(
            $connections,
            static function () use (&$migrationRuns): void {
                $migrationRuns++;
            },
            static fn(): BackupService => $backupService,
        );
        $progress = [];
        $tenant = $this->tenant();

        $restorer->validate($tenant, $this->archivePath);
        $stats = $restorer->restore(
            $tenant,
            new SensitiveString('database-password'),
            $this->archivePath,
            static function (array $event) use (&$progress): void {
                $progress[] = $event;
            },
        );

        $this->assertSame(['demo', 'demo'], $connections->scopedTenants);
        $this->assertNull(TenantContext::tryCurrent());
        $this->assertSame('logical-archive', $backupService->validatedArchive);
        $this->assertSame('logical-archive', $backupService->importedArchive);
        $this->assertSame(1, $migrationRuns);
        $this->assertSame([['phase' => 'completed']], $progress);
        $this->assertSame(['table_count' => 3, 'row_count' => 12], $stats);
    }

    public function testRestoreRejectsMissingDatabasePassword(): void
    {
        $restorer = new JsonTenantBackupRestorer(
            new TenantConnectionManager(new ArraySecretStore([])),
            static function (): void {
            },
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('password is unavailable');

        $restorer->restore($this->tenant(), new SensitiveString(''), $this->archivePath);
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
