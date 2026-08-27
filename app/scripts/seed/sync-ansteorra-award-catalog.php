#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Synchronize the seed award catalog with the Ansteorra production catalog.
 *
 * PHP version 8.4
 *
 * @category Seed
 * @package  KMP
 * @author   KMP Contributors <noreply@kmp.invalid>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     https://github.com/Ansteorra/KMP
 */

use Cake\Database\Connection;
use Cake\Database\Driver\Postgres;
use Cake\Datasource\ConnectionManager;

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols, Generic.Files.LineLength.TooLong
// phpcs:disable PEAR.Commenting.FunctionComment, Generic.Commenting.DocComment.MissingShort

const AWARD_CATALOG_BEGIN = '-- BEGIN KMP MANAGED ANSTEORRA AWARD CATALOG';
const AWARD_CATALOG_END = '-- END KMP MANAGED ANSTEORRA AWARD CATALOG';
const AWARD_CATALOG_TIMESTAMP = '2026-08-26 00:00:00';

$applicationRoot = dirname(__DIR__, 2);
$repositoryRoot = dirname($applicationRoot);
$catalogPath = __DIR__ . '/data/ansteorra-award-catalog.json';
$seedPaths = [
    $repositoryRoot . '/dev_seed_clean.sql' => 'mysql',
    $applicationRoot . '/tests/pg_seed_baseline.sql' => 'postgres',
    $applicationRoot . '/tests/pg_seed.sql' => 'postgres',
];

$options = getopt('', ['apply-local-database', 'check', 'check-local-database', 'write']);
$write = array_key_exists('write', $options);
$check = array_key_exists('check', $options);
$checkLocalDatabase = array_key_exists('check-local-database', $options);
$applyLocalDatabase = array_key_exists('apply-local-database', $options);

if (count(array_filter([$write, $check, $checkLocalDatabase, $applyLocalDatabase])) !== 1) {
    fwrite(
        STDERR,
        "Choose exactly one of --write, --check, --check-local-database, or --apply-local-database.\n",
    );
    exit(2);
}

$catalog = loadCatalog($catalogPath);

if ($applyLocalDatabase) {
    applyCatalogToLocalDatabase($catalog);
    fwrite(STDOUT, catalogSummary('Updated local database', $catalog));
    exit(0);
}
if ($checkLocalDatabase) {
    $connection = localDatabaseConnection();
    assertCatalogDependencies($connection, $catalog);
    validateDatabaseCatalog($connection, $catalog);
    fwrite(STDOUT, catalogSummary('Validated local database', $catalog));
    exit(0);
}

foreach ($seedPaths as $seedPath => $dialect) {
    $contents = file_get_contents($seedPath);
    if ($contents === false) {
        throw new RuntimeException("Unable to read seed snapshot: {$seedPath}");
    }

    $managedBlock = managedCatalogSql($catalog, $dialect);
    if ($write) {
        $contents = replaceManagedBlock($contents, $managedBlock);
        if (file_put_contents($seedPath, $contents) === false) {
            throw new RuntimeException("Unable to write seed snapshot: {$seedPath}");
        }
    } elseif (!str_contains($contents, $managedBlock)) {
        throw new RuntimeException(
            "Seed award catalog is missing or stale in {$seedPath}: "
            . describeManagedBlockDifference($contents, $managedBlock),
        );
    }

    fwrite(STDOUT, catalogSummary(($write ? 'Updated ' : 'Validated ') . basename($seedPath), $catalog));
}

/**
 * @return array<string, mixed>
 */
function loadCatalog(string $path): array
{
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException("Unable to read award catalog: {$path}");
    }
    $catalog = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
    if (!is_array($catalog)) {
        throw new RuntimeException("Award catalog is not an object: {$path}");
    }

    foreach (['domains', 'levels', 'awards'] as $collection) {
        if (!isset($catalog[$collection]) || !is_array($catalog[$collection])) {
            throw new RuntimeException("Award catalog is missing {$collection}.");
        }
        $expectedCount = (int)($catalog['counts'][$collection] ?? -1);
        if (count($catalog[$collection]) !== $expectedCount) {
            throw new RuntimeException("Award catalog {$collection} count does not match its manifest.");
        }
    }

    assertUniqueCatalogValues($catalog['domains'], 'id', 'domain ID');
    assertUniqueCatalogValues($catalog['levels'], 'id', 'level ID');
    assertUniqueCatalogValues($catalog['awards'], 'source_id', 'production award ID');
    assertUniqueCatalogValues($catalog['awards'], 'target_id', 'seed award ID');
    assertUniqueCatalogValues($catalog['awards'], 'name', 'award name');

    $domainIds = array_fill_keys(array_column($catalog['domains'], 'id'), true);
    $levelIds = array_fill_keys(array_column($catalog['levels'], 'id'), true);
    foreach ($catalog['awards'] as $award) {
        if (!isset($domainIds[$award['domain_id'] ?? null])) {
            throw new RuntimeException(sprintf('Award "%s" references an unknown domain.', $award['name'] ?? ''));
        }
        if (!isset($levelIds[$award['level_id'] ?? null])) {
            throw new RuntimeException(sprintf('Award "%s" references an unknown level.', $award['name'] ?? ''));
        }
    }

    return $catalog;
}

/**
 * @param array<int, array<string, mixed>> $rows
 */
function assertUniqueCatalogValues(array $rows, string $key, string $label): void
{
    $values = array_column($rows, $key);
    if (count($values) !== count(array_unique($values, SORT_REGULAR))) {
        throw new RuntimeException("Award catalog contains a duplicate {$label}.");
    }
}

/**
 * @param array<string, mixed> $catalog
 */
function catalogSummary(string $action, array $catalog): string
{
    return sprintf(
        "%s (%d domains, %d levels, %d production awards)\n",
        $action,
        count($catalog['domains']),
        count($catalog['levels']),
        count($catalog['awards']),
    );
}

/**
 * @param array<string, mixed> $catalog
 */
function managedCatalogSql(array $catalog, string $dialect): string
{
    $quote = $dialect === 'mysql' ? '`' : '"';
    $domainRows = [];
    foreach ($catalog['domains'] as $domain) {
        $domainRows[] = sprintf(
            '(%d,%s,%s,%s,1,1,NULL)',
            $domain['id'],
            sqlLiteral($domain['name'], $dialect),
            sqlLiteral(AWARD_CATALOG_TIMESTAMP, $dialect),
            sqlLiteral(AWARD_CATALOG_TIMESTAMP, $dialect),
        );
    }

    $levelRows = [];
    foreach ($catalog['levels'] as $level) {
        $levelRows[] = sprintf(
            '(%d,%s,%s,%s,%s,1,1,NULL)',
            $level['id'],
            sqlLiteral($level['name'], $dialect),
            sqlLiteral($level['progression_order'], $dialect),
            sqlLiteral(AWARD_CATALOG_TIMESTAMP, $dialect),
            sqlLiteral(AWARD_CATALOG_TIMESTAMP, $dialect),
        );
    }

    $awardRows = [];
    foreach ($catalog['awards'] as $award) {
        $awardRows[] = sprintf(
            '(%d,%s,%s,%s,%s,%s,%s,%s,%d,%d,%d,%s,%s,1,1,NULL)',
            $award['target_id'],
            sqlLiteral($award['name'], $dialect),
            sqlLiteral($award['abbreviation'], $dialect),
            sqlLiteral($award['specialties'], $dialect),
            sqlLiteral($award['description'], $dialect),
            sqlLiteral($award['insignia'], $dialect),
            sqlLiteral($award['badge'], $dialect),
            sqlLiteral($award['charter'], $dialect),
            $award['domain_id'],
            $award['level_id'],
            $award['branch_id'],
            sqlLiteral(AWARD_CATALOG_TIMESTAMP, $dialect),
            sqlLiteral(AWARD_CATALOG_TIMESTAMP, $dialect),
        );
    }

    $parts = [
        AWARD_CATALOG_BEGIN,
        '-- Functional catalog exported read-only from the Ansteorra production tenant.',
        managedUpsertSql('awards_domains', ['id', 'name', 'modified', 'created', 'created_by', 'modified_by', 'deleted'], $domainRows, ['name', 'modified', 'modified_by', 'deleted'], $dialect, $quote),
        managedUpsertSql('awards_levels', ['id', 'name', 'progression_order', 'modified', 'created', 'created_by', 'modified_by', 'deleted'], $levelRows, ['name', 'progression_order', 'modified', 'modified_by', 'deleted'], $dialect, $quote),
        managedUpsertSql(
            'awards_awards',
            ['id', 'name', 'abbreviation', 'specialties', 'description', 'insignia', 'badge', 'charter', 'domain_id', 'level_id', 'branch_id', 'modified', 'created', 'created_by', 'modified_by', 'deleted'],
            $awardRows,
            ['name', 'abbreviation', 'specialties', 'description', 'insignia', 'badge', 'charter', 'domain_id', 'level_id', 'branch_id', 'modified', 'modified_by', 'deleted'],
            $dialect,
            $quote,
        ),
    ];
    if ($dialect === 'postgres') {
        foreach (['awards_domains', 'awards_levels', 'awards_awards'] as $table) {
            $parts[] = sprintf(
                "SELECT setval(pg_get_serial_sequence('%1\$s', 'id'), (SELECT MAX(id) FROM %1\$s), true);",
                $table,
            );
        }
    }
    $parts[] = AWARD_CATALOG_END;

    return implode("\n\n", $parts);
}

/**
 * @param array<int, string> $columns
 * @param array<int, string> $rows
 * @param array<int, string> $updateColumns
 */
function managedUpsertSql(
    string $table,
    array $columns,
    array $rows,
    array $updateColumns,
    string $dialect,
    string $quote,
): string {
    $quotedColumns = array_map(static fn(string $column): string => $quote . $column . $quote, $columns);
    $sql = sprintf(
        'INSERT INTO %1$s%2$s%1$s (%3$s) VALUES' . "\n" . '%4$s' . "\n",
        $quote,
        $table,
        implode(',', $quotedColumns),
        implode(",\n", $rows),
    );
    if ($dialect === 'mysql') {
        $updates = array_map(
            static fn(string $column): string => sprintf('%1$s%2$s%1$s=VALUES(%1$s%2$s%1$s)', $quote, $column),
            $updateColumns,
        );

        return $sql . 'ON DUPLICATE KEY UPDATE ' . implode(',', $updates) . ';';
    }

    $updates = array_map(
        static fn(string $column): string => sprintf('%1$s%2$s%1$s=EXCLUDED.%1$s%2$s%1$s', $quote, $column),
        $updateColumns,
    );

    return $sql . sprintf('ON CONFLICT (%1$sid%1$s) DO UPDATE SET ', $quote) . implode(',', $updates) . ';';
}

/**
 * Render a scalar as a SQL literal for a generated seed snapshot.
 */
function sqlLiteral(mixed $value, string $dialect): string
{
    if ($value === null) {
        return 'NULL';
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    if (is_int($value) || is_float($value)) {
        return (string)$value;
    }

    $value = (string)$value;
    if ($dialect === 'mysql') {
        $value = strtr(
            $value,
            [
            '\\' => '\\\\',
            "\0" => '\\0',
            "\n" => '\\n',
            "\r" => '\\r',
            "\x1a" => '\\Z',
            "'" => "\\'",
            ],
        );

        return "'{$value}'";
    }
    if (str_contains($value, "\r") || str_contains($value, "\n")) {
        $value = strtr($value, ['\\' => '\\\\', "\r" => '\\r', "\n" => '\\n', "'" => "''"]);

        return "E'{$value}'";
    }

    return "'" . str_replace("'", "''", $value) . "'";
}

/**
 * Replace or append the generated catalog block without interpreting escapes.
 */
function replaceManagedBlock(string $contents, string $managedBlock): string
{
    $pattern = '/' . preg_quote(AWARD_CATALOG_BEGIN, '/') . '.*?' . preg_quote(AWARD_CATALOG_END, '/') . '/s';
    if (preg_match($pattern, $contents) === 1) {
        $updated = preg_replace_callback($pattern, static fn(): string => $managedBlock, $contents, 1);
        if ($updated === null) {
            throw new RuntimeException('Unable to replace the managed award catalog block.');
        }

        return $updated;
    }

    return rtrim($contents) . "\n\n" . $managedBlock . "\n";
}

/**
 * Describe the first difference in a stale managed catalog block.
 */
function describeManagedBlockDifference(string $contents, string $expected): string
{
    $pattern = '/' . preg_quote(AWARD_CATALOG_BEGIN, '/') . '.*?' . preg_quote(AWARD_CATALOG_END, '/') . '/s';
    if (preg_match($pattern, $contents, $matches) !== 1) {
        return 'managed block is absent';
    }

    $actual = $matches[0];
    $sharedLength = min(strlen($actual), strlen($expected));
    for ($offset = 0; $offset < $sharedLength; $offset++) {
        if ($actual[$offset] !== $expected[$offset]) {
            return sprintf(
                'first difference at byte %d (expected 0x%02x, found 0x%02x)',
                $offset,
                ord($expected[$offset]),
                ord($actual[$offset]),
            );
        }
    }

    return sprintf('length differs (expected %d bytes, found %d)', strlen($expected), strlen($actual));
}

/**
 * @param array<string, mixed> $catalog
 */
function applyCatalogToLocalDatabase(array $catalog): void
{
    $connection = localDatabaseConnection();
    assertCatalogDependencies($connection, $catalog);
    $connection->transactional(
        static function (Connection $connection) use ($catalog): void {
            foreach ($catalog['domains'] as $domain) {
                upsertById(
                    $connection,
                    'awards_domains',
                    (int)$domain['id'],
                    ['name' => $domain['name'], 'deleted' => null],
                );
            }
            foreach ($catalog['levels'] as $level) {
                upsertById(
                    $connection,
                    'awards_levels',
                    (int)$level['id'],
                    ['name' => $level['name'], 'progression_order' => $level['progression_order'], 'deleted' => null],
                );
            }
            foreach ($catalog['awards'] as $award) {
                assertAwardIdentityAvailable($connection, $award);
                upsertById(
                    $connection,
                    'awards_awards',
                    (int)$award['target_id'],
                    [
                    'name' => $award['name'],
                    'abbreviation' => $award['abbreviation'],
                    'specialties' => $award['specialties'],
                    'description' => $award['description'],
                    'insignia' => $award['insignia'],
                    'badge' => $award['badge'],
                    'charter' => $award['charter'],
                    'domain_id' => $award['domain_id'],
                    'level_id' => $award['level_id'],
                    'branch_id' => $award['branch_id'],
                    // Cake's positional binding converts false to an empty
                    // string, which PostgreSQL cannot cast to boolean. The
                    // database accepts the unambiguous 1/0 representation.
                    'is_active' => $award['is_active'] ? 1 : 0,
                    'approval_process_id' => $award['approval_process_id'],
                    'bestowal_todo_template_id' => $award['bestowal_todo_template_id'],
                    'deleted' => null,
                    ],
                );
            }

            if ($connection->getDriver() instanceof Postgres) {
                foreach (['awards_domains', 'awards_levels', 'awards_awards'] as $table) {
                    $connection->execute(
                        "SELECT setval(pg_get_serial_sequence('{$table}', 'id'), "
                        . "(SELECT MAX(id) FROM {$table}), true)",
                    );
                }
            }
        },
    );

    validateDatabaseCatalog($connection, $catalog);
}

/**
 * Bootstrap CakePHP and return a connection guarded to local database hosts.
 */
function localDatabaseConnection(): Connection
{
    include dirname(__DIR__, 2) . '/vendor/autoload.php';
    include dirname(__DIR__, 2) . '/config/bootstrap.php';

    $config = ConnectionManager::getConfig('default');
    $host = (string)($config['host'] ?? '');
    if (isset($config['url']) && is_string($config['url']) && $config['url'] !== '') {
        $urlHost = parse_url($config['url'], PHP_URL_HOST);
        if (is_string($urlHost)) {
            $host = $urlHost;
        }
    }
    if (!in_array(strtolower($host), ['127.0.0.1', 'db', 'localhost', 'mariadb', 'mysql', 'postgres'], true)) {
        throw new RuntimeException("Refusing to access non-local database host '{$host}'.");
    }

    return ConnectionManager::get('default');
}

/**
 * @param array<string, mixed> $catalog
 */
function assertCatalogDependencies(Connection $connection, array $catalog): void
{
    foreach (
        [
        'branches' => array_unique(array_column($catalog['awards'], 'branch_id')),
        'awards_approval_processes' => array_unique(array_column($catalog['awards'], 'approval_process_id')),
        'awards_bestowal_todo_templates' => array_unique(array_column($catalog['awards'], 'bestowal_todo_template_id')),
        ] as $table => $ids
    ) {
        foreach (array_filter($ids, static fn(mixed $id): bool => $id !== null) as $id) {
            if (!recordExists($connection, $table, (int)$id)) {
                throw new RuntimeException("Award catalog requires missing {$table} ID {$id}.");
            }
        }
    }
}

/**
 * @param array<string, mixed> $award
 */
function assertAwardIdentityAvailable(Connection $connection, array $award): void
{
    $idRow = fetchRow($connection, 'SELECT id, name FROM awards_awards WHERE id = ?', [$award['target_id']]);
    if ($idRow !== null && $idRow['name'] !== $award['name']) {
        throw new RuntimeException(
            sprintf(
                'Seed award ID %d belongs to "%s", not catalog award "%s".',
                $award['target_id'],
                $idRow['name'],
                $award['name'],
            ),
        );
    }
    $nameRow = fetchRow($connection, 'SELECT id FROM awards_awards WHERE name = ?', [$award['name']]);
    if ($nameRow !== null && (int)$nameRow['id'] !== (int)$award['target_id']) {
        throw new RuntimeException(
            sprintf(
                'Catalog award "%s" already uses seed ID %d instead of %d.',
                $award['name'],
                $nameRow['id'],
                $award['target_id'],
            ),
        );
    }
}

/**
 * @param array<string, mixed> $values
 */
function upsertById(Connection $connection, string $table, int $id, array $values): void
{
    if (recordExists($connection, $table, $id)) {
        $assignments = implode(', ', array_map(static fn(string $column): string => $column . ' = ?', array_keys($values)));
        $connection->execute(
            "UPDATE {$table} SET {$assignments} WHERE id = ?",
            [...array_values($values), $id],
        );

        return;
    }

    $columns = ['id', ...array_keys($values), 'created', 'created_by', 'modified_by'];
    $parameters = [$id, ...array_values($values), AWARD_CATALOG_TIMESTAMP, 1, 1];
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $connection->execute(
        sprintf('INSERT INTO %s (%s) VALUES (%s)', $table, implode(', ', $columns), $placeholders),
        $parameters,
    );
}

/**
 * Check whether a catalog dependency exists by primary key.
 */
function recordExists(Connection $connection, string $table, int $id): bool
{
    return fetchRow($connection, "SELECT id FROM {$table} WHERE id = ?", [$id]) !== null;
}

/**
 * @param array<int, mixed> $parameters
 * @return array<string, mixed>|null
 */
function fetchRow(Connection $connection, string $sql, array $parameters): ?array
{
    $row = $connection->execute($sql, $parameters)->fetch('assoc');

    return is_array($row) ? $row : null;
}

/**
 * @param array<string, mixed> $catalog
 */
function validateDatabaseCatalog(Connection $connection, array $catalog): void
{
    foreach ($catalog['domains'] as $domain) {
        $row = fetchRow($connection, 'SELECT name, deleted FROM awards_domains WHERE id = ?', [$domain['id']]);
        if ($row === null || $row['name'] !== $domain['name'] || $row['deleted'] !== null) {
            throw new RuntimeException("Local award domain {$domain['id']} does not match the catalog.");
        }
    }
    foreach ($catalog['levels'] as $level) {
        $row = fetchRow(
            $connection,
            'SELECT name, progression_order, deleted FROM awards_levels WHERE id = ?',
            [$level['id']],
        );
        if (
            $row === null
            || $row['name'] !== $level['name']
            || (int)$row['progression_order'] !== (int)$level['progression_order']
            || $row['deleted'] !== null
        ) {
            throw new RuntimeException("Local award level {$level['id']} does not match the catalog.");
        }
    }
    foreach ($catalog['awards'] as $award) {
        $row = fetchRow(
            $connection,
            'SELECT name, abbreviation, specialties, description, insignia, badge, charter, domain_id, level_id, '
            . 'branch_id, is_active, approval_process_id, bestowal_todo_template_id, deleted '
            . 'FROM awards_awards WHERE id = ?',
            [$award['target_id']],
        );
        if ($row === null) {
            throw new RuntimeException("Local award {$award['target_id']} is missing.");
        }
        foreach (['name', 'abbreviation', 'specialties', 'description', 'insignia', 'badge', 'charter'] as $field) {
            if ($row[$field] !== $award[$field]) {
                throw new RuntimeException("Local award {$award['target_id']} field {$field} does not match.");
            }
        }
        foreach (['domain_id', 'level_id', 'branch_id', 'approval_process_id', 'bestowal_todo_template_id'] as $field) {
            if (($row[$field] === null ? null : (int)$row[$field]) !== ($award[$field] === null ? null : (int)$award[$field])) {
                throw new RuntimeException("Local award {$award['target_id']} field {$field} does not match.");
            }
        }
        if ((bool)$row['is_active'] !== (bool)$award['is_active'] || $row['deleted'] !== null) {
            throw new RuntimeException("Local award {$award['target_id']} active state does not match.");
        }
    }
}
