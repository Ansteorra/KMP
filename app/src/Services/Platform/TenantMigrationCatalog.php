<?php
declare(strict_types=1);

namespace App\Services\Platform;

use Cake\Core\Plugin;
use Cake\Database\Connection;
use Cake\Utility\Inflector;
use RuntimeException;

/**
 * Discovers the release migration set and compares it with tenant history tables.
 */
final class TenantMigrationCatalog
{
    /**
     * @var array<string, list<string>>|null
     */
    private ?array $expectedVersions;

    /**
     * @param array<string, list<string>>|null $expectedVersions Test/support override keyed by app or plugin name
     */
    public function __construct(?array $expectedVersions = null)
    {
        $this->expectedVersions = $expectedVersions;
    }

    /**
     * Highest globally ordered migration version shipped by this release.
     */
    public function latestVersion(): string
    {
        $versions = array_merge(...array_values($this->versions()));
        if ($versions === []) {
            throw new RuntimeException('No tenant migrations were discovered for this release.');
        }

        return max($versions);
    }

    /**
     * Compare every app and plugin migration history with this release.
     */
    public function inspect(Connection $connection): TenantMigrationState
    {
        $tables = $connection->getSchemaCollection()->listTables();
        $pending = [];
        $unexpected = [];
        $appliedAcrossScopes = [];

        foreach ($this->versions() as $scope => $expected) {
            $table = $this->historyTable($scope);
            $applied = in_array($table, $tables, true)
                ? $this->appliedVersions($connection, $table)
                : [];
            $appliedAcrossScopes = array_merge($appliedAcrossScopes, $applied);
            $missing = array_values(array_diff($expected, $applied));
            $drift = array_values(array_diff($applied, $expected));
            if ($missing !== []) {
                $pending[$scope] = $missing;
            }
            if ($drift !== []) {
                $unexpected[$scope] = $drift;
            }
        }

        return new TenantMigrationState(
            $this->latestVersion(),
            $appliedAcrossScopes === [] ? null : max($appliedAcrossScopes),
            $pending,
            $unexpected,
        );
    }

    /**
     * @return array<string, list<string>>
     */
    public function versions(): array
    {
        return $this->expectedVersions ??= $this->discoverVersions();
    }

    /**
     * @return array<string, list<string>>
     */
    private function discoverVersions(): array
    {
        $sources = ['app' => CONFIG . 'Migrations'];
        foreach (Plugin::getCollection() as $name => $plugin) {
            $path = $plugin->getPath() . 'config' . DS . 'Migrations';
            if (is_dir($path)) {
                $sources[(string)$name] = $path;
            }
        }

        $versions = [];
        foreach ($sources as $scope => $path) {
            $scopeVersions = [];
            foreach (glob($path . DS . '*.php') ?: [] as $file) {
                if (preg_match('/^(\d{14})_/', basename($file), $matches)) {
                    $scopeVersions[] = $matches[1];
                }
            }
            if ($scopeVersions === []) {
                continue;
            }
            sort($scopeVersions, SORT_STRING);
            $versions[$scope] = array_values(array_unique($scopeVersions));
        }

        return $versions;
    }

    /**
     * Resolve the legacy per-scope history table required by app configuration.
     */
    private function historyTable(string $scope): string
    {
        if ($scope === 'app') {
            return 'phinxlog';
        }

        $prefix = str_replace(['\\', '/', '.'], '_', Inflector::underscore($scope));

        return $prefix . '_phinxlog';
    }

    /**
     * @return list<string>
     */
    private function appliedVersions(Connection $connection, string $table): array
    {
        $quotedTable = $connection->getDriver()->quoteIdentifier($table);
        $rows = $connection->execute(sprintf('SELECT version FROM %s ORDER BY version', $quotedTable))
            ->fetchAll('assoc');

        return array_map(static fn(array $row): string => (string)$row['version'], $rows);
    }
}
