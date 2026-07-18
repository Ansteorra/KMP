<?php
declare(strict_types=1);

namespace App\Services\Platform;

use App\KMP\TenantMetadata;
use App\Services\TenantConnectionManager;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Closure;
use InvalidArgumentException;
use Throwable;

/**
 * Drains the default and active-tenant Queue plugin datasources fairly.
 */
class PlatformQueueDrainService
{
    public const DEFAULT_CYCLE_BUDGET_SECONDS = 240;
    public const MAX_CYCLE_BUDGET_SECONDS = 300;

    private readonly Closure $clock;
    private readonly Closure $wallClock;

    /**
     * @param \App\Services\Platform\QueueDrainService $queueDrainService Queue processor
     * @param \App\Services\TenantConnectionManager $tenantConnectionManager Tenant connection binder
     * @param \Cake\Database\Connection|null $platformConnection Optional platform connection override
     * @param string|null $defaultDatasourceIdentity Optional physical default datasource identity
     * @param \Closure|null $clock Optional monotonic clock returning seconds
     * @param \Closure|null $wallClock Optional wall clock returning Unix seconds
     */
    public function __construct(
        private readonly QueueDrainService $queueDrainService,
        private readonly TenantConnectionManager $tenantConnectionManager,
        private readonly ?Connection $platformConnection = null,
        private readonly ?string $defaultDatasourceIdentity = null,
        ?Closure $clock = null,
        ?Closure $wallClock = null,
    ) {
        $this->clock = $clock ?? static fn(): float => hrtime(true) / 1_000_000_000;
        $this->wallClock = $wallClock ?? static fn(): float => (float)time();
    }

    /**
     * @return array{
     *     default: int,
     *     tenants: array<string, int>,
     *     failures: array<string, string>,
     *     duplicateTenants: list<string>,
     *     deferredTenants: list<string>,
     *     datasourcesProcessed: int,
     *     jobsProcessed: int,
     *     elapsedMs: float
     * }
     */
    public function drain(
        int $maxJobs = QueueDrainService::MAX_JOBS,
        int $maxRuntimeSeconds = QueueDrainService::DEFAULT_MAX_RUNTIME_SECONDS,
        int $cycleBudgetSeconds = self::DEFAULT_CYCLE_BUDGET_SECONDS,
    ): array {
        if ($cycleBudgetSeconds < 1 || $cycleBudgetSeconds > self::MAX_CYCLE_BUDGET_SECONDS) {
            throw new InvalidArgumentException(sprintf(
                'Queue cycle budget must be between 1 and %d seconds.',
                self::MAX_CYCLE_BUDGET_SECONDS,
            ));
        }

        $startedAt = ($this->clock)();
        $deadline = $startedAt + $cycleBudgetSeconds;
        $defaultProcessed = 0;
        $tenantResults = [];
        $failures = [];
        $duplicateTenants = [];
        $deferredTenants = [];

        try {
            $defaultProcessed = $this->queueDrainService->drainDefault(
                $maxJobs,
                $this->runtimeForRemainingBudget($maxRuntimeSeconds, $deadline),
            );
        } catch (Throwable $exception) {
            $failures['default'] = PlatformScheduleRunner::scrubError($exception->getMessage());
        }

        $defaultIdentity = $this->resolvedDefaultDatasourceIdentity();
        $tenants = $this->rotatedTenants($this->activeTenants(), ($this->wallClock)());
        foreach ($tenants as $index => $tenant) {
            if ($defaultIdentity !== '' && $this->tenantDatasourceIdentity($tenant) === $defaultIdentity) {
                $duplicateTenants[] = $tenant->slug;
                continue;
            }

            $remainingRuntime = $this->remainingRuntime($maxRuntimeSeconds, $deadline);
            if ($remainingRuntime === null) {
                $deferredTenants = array_merge(
                    $deferredTenants,
                    array_map(
                        static fn(TenantMetadata $deferred): string => $deferred->slug,
                        array_slice($tenants, $index),
                    ),
                );
                break;
            }

            try {
                $tenantResults[$tenant->slug] = (int)$this->tenantConnectionManager->withTenant(
                    $tenant,
                    fn(): int => $this->queueDrainService->drainTenant($maxJobs, $remainingRuntime),
                );
            } catch (Throwable $exception) {
                $failures[$tenant->slug] = PlatformScheduleRunner::scrubError($exception->getMessage());
            }
        }

        return [
            'default' => $defaultProcessed,
            'tenants' => $tenantResults,
            'failures' => $failures,
            'duplicateTenants' => $duplicateTenants,
            'deferredTenants' => array_values(array_unique($deferredTenants)),
            'datasourcesProcessed' => (isset($failures['default']) ? 0 : 1) + count($tenantResults),
            'jobsProcessed' => $defaultProcessed + array_sum($tenantResults),
            'elapsedMs' => round((($this->clock)() - $startedAt) * 1000, 2),
        ];
    }

    /**
     * @param list<\App\KMP\TenantMetadata> $tenants
     * @return list<\App\KMP\TenantMetadata>
     */
    private function rotatedTenants(array $tenants, float $startedAt): array
    {
        $count = count($tenants);
        if ($count < 2) {
            return $tenants;
        }

        $offset = ((int)floor($startedAt / 60)) % $count;

        return array_values(array_merge(
            array_slice($tenants, $offset),
            array_slice($tenants, 0, $offset),
        ));
    }

    /**
     * Return the runtime available to the default datasource.
     */
    private function runtimeForRemainingBudget(int $maxRuntimeSeconds, float $deadline): int
    {
        return $this->remainingRuntime($maxRuntimeSeconds, $deadline) ?? 1;
    }

    /**
     * Return a bounded datasource runtime, or null after the fleet deadline.
     */
    private function remainingRuntime(int $maxRuntimeSeconds, float $deadline): ?int
    {
        $remaining = (int)floor($deadline - ($this->clock)());
        if ($remaining < 1) {
            return null;
        }

        return min($maxRuntimeSeconds, $remaining);
    }

    /**
     * Resolve the normalized host/database identity for the default datasource.
     */
    private function resolvedDefaultDatasourceIdentity(): string
    {
        if ($this->defaultDatasourceIdentity !== null) {
            return $this->defaultDatasourceIdentity;
        }

        $config = ConnectionManager::getConfig('default') ?? [];
        $host = (string)($config['host'] ?? $config['server'] ?? '');
        $database = (string)($config['database'] ?? '');
        if (!empty($config['url'])) {
            $parts = parse_url((string)$config['url']);
            $host = (string)($parts['host'] ?? $host);
            $database = ltrim((string)($parts['path'] ?? $database), '/');
        }

        return $this->datasourceIdentity($host, $database);
    }

    /**
     * Resolve the normalized host/database identity for a tenant datasource.
     */
    private function tenantDatasourceIdentity(TenantMetadata $tenant): string
    {
        return $this->datasourceIdentity($tenant->dbServer, $tenant->dbName);
    }

    /**
     * Normalize a physical PostgreSQL datasource identity.
     */
    private function datasourceIdentity(string $host, string $database): string
    {
        $host = strtolower(rtrim(trim($host), '.'));
        $database = strtolower(trim($database));
        if ($host === '' || $database === '') {
            return '';
        }

        return $host . '|' . $database;
    }

    /**
     * @return list<\App\KMP\TenantMetadata>
     */
    private function activeTenants(): array
    {
        $rows = $this->connection()->execute(
            'SELECT * FROM tenants WHERE status = :status ORDER BY slug',
            ['status' => 'active'],
        )->fetchAll('assoc');

        return array_map(
            static fn(array $row): TenantMetadata => TenantMetadata::fromPlatformRow($row),
            $rows,
        );
    }

    /**
     * @return \Cake\Database\Connection
     */
    private function connection(): Connection
    {
        if ($this->platformConnection !== null) {
            return $this->platformConnection;
        }

        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('platform');

        return $connection;
    }
}
