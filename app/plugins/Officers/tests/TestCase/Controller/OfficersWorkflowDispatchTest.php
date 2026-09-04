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
 * Verifies that assign(), edit(), and release() route through TriggerDispatcher
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
            'status' => Officer::CURRENT_STATUS,
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

    /**
     * Get form data for the edit action.
     *
     * @param object $officer Officer being edited
     * @param array<string, mixed> $overrides Form value overrides
     * @return array<string, mixed>
     */
    private function getEditData(object $officer, array $overrides = []): array
    {
        return array_replace([
            'id' => $officer->id,
            'start_on' => $officer->start_on->format('Y-m-d'),
            'expires_on' => $officer->expires_on?->format('Y-m-d') ?? '',
            'deputy_description' => $officer->deputy_description ?? '',
            'email_address' => $officer->email_address ?? '',
            'term_note' => '',
        ], $overrides);
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
    // edit() tests
    // ---------------------------------------------------------------

    /**
     * Test edit() reports an unavailable workflow without mutating the officer.
     */
    public function testEditFlashesErrorWhenWorkflowUnavailable(): void
    {
        $this->deactivateWorkflows(['officer-assignment-update']);
        $officer = $this->createTestOfficer();
        $originalEmail = $this->Officers->get($officer->id)->email_address;

        $this->mockServiceClean(TriggerDispatcher::class, function () {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->expects($this->never())->method('dispatch');

            return $mock;
        });

        $this->post('/officers/officers/edit', $this->getEditData($officer, [
            'email_address' => 'unavailable@example.com',
        ]));

        $this->assertRedirect();
        $this->assertFlashMessage(
            'The officer assignment update workflow is not currently available.',
            'flash',
        );
        $this->assertSame($originalEmail, $this->Officers->get($officer->id)->email_address);
    }

    /**
     * Test edit() dispatches normalized values and performs no direct save.
     */
    public function testEditDispatchesNormalizedWorkflowContext(): void
    {
        $this->ensureActiveWorkflow('officer-assignment-update');
        $officer = $this->createTestOfficer();
        $officer->email_address = 'before@example.com';
        $officer->deputy_description = 'Before deputy';
        $this->Officers->saveOrFail($officer);

        $dispatched = false;
        $this->mockServiceClean(TriggerDispatcher::class, function () use (&$dispatched, $officer) {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->method('dispatch')
                ->willReturnCallback(function (
                    string $event,
                    array $context,
                    ?int $triggeredBy = null,
                ) use (
                    &$dispatched,
                    $officer,
                ): array {
                    $dispatched = true;
                    $this->assertSame('Officers.AssignmentUpdateRequested', $event);
                    $this->assertSame(self::ADMIN_MEMBER_ID, $triggeredBy);
                    $this->assertSame((int)$officer->id, $context['officerId']);
                    $this->assertSame(self::ADMIN_MEMBER_ID, $context['actorId']);
                    $this->assertSame((int)$officer->member_id, $context['memberId']);
                    $this->assertSame((int)$officer->office_id, $context['officeId']);
                    $this->assertSame((int)$officer->branch_id, $context['branchId']);
                    $this->assertSame($officer->start_on->format('Y-m-d'), $context['startOn']);
                    $this->assertSame($officer->expires_on->format('Y-m-d'), $context['expiresOn']);
                    $this->assertSame('after@example.com', $context['emailAddress']);
                    $this->assertSame('After deputy', $context['deputyDescription']);
                    $this->assertSame('', $context['termNote']);

                    return [new ServiceResult(true, null, [
                        'workflowResult' => [
                            'success' => true,
                            'data' => ['officerId' => (int)$officer->id],
                        ],
                    ])];
                });

            return $mock;
        });

        $this->post('/officers/officers/edit', $this->getEditData($officer, [
            'start_on' => ' ' . $officer->start_on->format('Y-m-d') . ' ',
            'expires_on' => ' ' . $officer->expires_on->format('Y-m-d') . ' ',
            'email_address' => ' after@example.com ',
            'deputy_description' => ' After deputy ',
        ]));

        $this->assertRedirect();
        $this->assertTrue($dispatched, 'TriggerDispatcher::dispatch should have been called');
        $this->assertFlashMessage('The officer assignment has been updated.', 'flash');

        $unchangedOfficer = $this->Officers->get($officer->id);
        $this->assertSame('before@example.com', $unchangedOfficer->email_address);
        $this->assertSame('Before deputy', $unchangedOfficer->deputy_description);
    }

    /**
     * Test edit() reports a committed update with follow-up warnings without inviting a duplicate retry.
     */
    public function testEditReportsSavedWithWarningWithoutInvitingDuplicateRetry(): void
    {
        $this->ensureActiveWorkflow('officer-assignment-update');
        $officer = $this->createTestOfficer();

        $this->mockServiceClean(TriggerDispatcher::class, function () {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->method('dispatch')
                ->willReturn([
                    new ServiceResult(true, null, [
                        'workflowResult' => [
                            'success' => true,
                            'updated' => true,
                            'warnings' => [
                                'The warrant extension request could not be completed.',
                                null,
                            ],
                        ],
                    ]),
                ]);

            return $mock;
        });

        $this->post('/officers/officers/edit', $this->getEditData($officer, [
            'email_address' => 'updated@example.com',
        ]));

        $this->assertRedirect();
        $this->assertFlashMessage(
            'The officer assignment was saved, but follow-up work needs attention: '
            . 'The warrant extension request could not be completed. '
            . 'Do not submit this assignment update again; complete the follow-up separately.',
        );
        $this->assertFlashElement('flash/warning');
    }

    /**
     * Test edit() does not label an advisory warning as saved without the updated contract flag.
     */
    public function testEditRequiresUpdatedFlagForSavedWithWarningMessage(): void
    {
        $this->ensureActiveWorkflow('officer-assignment-update');
        $officer = $this->createTestOfficer();

        $this->mockServiceClean(TriggerDispatcher::class, function () {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->method('dispatch')
                ->willReturn([
                    new ServiceResult(true, null, [
                        'workflowResult' => [
                            'success' => true,
                            'warnings' => ['Advisory only.'],
                        ],
                    ]),
                ]);

            return $mock;
        });

        $this->post('/officers/officers/edit', $this->getEditData($officer));

        $this->assertRedirect();
        $this->assertFlashMessage('The officer assignment has been updated.');
        $this->assertFlashElement('flash/success');
    }

    /**
     * Test edit() requires a note when a normalized term date changes.
     */
    public function testEditRequiresTermNoteForDateChange(): void
    {
        $this->ensureActiveWorkflow('officer-assignment-update');
        $officer = $this->createTestOfficer();
        $originalExpiresOn = $officer->expires_on->format('Y-m-d');

        $this->mockServiceClean(TriggerDispatcher::class, function () {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->expects($this->never())->method('dispatch');

            return $mock;
        });

        $this->post('/officers/officers/edit', $this->getEditData($officer, [
            'expires_on' => $officer->expires_on->addMonths(1)->format('Y-m-d'),
            'term_note' => '   ',
        ]));

        $this->assertRedirect();
        $this->assertFlashMessage(
            'A note is required when changing the officer term dates.',
            'flash',
        );
        $this->assertSame(
            $originalExpiresOn,
            $this->Officers->get($officer->id)->expires_on->format('Y-m-d'),
        );
    }

    /**
     * Test edit() rejects crafted updates for terminal assignment states.
     */
    public function testEditRejectsReleasedAndExpiredAssignmentsBeforeDispatch(): void
    {
        $this->mockServiceClean(TriggerDispatcher::class, function () {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->expects($this->never())->method('dispatch');

            return $mock;
        });

        foreach ([Officer::RELEASED_STATUS, Officer::EXPIRED_STATUS] as $status) {
            $officer = $this->createTestOfficer();
            $officer->status = $status;
            $this->Officers->saveOrFail($officer);
            $originalStart = $officer->start_on->toDateTimeString();
            $originalEnd = $officer->expires_on->toDateTimeString();
            $originalEmail = (string)($officer->email_address ?? '');

            $this->post('/officers/officers/edit', $this->getEditData($officer, [
                'start_on' => DateTime::now()->toDateString(),
                'expires_on' => DateTime::now()->addMonths(12)->toDateString(),
                'email_address' => 'crafted-reactivation@example.test',
                'term_note' => 'Attempt to reactivate a terminal assignment.',
            ]));

            $this->assertRedirect();
            $this->assertFlashMessage('Only current or upcoming officer assignments can be updated.');
            $savedOfficer = $this->Officers->get($officer->id);
            $this->assertSame($status, $savedOfficer->status);
            $this->assertSame($originalStart, $savedOfficer->start_on->toDateTimeString());
            $this->assertSame($originalEnd, $savedOfficer->expires_on->toDateTimeString());
            $this->assertSame($originalEmail, $savedOfficer->email_address);
        }
    }

    /**
     * Test edit() rejects an invalid required start date before dispatch.
     */
    public function testEditRejectsInvalidStartDate(): void
    {
        $officer = $this->createTestOfficer();
        $this->mockServiceClean(TriggerDispatcher::class, function () {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->expects($this->never())->method('dispatch');

            return $mock;
        });

        $this->post('/officers/officers/edit', $this->getEditData($officer, [
            'start_on' => '2026-02-31',
        ]));

        $this->assertRedirect();
        $this->assertFlashMessage('Enter a valid start date.', 'flash');
    }

    /**
     * Test edit() rejects an invalid optional end date before dispatch.
     */
    public function testEditRejectsInvalidEndDate(): void
    {
        $officer = $this->createTestOfficer();
        $this->mockServiceClean(TriggerDispatcher::class, function () {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->expects($this->never())->method('dispatch');

            return $mock;
        });

        $this->post('/officers/officers/edit', $this->getEditData($officer, [
            'expires_on' => 'not-a-date',
        ]));

        $this->assertRedirect();
        $this->assertFlashMessage('Enter a valid end date.', 'flash');
    }

    /**
     * Test edit() rejects an end date before the start date.
     */
    public function testEditRejectsEndDateBeforeStartDate(): void
    {
        $officer = $this->createTestOfficer();
        $this->mockServiceClean(TriggerDispatcher::class, function () {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->expects($this->never())->method('dispatch');

            return $mock;
        });

        $this->post('/officers/officers/edit', $this->getEditData($officer, [
            'expires_on' => $officer->start_on->subDays(1)->format('Y-m-d'),
            'term_note' => 'Correcting the recorded term.',
        ]));

        $this->assertRedirect();
        $this->assertFlashMessage(
            'The officer term end date cannot be before the start date.',
            'flash',
        );
    }

    /**
     * Test edit() rejects an invalid optional email before dispatch.
     */
    public function testEditRejectsInvalidEmailAddress(): void
    {
        $officer = $this->createTestOfficer();
        $this->mockServiceClean(TriggerDispatcher::class, function () {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->expects($this->never())->method('dispatch');

            return $mock;
        });

        $this->post('/officers/officers/edit', $this->getEditData($officer, [
            'email_address' => 'not-an-email',
        ]));

        $this->assertRedirect();
        $this->assertFlashMessage('Enter a valid email address.', 'flash');
    }

    /**
     * Test edit() surfaces workflow failure without directly saving changes.
     */
    public function testEditFlashesWorkflowFailureWithoutDirectMutation(): void
    {
        $this->ensureActiveWorkflow('officer-assignment-update');
        $officer = $this->createTestOfficer();
        $officer->email_address = 'unchanged@example.com';
        $this->Officers->saveOrFail($officer);

        $dispatched = false;
        $this->mockServiceClean(TriggerDispatcher::class, function () use (&$dispatched) {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->method('dispatch')
                ->willReturnCallback(function () use (&$dispatched): array {
                    $dispatched = true;

                    return [new ServiceResult(false, 'The update was rejected.')];
                });

            return $mock;
        });

        $this->post('/officers/officers/edit', $this->getEditData($officer, [
            'email_address' => 'should-not-save@example.com',
        ]));

        $this->assertRedirect();
        $this->assertTrue($dispatched, 'TriggerDispatcher::dispatch should have been called');
        $this->assertFlashMessage('The update was rejected.', 'flash');
        $this->assertSame(
            'unchanged@example.com',
            $this->Officers->get($officer->id)->email_address,
        );
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
