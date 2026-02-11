<?php
declare(strict_types=1);

namespace Queue\Test\TestCase\Queue;

use Cake\TestSuite\TestCase;
use Queue\Queue\Task\EmailTask;
use Queue\Queue\Task\MailerTask;

class TaskTest extends TestCase {

	/**
	 * @return void
	 */
	public function testTaskName() {
		$name = MailerTask::taskName();
		$this->assertSame('Queue.Mailer', $name);

		$name = EmailTask::taskName();
		$this->assertSame('Queue.Email', $name);
	}

}
