<?php
declare(strict_types=1);

namespace Officers\Test\TestCase\Controller;

use App\Services\ServiceResult;
use App\Services\WarrantManager\WarrantManagerInterface;
use App\Services\WorkflowEngine\TriggerDispatcher;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\Core\ContainerInterface as CakeContainerInterface;
use Cake\Event\EventInterface;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Closure;
use Exception;
use Officers\Model\Entity\Officer;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use ReflectionProperty;

/**
 * Tests workflow dispatch in OfficersController.
 *
 * Verifies that assign() and release() route through TriggerDispatcher
 * and that requestWarrant() delegates to the warrant manager workflow trigger path.
 *
 * @uses \Officers\Controller\OfficersController
 */
class OfficersWorkflowDispatchTest extends HttpIntegrationTestCase
{
    protected $Officers;
    protected $Offices;
    protected $WorkflowDefinitions;
    protected $WorkflowVersions;

    /**
     * Keys of mocked services that need DI argument clearing.
     */
    private array $mockedServiceKeys = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->enableRetainFlashMessages();
        $this->authenticateAsSuperUser();

        $this->Officers = TableRegistry::getTableLocator()->get('Officers.Officers');
        $this->Offices = TableRegistry::getTableLocator()->get('Officers.Offices');
        $this->WorkflowDefinitions = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $this->WorkflowVersions = TableRegistry::getTableLocator()->get('WorkflowVersions');

        // Provide ContainerInterface so the DI chain for WorkflowEngine
        // and TriggerDispatcher can resolve without hitting unresolvable deps.
        $this->mockServiceClean(CakeContainerInterface::class, function () {
            return $this->createMock(CakeContainerInterface::class);
        });
    }

    protected function tearDown(): void
    {
        unset($this->Officers, $this->Offices, $this->WorkflowDefinitions, $this->WorkflowVersions);
        $this->mockedServiceKeys = [];
        parent::tearDown();
    }

    /**
     * Override modifyContainer to clear stale DI arguments after setConcrete.
     *
     * League Container's extend()->setConcrete() keeps original addArgument()
     * entries, causing unwanted dependency resolution. This override clears
     * those arguments for services we've replaced with mocks.
     */
    public function modifyContainer(EventInterface $event, PsrContainerInterface $container): void
    {
        parent::modifyContainer($event, $container);

        foreach ($this->mockedServiceKeys as $key) {
            if ($container->has($key)) {
                try {
                    $def = $container->extend($key);
                    $ref = new ReflectionProperty($def, 'arguments');
                    $ref->setAccessible(true);
                    $ref->setValue($def, []);
                } catch (Exception $e) {
                    // Definition may not exist in aggregate — ignore
                }
            }
        }
    }

    /**
     * Mock a service AND mark it for DI argument clearing.
     */
    protected function mockServiceClean(string $class, Closure $factory): void
    {
        $this->mockService($class, $factory);
        $this->mockedServiceKeys[] = $class;
    }

    /**
     * Create an active workflow definition with a current version.
     */
    private function ensureActiveWorkflow(string $slug): void
    {
        $existing = $this->WorkflowDefinitions->find()
            ->where(['slug' => $slug])
            ->first();

        if ($existing && $existing->is_active && $existing->current_version_id) {
            return;
        }

        if (!$existing) {
            $existing = $this->WorkflowDefinitions->newEntity([
                'name' => "Test $slug",
                'slug' => $slug,
                'description' => "Test workflow for $slug",
                'trigger_type' => 'event',
                'is_active' => true,
                'created_by' => self::ADMIN_MEMBER_ID,
                'modified_by' => self::ADMIN_MEMBER_ID,
            ]);
            $this->WorkflowDefinitions->saveOrFail($existing);
        }

        if (!$existing->current_version_id) {
            $version = $this->WorkflowVersions->newEntity([
                'workflow_definition_id' => $existing->id,
                'version_number' => 1,
                'status' => 'published',
                'definition' => ['nodes' => [], 'edges' => []],
                'created_by' => self::ADMIN_MEMBER_ID,
                'modified_by' => self::ADMIN_MEMBER_ID,
            ]);
            $this->WorkflowVersions->saveOrFail($version);

            $existing->current_version_id = $version->id;
        }

        // Ensure the definition is active (may have been seeded as inactive)
        $existing->is_active = true;
        $this->WorkflowDefinitions->saveOrFail($existing);
    }

    /**
     * Deactivate workflow definitions matching the given slugs.
     */
    private function deactivateWorkflows(array $slugs): void
    {
        $this->WorkflowDefinitions->updateAll(
            ['is_active' => false],
            ['slug IN' => $slugs],
        );
    }

    /**
     * Create a test officer record for release/warrant tests.
     */
    private function createTestOfficer(): object
    {
        $office = $this->Offices->find()->first();
        $officer = $this->Officers->newEntity([
            'member_id' => self::TEST_MEMBER_AGATHA_ID,
            'office_id' => $office->id,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'approver_id' => self::ADMIN_MEMBER_ID,
            'approval_date' => DateTime::now(),
            'start_on' => DateTime::now()->subDays(30),
            'expires_on' => DateTime::now()->addMonths(6),
            'status' => 'current',
            'reports_to_office_id' => $office->reports_to_id ?? $office->id,
            'reports_to_branch_id' => self::KINGDOM_BRANCH_ID,
        ]);
        $this->Officers->saveOrFail($officer);

        return $officer;
    }

    /**
     * Get form data for the assign action.
     */
    private function getAssignData(): array
    {
        $office = $this->Offices->find()->first();

        return [
            'member_id' => self::TEST_MEMBER_BRYCE_ID,
            'office_id' => $office->id,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'start_on' => DateTime::now()->toDateString(),
            'end_on' => '',
            'deputy_description' => '',
            'email_address' => 'test@example.com',
        ];
    }

    // ---------------------------------------------------------------
    // assign() tests
    // ---------------------------------------------------------------

    /**
     * Test assign() shows an error when the workflow is unavailable.
     */
    public function testAssignFlashesErrorWhenWorkflowUnavailable(): void
    {
        $this->deactivateWorkflows(['officer-hire']);

        $this->mockServiceClean(TriggerDispatcher::class, function () {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->expects($this->never())->method('dispatch');

            return $mock;
        });

        $this->post('/officers/officers/assign', $this->getAssignData());

        $this->assertRedirect();
        $this->assertFlashMessage('The officer assignment workflow is not currently available.', 'flash');
    }

    /**
     * Test assign() dispatches workflow when officer-hire workflow is active.
     */
    public function testAssignDispatchesWorkflowWhenActive(): void
    {
        $this->ensureActiveWorkflow('officer-hire');

        $dispatched = false;
        $this->mockServiceClean(TriggerDispatcher::class, function () use (&$dispatched) {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->method('dispatch')
                ->willReturnCallback(function (string $event, array $context) use (&$dispatched) {
                    $dispatched = true;
                    $this->assertSame('Officers.HireRequested', $event);
                    $this->assertArrayHasKey('memberId', $context);
                    $this->assertArrayHasKey('officeId', $context);
                    $this->assertArrayHasKey('branchId', $context);
                    $this->assertArrayHasKey('startOn', $context);
                    $this->assertArrayHasKey('expiresOn', $context);
                    $this->assertArrayHasKey('deputyDescription', $context);
                    $this->assertArrayHasKey('emailAddress', $context);
                    $this->assertArrayHasKey('member_id', $context);
                    $this->assertArrayHasKey('office_id', $context);
                    $this->assertArrayHasKey('branch_id', $context);

                    return [new ServiceResult(true, null, ['instanceId' => 999])];
                });

            return $mock;
        });

        $this->post('/officers/officers/assign', $this->getAssignData());

        $this->assertRedirect();
        $this->assertTrue($dispatched, 'TriggerDispatcher::dispatch should have been called');
        $this->assertFlashMessage('The officer has been saved.', 'flash');
    }

    /**
     * Test assign() surfaces workflow rejection errors.
     */
    public function testAssignFlashesWorkflowFailureMessage(): void
    {
        $this->ensureActiveWorkflow('officer-hire');

        $this->mockServiceClean(TriggerDispatcher::class, function () {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->method('dispatch')
                ->willReturn([
                    new ServiceResult(true, null, [
                        'workflowResult' => [
                            'success' => false,
                            'error' => 'Member is not warrantable',
                        ],
                    ]),
                ]);

            return $mock;
        });

        $this->post('/officers/officers/assign', $this->getAssignData());

        $this->assertRedirect();
        $this->assertFlashMessage('Member is not warrantable', 'flash');
    }

    // ---------------------------------------------------------------
    // release() tests
    // ---------------------------------------------------------------

    /**
     * Test release() shows an error when the workflow is unavailable.
     */
    public function testReleaseFlashesErrorWhenWorkflowUnavailable(): void
    {
        $this->deactivateWorkflows(['officers-release']);
        $officer = $this->createTestOfficer();

        $this->mockServiceClean(TriggerDispatcher::class, function () {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->expects($this->never())->method('dispatch');

            return $mock;
        });

        $this->post('/officers/officers/release', [
            'id' => $officer->id,
            'revoked_reason' => 'Stepping down',
            'revoked_on' => DateTime::now()->toDateString(),
        ]);

        $this->assertRedirect();
        $this->assertFlashMessage('The officer release workflow is not currently available.', 'flash');
    }

    /**
     * Test release() dispatches workflow when officers-release workflow is active.
     */
    public function testReleaseDispatchesWorkflowWhenActive(): void
    {
        $this->ensureActiveWorkflow('officers-release');
        $officer = $this->createTestOfficer();

        $dispatched = false;
        $this->mockServiceClean(TriggerDispatcher::class, function () use (&$dispatched) {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->method('dispatch')
                ->willReturnCallback(function (string $event, array $context) use (&$dispatched) {
                    $dispatched = true;
                    $this->assertSame('Officers.Released', $event);
                    $this->assertArrayHasKey('officerId', $context);
                    $this->assertArrayHasKey('memberId', $context);
                    $this->assertArrayHasKey('officeId', $context);
                    $this->assertArrayHasKey('releasedById', $context);
                    $this->assertArrayHasKey('expiresOn', $context);
                    $this->assertArrayHasKey('reason', $context);
                    $this->assertSame(Officer::RELEASED_STATUS, $context['releaseStatus']);

                    return [new ServiceResult(true, null, ['instanceId' => 888])];
                });

            return $mock;
        });

        $this->post('/officers/officers/release', [
            'id' => $officer->id,
            'revoked_reason' => 'Stepping down',
            'revoked_on' => DateTime::now()->toDateString(),
        ]);

        $this->assertRedirect();
        $this->assertTrue($dispatched, 'TriggerDispatcher::dispatch should have been called');
    }

    /**
     * Test release() surfaces workflow execution failures.
     */
    public function testReleaseFlashesWorkflowFailureMessage(): void
    {
        $this->ensureActiveWorkflow('officers-release');
        $officer = $this->createTestOfficer();

        $this->mockServiceClean(TriggerDispatcher::class, function () {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->method('dispatch')
                ->willReturn([
                    new ServiceResult(false, 'Unable to release officer'),
                ]);

            return $mock;
        });

        $this->post('/officers/officers/release', [
            'id' => $officer->id,
            'revoked_reason' => 'Stepping down',
            'revoked_on' => DateTime::now()->toDateString(),
        ]);

        $this->assertRedirect();
        $this->assertFlashMessage('Unable to release officer', 'flash');
    }

    // ---------------------------------------------------------------
    // requestWarrant() tests
    // ---------------------------------------------------------------

    /**
     * Test requestWarrant() creates the roster through WarrantManager.
     */
    public function testRequestWarrantCreatesRosterThroughWarrantManager(): void
    {
        $this->deactivateWorkflows(['warrants-roster-approval']);
        $officer = $this->createTestOfficer();

        $called = false;
        $this->mockServiceClean(WarrantManagerInterface::class, function () use (&$called) {
            $mock = $this->createMock(WarrantManagerInterface::class);
            $mock->method('request')
                ->willReturnCallback(function () use (&$called) {
                    $called = true;

                    return new ServiceResult(true);
                });

            return $mock;
        });

        $this->mockServiceClean(TriggerDispatcher::class, function () {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->expects($this->never())->method('dispatch');

            return $mock;
        });

        $this->post("/officers/officers/requestWarrant/{$officer->id}");

        $this->assertRedirect();
        $this->assertTrue($called, 'WarrantManager::request should have been called');
    }
}
