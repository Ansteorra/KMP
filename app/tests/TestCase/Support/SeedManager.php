<?php
declare(strict_types=1);

namespace App\Test\TestCase\Support;

use Cake\Database\Connection;
use Cake\Database\Driver\Postgres;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\Fixture\SchemaLoader;
use Exception;
use InitWorkflowDefinitionsSeed;
use Migrations\Migrations;
use RuntimeException;

/**
 * SeedManager
 *
 * Handles loading the shared dev seed SQL file for the test connection.
 * Provides helpers for one-time bootstrap seeding and ad-hoc resets.
 */
final class SeedManager
{
    /**
     * Absolute path to the shared SQL seed file.
     */
    private const SEED_FILENAME = 'dev_seed_clean.sql';

    /**
     * PostgreSQL-compatible data-only seed generated from dev_seed_clean.sql.
     */
    private const POSTGRES_SEED_FILENAME = 'pg_seed.sql';

    /**
     * Tracks whether the seed has been applied during the current process.
     */
    private static bool $seedLoaded = false;

    /**
     * Ensure the test database is seeded before executing the test suite.
     *
     * @param string $connection Connection name to seed (defaults to `test`).
     * @return void
     */
    public static function bootstrap(string $connection = 'test'): void
    {
        if (self::$seedLoaded) {
            return;
        }

        if (self::isPostgres($connection)) {
            self::loadPostgresSeed($connection);
        } else {
            self::loadSeed($connection);
        }
        self::$seedLoaded = true;
    }

    /**
     * Force a reseed of the database, useful for data-reset heavy scenarios.
     *
     * @param string $connection Connection name to seed (defaults to `test`).
     * @return void
     */
    public static function reset(string $connection = 'test'): void
    {
        if (self::isPostgres($connection)) {
            self::loadPostgresSeed($connection);

            return;
        }

        self::loadSeed($connection);
    }

    /**
     * Return true if the connection uses the Postgres driver.
     *
     * @param string $connection Connection name.
     * @return bool
     */
    public static function isPostgres(string $connection): bool
    {
        try {
            $conn = ConnectionManager::get($connection);

            return $conn->getDriver() instanceof Postgres;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Resolve the absolute path to the dev seed file.
     *
     * @return string
     */
    private static function seedPath(): string
    {
        $seedPath = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . self::SEED_FILENAME;

        if (!is_file($seedPath)) {
            throw new RuntimeException(sprintf('Unable to locate seed file at %s', $seedPath));
        }

        return $seedPath;
    }

    /**
     * Execute the SQL seed file against the desired connection.
     *
     * @param string $connection Connection name.
     * @return void
     */
    private static function loadSeed(string $connection): void
    {
        $seedPath = self::seedPath();

        $loader = new SchemaLoader();
        $loader->loadSqlFiles([$seedPath], $connection);
    }

    /**
     * Execute the PostgreSQL-compatible data seed.
     *
     * @param string $connection Connection name.
     * @return void
     */
    private static function loadPostgresSeed(string $connection): void
    {
        $seedPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . self::POSTGRES_SEED_FILENAME;

        if (!is_file($seedPath)) {
            throw new RuntimeException(sprintf('Unable to locate PostgreSQL seed file at %s', $seedPath));
        }

        $sql = file_get_contents($seedPath);
        if ($sql === false) {
            throw new RuntimeException(sprintf('Unable to read PostgreSQL seed file at %s', $seedPath));
        }

        preg_match_all('/INSERT\s+INTO\s+"([^"]+)"/i', $sql, $matches);
        $seededTables = array_values(array_unique($matches[1] ?? []));
        if ($seededTables !== []) {
            $conn = ConnectionManager::get($connection);
            $quotedTables = array_map(
                static fn(string $table): string => '"' . str_replace('"', '""', $table) . '"',
                $seededTables,
            );
            $conn->getDriver()->exec(
                sprintf('TRUNCATE TABLE %s RESTART IDENTITY CASCADE', implode(', ', $quotedTables)),
            );
            $conn->getDriver()->exec($sql);
            self::seedPostgresWorkflowDefinitions($conn);
            self::seedPostgresBestowalReference($conn);

            return;
        }

        $conn = ConnectionManager::get($connection);
        $conn->getDriver()->exec($sql);
        self::seedPostgresWorkflowDefinitions($conn);
        self::seedPostgresBestowalReference($conn);
    }

    /**
     * Seed configuration data that normally comes from data-bearing migrations.
     *
     * PostgreSQL tests import a current schema dump, so those migrations do not
     * execute again. Keep their configuration seed data available explicitly.
     *
     * @param \Cake\Database\Connection $conn Test database connection.
     * @return void
     */
    private static function seedPostgresWorkflowDefinitions(Connection $conn): void
    {
        $count = (int)$conn->execute('SELECT count(*) FROM workflow_definitions')->fetchColumn(0);
        if ($count > 0) {
            return;
        }

        $seedPath = dirname(__DIR__, 3) . '/config/Seeds/InitWorkflowDefinitionsSeed.php';
        $jsonDir = dirname($seedPath) . '/WorkflowDefinitions/';
        require_once $seedPath;

        $seed = new InitWorkflowDefinitionsSeed();
        $now = date('Y-m-d H:i:s');
        foreach ($seed->getWorkflowMeta() as $meta) {
            $jsonPath = $jsonDir . $meta['json_file'];
            $definitionJson = file_get_contents($jsonPath);
            if ($definitionJson === false) {
                throw new RuntimeException(sprintf('Workflow definition file not found: %s', $jsonPath));
            }

            $decoded = json_decode($definitionJson, true);
            if (!is_array($decoded)) {
                throw new RuntimeException(sprintf('Invalid workflow definition JSON: %s', $jsonPath));
            }

            $definitionId = (int)$conn->execute(
                'INSERT INTO workflow_definitions (
                    name, slug, description, trigger_type, trigger_config, entity_type,
                    is_active, execution_mode, current_version_id, created_by, modified_by, created, modified
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, 1, 1, ?, ?)
                RETURNING id',
                [
                    $meta['name'],
                    $meta['slug'],
                    $meta['description'],
                    $meta['trigger_type'],
                    json_encode($meta['trigger_config']),
                    $meta['entity_type'],
                    !empty($meta['is_active']) ? 'true' : 'false',
                    $meta['execution_mode'] ?? 'durable',
                    $now,
                    $now,
                ],
            )->fetchColumn(0);

            $versionId = (int)$conn->execute(
                'INSERT INTO workflow_versions (
                    workflow_definition_id, version_number, definition, canvas_layout, status,
                    published_at, published_by, change_notes, created_by, created, modified
                ) VALUES (?, 1, ?, ?, ?, ?, 1, ?, 1, ?, ?)
                RETURNING id',
                [
                    $definitionId,
                    json_encode($decoded),
                    '{}',
                    'published',
                    $now,
                    'Initial test seed version',
                    $now,
                    $now,
                ],
            )->fetchColumn(0);

            $conn->execute(
                'UPDATE workflow_definitions SET current_version_id = ? WHERE id = ?',
                [$versionId, $definitionId],
            );
        }
    }

    /**
     * Seed Awards bestowal state-machine configuration for PostgreSQL tests.
     *
     * @param \Cake\Database\Connection $conn Test database connection.
     * @return void
     */
    private static function seedPostgresBestowalReference(Connection $conn): void
    {
        $count = (int)$conn->execute('SELECT count(*) FROM awards_bestowal_statuses')->fetchColumn(0);
        if ($count > 0) {
            return;
        }

        (new Migrations())->seed([
            'connection' => $conn->configName(),
            'plugin' => 'Awards',
            'seed' => 'InitBestowalReferenceSeed',
        ]);
    }
}
