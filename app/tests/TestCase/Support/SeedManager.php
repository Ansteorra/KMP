<?php

declare(strict_types=1);

namespace App\Test\TestCase\Support;

use Cake\Database\Driver\Postgres;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\Fixture\SchemaLoader;
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

        // dev_seed_clean.sql is a MariaDB dump — not compatible with Postgres.
        // For Postgres, migrations create the schema; we skip the MySQL seed.
        if (self::isPostgres($connection)) {
            self::$seedLoaded = true;
            return;
        }

        self::loadSeed($connection);
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
    private static function isPostgres(string $connection): bool
    {
        try {
            $conn = ConnectionManager::get($connection);

            return $conn->getDriver() instanceof Postgres;
        } catch (\Exception $e) {
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
}
