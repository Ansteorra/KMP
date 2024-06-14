<?php
declare(strict_types=1);

namespace App\Test\TestCase\Command;

use App\Command\UpdateDatabaseCommand;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * App\Command\UpdateDatabaseCommand Test Case
 *
 * @uses \App\Command\UpdateDatabaseCommand
 */
class UpdateDatabaseCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    /**
     * Test buildOptionParser method
     *
     * @return void
     * @uses \App\Command\UpdateDatabaseCommand::buildOptionParser()
     */
    public function testBuildOptionParser(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test execute method
     *
     * @return void
     * @uses \App\Command\UpdateDatabaseCommand::execute()
     */
    public function testExecute(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
