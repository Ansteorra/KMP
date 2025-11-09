<?php

declare(strict_types=1);

namespace App\Test\TestCase;

use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\Fixture\SchemaLoader;

/**
 * TestDatabaseTrait
 * 
 * Trait to help manage test database state.
 * Provides methods to reset database to a clean state between tests.
 */
trait TestDatabaseTrait
{
    /**
     * Track if we're in a transaction
     */
    protected bool $_inTransaction = false;

    /**
     * Start a database transaction before test
     * 
     * @return void
     */
    protected function startDatabaseTransaction(): void
    {
        if (!$this->_inTransaction) {
            $connection = ConnectionManager::get('test');
            $connection->begin();
            $this->_inTransaction = true;
        }
    }

    /**
     * Rollback database transaction after test
     * 
     * @return void
     */
    protected function rollbackDatabaseTransaction(): void
    {
        if ($this->_inTransaction) {
            $connection = ConnectionManager::get('test');
            $connection->rollback();
            $this->_inTransaction = false;
        }
    }

    /**
     * Reset the test database to clean state
     * 
     * WARNING: This is expensive. Only use when necessary.
     * 
     * @return void
     */
    protected function resetTestDatabase(): void
    {
        $loader = new SchemaLoader();
        $loader->loadSqlFiles('../dev_seed_clean.sql', 'test');
    }

    /**
     * Insert test-specific data
     * 
     * @param string $table Table name
     * @param array $data Data to insert
     * @return void
     */
    protected function insertTestData(string $table, array $data): void
    {
        $connection = ConnectionManager::get('test');
        $connection->insert($table, $data);
    }

    /**
     * Clean up a specific table for testing
     * 
     * @param string $table Table name
     * @param array $where Where conditions (optional)
     * @return void
     */
    protected function cleanTable(string $table, array $where = []): void
    {
        $connection = ConnectionManager::get('test');
        if (empty($where)) {
            $connection->execute("TRUNCATE TABLE `{$table}`");
        } else {
            $connection->delete($table, $where);
        }
    }
}
