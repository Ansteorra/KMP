<?php

declare(strict_types=1);

namespace Queue\Test\TestCase\Controller\Admin;

use App\Test\TestCase\TestAuthenticationHelper;
use Cake\I18n\DateTime;
use Cake\TestSuite\IntegrationTestTrait;
use Queue\Model\Table\QueuedJobsTable;
use Shim\TestSuite\TestCase;

/**
 * @uses \Queue\Controller\QueueProcessesController
 */
class QueueProcessesControllerTest extends TestCase
{

	use IntegrationTestTrait;
	use TestAuthenticationHelper;

	/**
	 * @var array<string>
	 */
	protected array $fixtures = [
		'plugin.Queue.QueuedJobs',
		'plugin.Queue.QueueProcesses',
	];

	/**
	 * @return void
	 */
	public function setUp(): void
	{
		parent::setUp();

		$this->disableErrorHandlerMiddleware();
		$this->authenticateAsSuperUser();
		$this->enableCsrfToken();
		$this->enableSecurityToken();
	}

	/**
	 * Test index method
	 *
	 * @return void
	 */
	public function testIndex()
	{
		$this->get(['plugin' => 'Queue', 'controller' => 'QueueProcesses', 'action' => 'index']);

		$this->assertResponseCode(200);
	}

	/**
	 * Test view method
	 *
	 * @return void
	 */
	public function testView()
	{
		$queueProcess = $this->getTableLocator()->get('Queue.QueueProcesses')->find()->firstOrFail();

		$this->get(['plugin' => 'Queue', 'controller' => 'QueueProcesses', 'action' => 'view', $queueProcess->id]);

		$this->assertResponseCode(200);
	}

	/**
	 * Test edit method
	 *
	 * @return void
	 */
	public function testEdit()
	{
		$queueProcess = $this->getTableLocator()->get('Queue.QueueProcesses')->find()->firstOrFail();

		$this->get(['plugin' => 'Queue', 'controller' => 'QueueProcesses', 'action' => 'edit', $queueProcess->id]);

		$this->assertResponseCode(200);
	}

	/**
	 * @return void
	 */
	public function testTerminate()
	{
		/** @var \Queue\Model\Entity\QueueProcess $queueProcess */
		$queueProcess = $this->getTableLocator()->get('Queue.QueueProcesses')->find()->firstOrFail();
		$queueProcess->terminate = false;
		$this->getTableLocator()->get('Queue.QueueProcesses')->saveOrFail($queueProcess);

		$this->post(['plugin' => 'Queue', 'controller' => 'QueueProcesses', 'action' => 'terminate', $queueProcess->id]);

		$this->assertResponseCode(302);

		$queueProcess = $this->getTableLocator()->get('Queue.QueueProcesses')->find()->firstOrFail();
		$this->assertTrue($queueProcess->terminate);
	}

	/**
	 * @return void
	 */
	public function testDelete()
	{
		$queueProcess = $this->getTableLocator()->get('Queue.QueueProcesses')->find()->firstOrFail();

		$this->post(['plugin' => 'Queue', 'controller' => 'QueueProcesses', 'action' => 'delete', $queueProcess->id]);

		$this->assertResponseCode(302);

		$count = $this->getTableLocator()->get('Queue.QueueProcesses')->find()->count();
		$this->assertSame(0, $count);
	}

	/**
	 * @return void
	 */
	public function testCleanup()
	{
		/** @var \Queue\Model\Entity\QueueProcess $queueProcess */
		$queueProcess = $this->getTableLocator()->get('Queue.QueueProcesses')->find()->firstOrFail();
		$queueProcess->modified = new DateTime(time() - 4 * QueuedJobsTable::DAY);
		$this->getTableLocator()->get('Queue.QueueProcesses')->saveOrFail($queueProcess);

		$this->post(['plugin' => 'Queue', 'controller' => 'QueueProcesses', 'action' => 'cleanup']);

		$this->assertResponseCode(302);

		$count = $this->getTableLocator()->get('Queue.QueueProcesses')->find()->count();
		$this->assertSame(0, $count);
	}
}
