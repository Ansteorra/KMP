<?php
declare(strict_types=1);

namespace Queue\Test\TestCase\Queue;

use Cake\TestSuite\TestCase;
use Queue\Queue\Task\EmailTask;
use Queue\Queue\TaskFinder;

class TaskFinderTest extends TestCase {

	/**
	 * @var \Queue\Queue\TaskFinder
	 */
	protected $taskFinder;

	/**
	 * @return void
	 */
	public function testAllAppAndPluginTasks() {
		$this->taskFinder = new TaskFinder();

		$result = $this->taskFinder->all();

		$this->assertArrayHasKey('Queue.Email', $result);
		$this->assertArrayHasKey('Queue.Mailer', $result);
	}

	/**
	 * @return void
	 */
	public function testResolve(): void {
		$this->taskFinder = new TaskFinder();

		$result = $this->taskFinder->resolve('Queue.Email');
		$this->assertSame('Queue.Email', $result);

		$result = $this->taskFinder->resolve(EmailTask::class);
		$this->assertSame('Queue.Email', $result);

		$result = $this->taskFinder->resolve(EmailTask::taskName());
		$this->assertSame('Queue.Email', $result);
	}

	/**
	 * @return void
	 */
	public function testClassName(): void {
		$this->taskFinder = new TaskFinder();

		$class = $this->taskFinder->getClass('Queue.Email');
		$this->assertSame(EmailTask::class, $class);
	}

}
