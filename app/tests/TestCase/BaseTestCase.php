<?php

declare(strict_types=1);

namespace App\Test\TestCase;

use App\Test\TestCase\Support\SeedManager;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;

/**
 * BaseTestCase - Base class for all KMP tests.
 *
 * Provides automatic database transaction wrapping for test isolation and
 * centralized test data ID constants from dev_seed_clean.sql.
 */
abstract class BaseTestCase extends TestCase
{
    // ==================================================
    // MEMBER TEST DATA CONSTANTS
    // ==================================================

    /**
     * Admin member with full super user permissions
     * Email: admin@amp.ansteorra.org
     * SCA Name: Admin von Admin
     * Status: verified
     */
    public const ADMIN_MEMBER_ID = 1;

    // ==================================================
    // BRANCH TEST DATA CONSTANTS
    // ==================================================

    /**
     * Kingdom branch (root of tree)
     * Name: Ansteorra
     * Type: Kingdom
     */
    public const KINGDOM_BRANCH_ID = 2;

    /**
     * Synthetic test member - Local MoAS role
     * Email: agatha@ampdemo.com
     * SCA Name: Agatha Local MoAS Demoer
     */
    public const TEST_MEMBER_AGATHA_ID = 2871;

    /**
     * Synthetic test member - Local Seneschal role (Regional Officer Management)
     * Email: bryce@ampdemo.com
     * SCA Name: Bryce Local Seneschal Demoer
     * Branch: Barony of Stargate (ID 39)
     */
    public const TEST_MEMBER_BRYCE_ID = 2872;

    /**
     * Synthetic test member - Regional Armored Marshal
     * Email: devon@ampdemo.com  
     * SCA Name: Devon Regional Armored Demoer
     * Has roles in Central Region (12) and Southern Region (13)
     */
    public const TEST_MEMBER_DEVON_ID = 2874;

    /**
     * Synthetic test member - Kingdom Seneschal
     * Email: eirik@ampdemo.com
     * SCA Name: Eirik Kingdom Seneschal Demoer
     * Branch: Ansteorra (ID 2) - Greater Officer of State
     */
    public const TEST_MEMBER_EIRIK_ID = 2875;

    /**
     * Shire of Adlersruhe - a local group branch
     * Name: Shire of Adlersruhe
     * Type: Local Group
     */
    public const TEST_BRANCH_LOCAL_ID = 14;

    /**
     * Barony of Stargate - Bryce's branch
     */
    public const TEST_BRANCH_STARGATE_ID = 39;

    /**
     * Central Region - Devon's region
     */
    public const TEST_BRANCH_CENTRAL_REGION_ID = 12;

    /**
     * Southern Region - Devon's region
     */
    public const TEST_BRANCH_SOUTHERN_REGION_ID = 13;

    // ==================================================
    // ROLE TEST DATA CONSTANTS
    // ==================================================

    /**
     * Admin role with super user permission
     * Name: Admin
     */
    public const ADMIN_ROLE_ID = 1;

    // ==================================================
    // PERMISSION TEST DATA CONSTANTS
    // ==================================================

    /**
     * Super user permission (grants all access)
     * Name: Is Super User
     * Flag: is_super_user = 1
     */
    public const SUPER_USER_PERMISSION_ID = 1;

    // ==================================================
    // TRANSACTION MANAGEMENT
    // ==================================================

    /**
     * Database connection for transaction management
     *
     * @var \Cake\Database\Connection|null
     */
    protected $connection = null;

    /**
     * Whether a transaction is currently active
     *
     * @var bool
     */
    protected $transactionStarted = false;

    /**
     * Set up test - automatically start database transaction
     *
     * Override this method in your test class if you need custom setup,
     * but always call parent::setUp() first to ensure transaction begins.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->startTransaction();
    }

    /**
     * Tear down test - automatically rollback database transaction
     *
     * Override this method in your test class if you need custom teardown,
     * but always call parent::tearDown() last to ensure transaction rolls back.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->rollbackTransaction();
        parent::tearDown();
    }

    /**
     * Start a database transaction for test isolation.
     *
     * @return void
     */
    protected function startTransaction(): void
    {
        if (!$this->transactionStarted) {
            $this->connection = ConnectionManager::get('test');
            $this->connection->begin();
            $this->transactionStarted = true;
        }
    }

    /**
     * Rollback the database transaction.
     *
     * @return void
     */
    protected function rollbackTransaction(): void
    {
        if ($this->transactionStarted && $this->connection) {
            $this->connection->rollback();
            $this->transactionStarted = false;
        }
    }

    /**
     * Disable automatic transaction wrapping for this test.
     *
     * @return void
     */
    protected function disableTransactions(): void
    {
        if ($this->transactionStarted) {
            $this->rollbackTransaction();
        }
    }

    /**
     * Force a dev seed reload for scenarios that mutate lots of data.
     *
     * @return void
     */
    protected function reseedDatabase(): void
    {
        SeedManager::reset('test');
        $this->transactionStarted = false;
    }

    // ==================================================
    // HELPER METHODS
    // ==================================================

    /**
     * Assert that a record exists in the database
     *
     * @param string $table Table name
     * @param array<string, mixed> $conditions Conditions to find the record
     * @param string $message Optional assertion message
     * @return void
     */
    protected function assertRecordExists(string $table, array $conditions, string $message = ''): void
    {
        $tableObject = $this->getTableLocator()->get($table);
        $record = $tableObject->find()->where($conditions)->first();

        if (empty($message)) {
            $conditionStr = json_encode($conditions);
            $message = "Record matching {$conditionStr} should exist in {$table}";
        }

        $this->assertNotNull($record, $message);
    }

    /**
     * Assert that a record does not exist in the database
     *
     * @param string $table Table name
     * @param array<string, mixed> $conditions Conditions to find the record
     * @param string $message Optional assertion message
     * @return void
     */
    protected function assertRecordNotExists(string $table, array $conditions, string $message = ''): void
    {
        $tableObject = $this->getTableLocator()->get($table);
        $record = $tableObject->find()->where($conditions)->first();

        if (empty($message)) {
            $conditionStr = json_encode($conditions);
            $message = "Record matching {$conditionStr} should not exist in {$table}";
        }

        $this->assertNull($record, $message);
    }

    /**
     * Assert that a table has a specific number of records
     *
     * @param string $table Table name
     * @param int $expectedCount Expected number of records
     * @param array<string, mixed> $conditions Optional conditions
     * @param string $message Optional assertion message
     * @return void
     */
    protected function assertRecordCount(
        string $table,
        int $expectedCount,
        array $conditions = [],
        string $message = ''
    ): void {
        $tableObject = $this->getTableLocator()->get($table);
        $query = $tableObject->find();

        if (!empty($conditions)) {
            $query->where($conditions);
        }

        $actualCount = $query->count();

        if (empty($message)) {
            $message = "Table {$table} should have {$expectedCount} records";
        }

        $this->assertEquals($expectedCount, $actualCount, $message);
    }
}
