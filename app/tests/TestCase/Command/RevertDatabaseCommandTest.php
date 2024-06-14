<?php
declare(strict_types=1);

namespace App\Test\TestCase\Command;

use App\Command\RevertDatabaseCommand;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * App\Command\RevertDatabaseCommand Test Case
 *
 * @uses \App\Command\RevertDatabaseCommand
 */
class RevertDatabaseCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    /**
     * Test buildOptionParser method
     *
     * @return void
     * @uses \App\Command\RevertDatabaseCommand::buildOptionParser()
     */
    public function testBuildOptionParser(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test execute method
     *
     * @return void
     * @uses \App\Command\RevertDatabaseCommand::execute()
     */
    public function testExecute(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
