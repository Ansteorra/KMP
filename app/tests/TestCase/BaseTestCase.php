<?php

declare(strict_types=1);

namespace App\Test\TestCase;

use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;

/**
 * BaseTestCase
 *
 * Base test case class for all KMP tests. Provides:
 * - Automatic database transaction wrapping for test isolation
 * - Centralized test data ID constants from dev_seed_clean.sql
 * - Common helper methods for testing
 *
 * ## Transaction Isolation
 *
 * All tests automatically run within a database transaction that is
 * rolled back after each test completes. This ensures:
 * - Tests start with clean state from dev_seed_clean.sql
 * - Tests don't affect each other
 * - No need to reset database between tests
 * - Fast test execution
 *
 * ## Test Data Constants
 *
 * Use these constants instead of hardcoding IDs in tests.
 * These IDs are stable in dev_seed_clean.sql.
 *
 * Example:
 * ```php
 * $member = $this->Members->get(self::ADMIN_MEMBER_ID);
 * $branch = $this->Branches->get(self::KINGDOM_BRANCH_ID);
 * ```
 *
 * ## Usage
 *
 * ```php
 * use App\Test\TestCase\BaseTestCase;
 *
 * class MembersTableTest extends BaseTestCase
 * {
 *     public function testFindActive(): void
 *     {
 *         $members = $this->getTableLocator()->get('Members');
 *         $active = $members->find('active')->toArray();
 *         $this->assertNotEmpty($active);
 *     }
 * }
 * ```
 *
 * @uses \Cake\TestSuite\TestCase
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
     * Name: Kingdom of Ansteorra
     * Type: kingdom
     */
    public const KINGDOM_BRANCH_ID = 1;

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
     * Test branch for demo members
     * Branch ID used in test member role assignments
     */
    public const TEST_BRANCH_LOCAL_ID = 1073;

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
     * Start a database transaction for test isolation
     *
     * This wraps all database operations in a transaction that will be
     * rolled back after the test completes, ensuring tests don't affect
     * each other.
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
     * Rollback the database transaction
     *
     * This undoes all database changes made during the test, returning
     * the database to its initial state from dev_seed_clean.sql.
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
     * Disable automatic transaction wrapping for this test
     *
     * Call this method in your test's setUp() if you need to test
     * transaction behavior or need multiple transactions.
     *
     * Example:
     * ```php
     * protected function setUp(): void
     * {
     *     parent::setUp();
     *     $this->disableTransactions();
     * }
     * ```
     *
     * @return void
     */
    protected function disableTransactions(): void
    {
        if ($this->transactionStarted) {
            $this->rollbackTransaction();
        }
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
