<?php

declare(strict_types=1);

namespace Queue\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * @uses \Queue\Command\InfoCommand
 */
class InfoCommandTest extends TestCase
{

	use ConsoleIntegrationTestTrait;

	/**
	 * @return void
	 */
	public function setUp(): void
	{
		parent::setUp();
	}

	/**
	 * @return void
	 */
	public function testExecute(): void
	{
		$this->exec('queue info');

		$output = $this->_out->output();
		$this->assertStringContainsString('2 tasks available:', $output);
		$this->assertExitCode(0);
	}
}
