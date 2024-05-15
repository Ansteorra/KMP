<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\AppSettingsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\AppSettingsTable Test Case
 */
class AppSettingsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\AppSettingsTable
     */
    protected $AppSettings;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = [
        'app.AppSettings',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('AppSettings') ? [] : ['className' => AppSettingsTable::class];
        $this->AppSettings = $this->getTableLocator()->get('AppSettings', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->AppSettings);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\AppSettingsTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
