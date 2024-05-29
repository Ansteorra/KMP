<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\NotesTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\NotesTable Test Case
 */
class NotesTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\NotesTable
     */
    protected $Notes;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = ["app.Notes"];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists("Notes")
            ? []
            : ["className" => NotesTable::class];
        $this->Notes = $this->getTableLocator()->get("Notes", $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->Notes);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\NotesTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete("Not implemented yet.");
    }
}
