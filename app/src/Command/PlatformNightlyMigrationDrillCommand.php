<?php
declare(strict_types=1);

namespace App\Command;

use App\KMP\TenantMetadata;
use App\Services\Platform\PlatformScheduleRunner;
use App\Services\Platform\ReleaseCompatibilityChecker;
use App\Services\Platform\ReleaseManifest;
use App\Services\Platform\TenantMigrateCommandScrubber;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Cake\I18n\DateTime;
use Cake\Utility\Text;
use RuntimeException;
use Throwable;

/**
 * Runs a non-destructive nightly migration drill for staging/canary tenants.
 */
class PlatformNightlyMigrationDrillCommand extends Command
{
    private const PLATFORM_CONNECTION = 'platform';
    private const JOB_TYPE = 'nightly_migration_drill';
    private const ENABLE_ENV = 'KMP_ENABLE_NIGHTLY_MIGRATION_DRILL';

    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'platform nightly_migration_drill';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->setDescription(
                'Run a safe nightly tenant migration drill using release checks, status, and dry-run probes.',
            )
            ->addOption('manifest', [
                'short' => 'm',
                'help' => 'Path to release manifest JSON.',
                'default' => ROOT . DS . 'config' . DS . 'release_manifest.json',
            ])
            ->addOption('tenant', [
                'short' => 't',
                'help' => 'Tenant slug to drill.',
            ])
            ->addOption('all', [
                'help' => 'Drill all active tenants. Use only in staging/canary environments.',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('plan-only', [
                'help' => 'Only validate the manifest and target list; do not connect to tenant databases.',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('allow-staging', [
                'help' => 'Required with KMP_ENABLE_NIGHTLY_MIGRATION_DRILL=true to run tenant status/dry-run probes.',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('target', [
                'help' => 'Optional target migration version to pass to the dry-run probe.',
            ])
            ->addOption('date', [
                'help' => 'Optional target migration date to pass to the dry-run probe.',
            ])
            ->addOption('fail-fast', [
                'help' => 'Stop after the first tenant probe failure.',
                'boolean' => true,
                'default' => false,
            ]);
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $jobId = Text::uuid();
        $this->insertJob($jobId, $args);

        try {
            $this->assertSelector($args);
            $tenants = $this->resolveTenants($args);
            $manifest = ReleaseManifest::fromFile((string)$args->getOption('manifest'));
            $this->assertReleaseCompatibility($tenants, $manifest);

            $failed = 0;
            $completed = 0;
            if (!(bool)$args->getOption('plan-only')) {
                $this->assertExecutionAllowed($args);
                foreach ($tenants as $tenant) {
                    $result = $this->drillTenant($tenant, $args, $io);
                    if ($result) {
                        $completed++;
                    } else {
                        $failed++;
                        if ((bool)$args->getOption('fail-fast')) {
                            break;
                        }
                    }
                }
            } else {
                $completed = count($tenants);
            }

            $parameters = $this->resultParameters($args, $tenants, $manifest->appVersion, $completed, $failed);
            $this->finishJob($jobId, $failed === 0 ? 'completed' : 'failed', $parameters);

            $status = $failed === 0 ? 'completed' : 'failed';
            $io->out(sprintf(
                'NIGHTLY_MIGRATION_DRILL status=%s mode=%s tenants=%d completed=%d failed=%d manifest=%s',
                $status,
                (bool)$args->getOption('plan-only') ? 'plan-only' : 'dry-run',
                count($tenants),
                $completed,
                $failed,
                $manifest->appVersion,
            ));

            return $failed === 0 ? self::CODE_SUCCESS : self::CODE_ERROR;
        } catch (Throwable $e) {
            $message = $this->scrubError($e->getMessage());
            $this->finishJob($jobId, 'failed', $this->baseParameters($args), $message);
            $io->err($message);

            return self::CODE_ERROR;
        }
    }

    /**
     * Run status and dry-run probes for a tenant.
     */
    protected function drillTenant(TenantMetadata $tenant, Arguments $args, ConsoleIo $io): bool
    {
        $io->out(sprintf('Drilling tenant %s...', $tenant->slug));
        $statusCode = $this->runTenantCommand(['--tenant', $tenant->slug, '--status'], $io);
        if ($statusCode !== self::CODE_SUCCESS) {
            $io->err(sprintf('Tenant %s status probe failed.', $tenant->slug));

            return false;
        }
        $markerCode = $this->runTenantCommand(
            ['--tenant', $tenant->slug, '--marker-only', '--manifest', (string)$args->getOption('manifest')],
            $io,
        );
        if ($markerCode !== self::CODE_SUCCESS) {
            $io->err(sprintf('Tenant %s migration marker probe failed.', $tenant->slug));

            return false;
        }

        $dryRunArgs = ['--tenant', $tenant->slug, '--dry-run', '--manifest', (string)$args->getOption('manifest')];
        foreach (['target', 'date'] as $option) {
            if ($args->getOption($option) !== null && $args->getOption($option) !== '') {
                $dryRunArgs[] = '--' . $option;
                $dryRunArgs[] = (string)$args->getOption($option);
            }
        }

        $dryRunCode = $this->runTenantCommand($dryRunArgs, $io);
        if ($dryRunCode !== self::CODE_SUCCESS) {
            $io->err(sprintf('Tenant %s dry-run migration probe failed.', $tenant->slug));

            return false;
        }

        return true;
    }

    /**
     * @param list<string> $commandArgs
     */
    protected function runTenantCommand(array $commandArgs, ConsoleIo $io): int
    {
        return (int)$this->executeCommand(TenantMigrateCommand::class, $commandArgs, $io);
    }

    /**
     * @return list<\App\KMP\TenantMetadata>
     */
    private function resolveTenants(Arguments $args): array
    {
        if ((bool)$args->getOption('all')) {
            $rows = $this->platform()->execute(
                'SELECT * FROM tenants WHERE status = :status ORDER BY slug',
                ['status' => 'active'],
            )->fetchAll('assoc');

            return array_map(
                static fn(array $row): TenantMetadata => TenantMetadata::fromPlatformRow($row),
                $rows,
            );
        }

        $slug = strtolower(trim((string)$args->getOption('tenant')));
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,78}[a-z0-9])?$/', $slug)) {
            throw new RuntimeException('Invalid tenant slug.');
        }
        $row = $this->platform()->execute(
            'SELECT * FROM tenants WHERE slug = :slug AND status = :status LIMIT 1',
            ['slug' => $slug, 'status' => 'active'],
        )->fetch('assoc');
        if (!is_array($row)) {
            throw new RuntimeException(sprintf('Active tenant "%s" was not found.', $slug));
        }

        return [TenantMetadata::fromPlatformRow($row)];
    }

    /**
     * @param list<\App\KMP\TenantMetadata> $tenants
     */
    private function assertReleaseCompatibility(array $tenants, ReleaseManifest $manifest): void
    {
        $checker = new ReleaseCompatibilityChecker();
        foreach ($tenants as $tenant) {
            $checker->assertTenantCompatible($tenant->schemaVersion, $manifest, $tenant->slug);
        }
    }

    /**
     * Validate that exactly one target selector was supplied.
     */
    private function assertSelector(Arguments $args): void
    {
        $hasTenant = trim((string)$args->getOption('tenant')) !== '';
        $hasAll = (bool)$args->getOption('all');
        if ($hasTenant === $hasAll) {
            throw new RuntimeException('Specify exactly one of --tenant or --all.');
        }
        if ($args->getOption('target') !== null && $args->getOption('date') !== null) {
            throw new RuntimeException('Use either --target or --date, not both.');
        }
    }

    /**
     * Fail closed unless both the command flag and environment gate are present.
     */
    private function assertExecutionAllowed(Arguments $args): void
    {
        if ((bool)$args->getOption('allow-staging') && getenv(self::ENABLE_ENV) === 'true') {
            return;
        }

        throw new RuntimeException(
            sprintf(
                'Refusing tenant drill execution without --allow-staging and %s=true. '
                    . 'Use --plan-only for metadata checks.',
                self::ENABLE_ENV,
            ),
        );
    }

    /**
     * Insert the aggregate drill job before checks begin.
     */
    private function insertJob(string $jobId, Arguments $args): void
    {
        $now = $this->now();
        $this->platform()->insert('platform_jobs', [
            'id' => $jobId,
            'tenant_id' => null,
            'requested_by_platform_user_id' => null,
            'job_type' => self::JOB_TYPE,
            'status' => 'running',
            'idempotency_key' => null,
            'parameters' => json_encode($this->baseParameters($args), JSON_UNESCAPED_SLASHES),
            'log_uri' => null,
            'last_error' => null,
            'created_at' => $now,
            'started_at' => $now,
            'finished_at' => null,
            'modified_at' => $now,
        ]);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function finishJob(string $jobId, string $status, array $parameters, ?string $lastError = null): void
    {
        $now = $this->now();
        $this->platform()->update('platform_jobs', [
            'status' => $status,
            'parameters' => json_encode(
                TenantMigrateCommandScrubber::scrubMetadata($parameters),
                JSON_UNESCAPED_SLASHES,
            ),
            'last_error' => $lastError,
            'finished_at' => $now,
            'modified_at' => $now,
        ], ['id' => $jobId]);
    }

    /**
     * @return array<string, mixed>
     */
    private function baseParameters(Arguments $args): array
    {
        return TenantMigrateCommandScrubber::scrubMetadata([
            'manifest' => (string)$args->getOption('manifest'),
            'tenant' => $args->getOption('tenant'),
            'all' => (bool)$args->getOption('all'),
            'plan_only' => (bool)$args->getOption('plan-only'),
            'mode' => (bool)$args->getOption('plan-only') ? 'plan-only' : 'dry-run',
            'target' => $args->getOption('target'),
            'date' => $args->getOption('date'),
        ]);
    }

    /**
     * @param list<\App\KMP\TenantMetadata> $tenants
     * @return array<string, mixed>
     */
    private function resultParameters(
        Arguments $args,
        array $tenants,
        string $manifestVersion,
        int $completed,
        int $failed,
    ): array {
        return array_merge($this->baseParameters($args), [
            'manifest_version' => $manifestVersion,
            'tenant_slugs' => array_map(static fn(TenantMetadata $tenant): string => $tenant->slug, $tenants),
            'completed' => $completed,
            'failed' => $failed,
        ]);
    }

    /**
     * Remove secret-like values from command errors before storing them.
     */
    private function scrubError(string $message): string
    {
        return TenantMigrateCommandScrubber::scrubString(PlatformScheduleRunner::scrubError($message));
    }

    /**
     * Get the platform metadata connection.
     */
    private function platform(): Connection
    {
        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get(self::PLATFORM_CONNECTION);

        return $connection;
    }

    /**
     * Return the current UTC timestamp.
     */
    private function now(): DateTime
    {
        return DateTime::now('UTC');
    }
}
