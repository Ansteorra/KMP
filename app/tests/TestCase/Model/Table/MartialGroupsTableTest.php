<?php

declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\ActivityGroupsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\ActivityGroupsTable Test Case
 */
class ActivityGroupsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\ActivityGroupsTable
     */
    protected $ActivityGroups;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = ["app.ActivityGroups"];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists("ActivityGroups")
            ? []
            : ["className" => ActivityGroupsTable::class];
        $this->ActivityGroups = $this->getTableLocator()->get(
            "ActivityGroups",
            $config,
        );
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->ActivityGroups);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\ActivityGroupsTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete("Not implemented yet.");
    }
}
