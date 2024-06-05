<?php

declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\ActivitiesTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\ActivitiesTable Test Case
 */
class ActivitiesTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\ActivitiesTable
     */
    protected $Activities;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = [
        "app.Activities",
        "app.ActivityGroups",
        "app.MemberActivities",
        "app.PendingAuthorizations",
        "app.Permissions",
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists("Activities")
            ? []
            : ["className" => ActivitiesTable::class];
        $this->Activities = $this->getTableLocator()->get(
            "Activities",
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
        unset($this->Activities);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\ActivitiesTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete("Not implemented yet.");
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @uses \App\Model\Table\ActivitiesTable::buildRules()
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete("Not implemented yet.");
    }
}
