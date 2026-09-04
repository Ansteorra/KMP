<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Model\Entity\Warrant;
use App\Model\Entity\WarrantPeriod;
use App\Model\Entity\WarrantRoster;
use App\Model\Entity\WorkflowInstance;
use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use App\Services\ServiceResult;
use App\Services\WarrantManager\DefaultWarrantManager;
use App\Services\WarrantManager\WarrantManagerInterface;
use App\Services\WarrantManager\WarrantRequest;
use App\Services\WorkflowEngine\TriggerDispatcher;
use App\Services\WorkflowEngine\WorkflowEngineInterface;
use App\Test\TestCase\BaseTestCase;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

class WarrantManagerTest extends BaseTestCase
{
    private const TEST_ENTITY_TYPE = 'WarrantManagerTests';
    private const TEST_ENTITY_ID = 991001;
    private const SHARED_ROSTER_ENTITY_ID = 991002;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
    }

    /**
     * Create a DefaultWarrantManager with mock dependencies.
     */
    private function createWarrantManager(
        ?ActiveWindowManagerInterface $activeWindowManager = null,
        ?TriggerDispatcher $triggerDispatcher = null,
    ): DefaultWarrantManager {
        $activeWindowManager ??= $this->createMock(ActiveWindowManagerInterface::class);
        $triggerDispatcher ??= $this->createMock(TriggerDispatcher::class);

        return new DefaultWarrantManager($activeWindowManager, $triggerDispatcher);
    }

    /**
     * Create a deterministic future warrant period that cannot collide with seed data.
     */
    private function createReconciliationPeriod(): WarrantPeriod
    {
        $periods = TableRegistry::getTableLocator()->get('WarrantPeriods');
        $period = $periods->newEntity([
            'start_date' => new DateTime('2088-01-01'),
            'end_date' => new DateTime('2088-12-31'),
            'created_by' => self::ADMIN_MEMBER_ID,
        ], ['accessibleFields' => ['*' => true]]);
        $periods->saveOrFail($period);

        return $period;
    }

    /**
     * Create a pending warrant roster.
     */
    private function createPendingRoster(string $name = 'Pending reconciliation roster'): WarrantRoster
    {
        $rosters = TableRegistry::getTableLocator()->get('WarrantRosters');
        $roster = $rosters->newEmptyEntity();
        $roster->name = $name;
        $roster->approvals_required = 2;
        $roster->approval_count = 0;
        $roster->status = WarrantRoster::STATUS_PENDING;
        $roster->created_by = self::ADMIN_MEMBER_ID;
        $rosters->saveOrFail($roster);

        return $roster;
    }

    /**
     * Add a pending warrant to a roster.
     *
     * @param array<string, mixed> $overrides
     */
    private function addPendingWarrant(int $rosterId, array $overrides = []): Warrant
    {
        $warrants = TableRegistry::getTableLocator()->get('Warrants');
        $values = array_merge([
            'name' => 'Reconciliation warrant',
            'member_id' => self::TEST_MEMBER_BRYCE_ID,
            'warrant_roster_id' => $rosterId,
            'entity_type' => self::TEST_ENTITY_TYPE,
            'entity_id' => self::TEST_ENTITY_ID,
            'member_role_id' => null,
            'start_on' => new DateTime('2088-03-01'),
            'expires_on' => new DateTime('2088-06-30'),
            'status' => Warrant::PENDING_STATUS,
            'created_by' => self::ADMIN_MEMBER_ID,
        ], $overrides);
        $warrant = $warrants->newEntity($values, ['accessibleFields' => ['*' => true]]);
        $warrants->saveOrFail($warrant);

        return $warrant;
    }

    /**
     * Build a warrant request for the reconciliation fixture identity.
     *
     * @param array<string, mixed> $overrides
     */
    private function createReconciliationRequest(array $overrides = []): WarrantRequest
    {
        $values = array_merge([
            'name' => 'Reconciliation warrant',
            'entity_type' => self::TEST_ENTITY_TYPE,
            'entity_id' => self::TEST_ENTITY_ID,
            'requester_id' => self::ADMIN_MEMBER_ID,
            'member_id' => self::TEST_MEMBER_BRYCE_ID,
            'start_on' => new DateTime('2088-03-01'),
            'expires_on' => new DateTime('2088-06-30'),
            'member_role_id' => null,
        ], $overrides);

        return new WarrantRequest(
            $values['name'],
            $values['entity_type'],
            $values['entity_id'],
            $values['requester_id'],
            $values['member_id'],
            $values['start_on'],
            $values['expires_on'],
            $values['member_role_id'],
        );
    }

    /**
     * Create a valid role assignment for the reconciliation fixture member.
     */
    private function createReconciliationMemberRole(): int
    {
        $roles = TableRegistry::getTableLocator()->get('Roles');
        $role = $roles->saveOrFail($roles->newEntity([
            'name' => 'Warrant reconciliation role ' . uniqid(),
        ]));
        $memberRoles = TableRegistry::getTableLocator()->get('MemberRoles');
        $memberRole = $memberRoles->saveOrFail($memberRoles->newEntity([
            'member_id' => self::TEST_MEMBER_BRYCE_ID,
            'role_id' => $role->id,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'start_on' => DateTime::now()->subDays(1),
            'expires_on' => DateTime::now()->addYears(10),
            'approver_id' => self::ADMIN_MEMBER_ID,
        ]));

        return (int)$memberRole->id;
    }

    /**
     * Create an active workflow instance for a roster.
     */
    private function createActiveRosterWorkflow(int $rosterId): WorkflowInstance
    {
        $definitions = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $definition = $definitions->newEntity([
            'name' => 'Warrant reconciliation ' . uniqid(),
            'slug' => 'warrant-reconciliation-' . uniqid(),
            'trigger_type' => 'manual',
        ]);
        $definitions->saveOrFail($definition);

        $versions = TableRegistry::getTableLocator()->get('WorkflowVersions');
        $version = $versions->newEntity([
            'workflow_definition_id' => $definition->id,
            'version_number' => 1,
            'definition' => [
                'nodes' => [
                    'trigger' => ['type' => 'trigger', 'outputs' => []],
                ],
            ],
            'status' => 'published',
        ]);
        $versions->saveOrFail($version);

        $instances = TableRegistry::getTableLocator()->get('WorkflowInstances');
        $instance = $instances->newEntity([
            'workflow_definition_id' => $definition->id,
            'workflow_version_id' => $version->id,
            'entity_type' => 'WarrantRosters',
            'entity_id' => $rosterId,
            'status' => WorkflowInstance::STATUS_WAITING,
        ]);
        $instances->saveOrFail($instance);

        return $instance;
    }

    // =========================================
    // WarrantRequest value object tests
    // =========================================

    public function testWarrantRequestConstruction(): void
    {
        $request = new WarrantRequest(
            'Test Warrant',
            'Branches',
            self::TEST_BRANCH_STARGATE_ID,
            self::ADMIN_MEMBER_ID,
            self::TEST_MEMBER_BRYCE_ID,
        );

        $this->assertEquals('Test Warrant', $request->name);
        $this->assertEquals('Branches', $request->entity_type);
        $this->assertEquals(self::TEST_BRANCH_STARGATE_ID, $request->entity_id);
        $this->assertEquals(self::ADMIN_MEMBER_ID, $request->requester_id);
        $this->assertEquals(self::TEST_MEMBER_BRYCE_ID, $request->member_id);
        $this->assertNull($request->start_on);
        $this->assertNull($request->expires_on);
        $this->assertNull($request->member_role_id);
    }

    public function testWarrantRequestWithOptionalParams(): void
    {
        $startOn = new DateTime('2025-01-01');
        $expiresOn = new DateTime('2025-12-31');

        $request = new WarrantRequest(
            'Full Warrant',
            'Branches',
            self::TEST_BRANCH_STARGATE_ID,
            self::ADMIN_MEMBER_ID,
            self::TEST_MEMBER_BRYCE_ID,
            $startOn,
            $expiresOn,
            42,
        );

        $this->assertEquals($startOn, $request->start_on);
        $this->assertEquals($expiresOn, $request->expires_on);
        $this->assertEquals(42, $request->member_role_id);
    }

    public function testWarrantRequestWithNullDates(): void
    {
        $request = new WarrantRequest(
            'Null Date Warrant',
            'Direct Grant',
            1,
            self::ADMIN_MEMBER_ID,
            self::TEST_MEMBER_BRYCE_ID,
            null,
            null,
            null,
        );

        $this->assertNull($request->start_on);
        $this->assertNull($request->expires_on);
        $this->assertNull($request->member_role_id);
    }

    // =========================================
    // DefaultWarrantManager tests
    // =========================================

    public function testGetWarrantPeriodReturnsNullForFarFutureDates(): void
    {
        $manager = $this->createWarrantManager();

        // Use dates far in the future that won't have a warrant period
        $farFuture = new DateTime('2099-01-01');
        $result = $manager->getWarrantPeriod($farFuture, null);
        $this->assertNull($result, 'Should return null for dates outside any warrant period');
    }

    public function testGetWarrantPeriodReturnsEntityForValidDates(): void
    {
        $manager = $this->createWarrantManager();

        // Check if warrant periods exist in seed data
        $warrantPeriodTable = TableRegistry::getTableLocator()->get('WarrantPeriods');
        $currentPeriod = $warrantPeriodTable->find()
            ->where([
                'start_date <=' => DateTime::now(),
                'end_date >=' => DateTime::now(),
            ])
            ->first();

        if ($currentPeriod === null) {
            $this->markTestSkipped('No current warrant period in seed data');
        }

        $result = $manager->getWarrantPeriod(DateTime::now(), null);
        $this->assertNotNull($result, 'Should return a warrant period for current date');
        $this->assertInstanceOf(WarrantPeriod::class, $result);
    }

    public function testGetWarrantPeriodRespectsEndOnConstraint(): void
    {
        $manager = $this->createWarrantManager();

        $warrantPeriodTable = TableRegistry::getTableLocator()->get('WarrantPeriods');
        $currentPeriod = $warrantPeriodTable->find()
            ->where([
                'start_date <=' => DateTime::now(),
                'end_date >=' => DateTime::now(),
            ])
            ->first();

        if ($currentPeriod === null) {
            $this->markTestSkipped('No current warrant period in seed data');
        }

        // Request with an endOn earlier than the period's end
        $earlyEnd = new DateTime('+30 days');
        $result = $manager->getWarrantPeriod(DateTime::now(), $earlyEnd);
        $this->assertNotNull($result);
    }

    public function testGetWarrantPeriodFindsPeriodStartingAfterAdjacentWarrantEnds(): void
    {
        $manager = $this->createWarrantManager();
        $periods = TableRegistry::getTableLocator()->get('WarrantPeriods');
        $currentEnd = new DateTime('2088-12-31');
        $extensionStart = $currentEnd->addDays(1)->startOfDay();
        $nextEnd = new DateTime('2089-12-31');

        $periods->saveOrFail($periods->newEntity([
            'start_date' => new DateTime('2088-01-01'),
            'end_date' => $currentEnd,
            'created_by' => self::ADMIN_MEMBER_ID,
        ], ['accessibleFields' => ['*' => true]]));
        $nextPeriod = $periods->newEntity([
            'start_date' => $extensionStart,
            'end_date' => $nextEnd,
            'created_by' => self::ADMIN_MEMBER_ID,
        ], ['accessibleFields' => ['*' => true]]);
        $periods->saveOrFail($nextPeriod);

        $result = $manager->getWarrantPeriod($extensionStart, null);

        $this->assertNotNull($result);
        $this->assertSame($nextPeriod->id, $result->id);
        $this->assertSame($extensionStart->toDateString(), $result->start_date->toDateString());
        $this->assertSame($nextEnd->toDateString(), $result->end_date->toDateString());
    }

    public function testRequestReusesExactPendingWarrantWithMultipleActiveWorkflows(): void
    {
        $this->createReconciliationPeriod();
        $roster = $this->createPendingRoster();
        $warrant = $this->addPendingWarrant($roster->id);
        $workflow = $this->createActiveRosterWorkflow($roster->id);
        $secondWorkflow = $this->createActiveRosterWorkflow($roster->id);

        $engine = $this->createMock(WorkflowEngineInterface::class);
        $engine->expects($this->never())->method('cancelWorkflow');
        $dispatcher = $this->createMock(TriggerDispatcher::class);
        $dispatcher->method('getEngine')->willReturn($engine);
        $dispatcher->expects($this->never())->method('dispatch');
        $manager = $this->createWarrantManager(null, $dispatcher);

        $rosters = TableRegistry::getTableLocator()->get('WarrantRosters');
        $warrants = TableRegistry::getTableLocator()->get('Warrants');
        $rosterCount = $rosters->find()->count();
        $warrantCount = $warrants->find()->count();
        $request = $this->createReconciliationRequest([
            'start_on' => new DateTime('2088-03-01 14:30:00'),
            'expires_on' => new DateTime('2088-06-30 21:15:00'),
        ]);

        $result = $manager->request(
            'Retry of the same roster',
            'The normalized warrant requirement is unchanged.',
            [$request],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertTrue($result->isSuccess());
        $this->assertSame($roster->id, $result->getData());
        $this->assertSame(WarrantManagerInterface::REQUEST_REUSED_REASON, $result->reason);
        $this->assertSame($rosterCount, $rosters->find()->count());
        $this->assertSame($warrantCount, $warrants->find()->count());
        $this->assertSame(Warrant::PENDING_STATUS, $warrants->get($warrant->id)->status);
        $this->assertSame(
            WorkflowInstance::STATUS_WAITING,
            TableRegistry::getTableLocator()->get('WorkflowInstances')->get($workflow->id)->status,
        );
        $this->assertSame(
            WorkflowInstance::STATUS_WAITING,
            TableRegistry::getTableLocator()->get('WorkflowInstances')->get($secondWorkflow->id)->status,
        );
    }

    public function testRequestReplacesChangedPendingWarrantAndStartsFreshRoster(): void
    {
        $this->createReconciliationPeriod();
        $oldRoster = $this->createPendingRoster('Roster to replace');
        $oldWarrant = $this->addPendingWarrant($oldRoster->id);
        $workflow = $this->createActiveRosterWorkflow($oldRoster->id);

        $engine = $this->createMock(WorkflowEngineInterface::class);
        $engine->expects($this->once())
            ->method('cancelWorkflow')
            ->with(
                $workflow->id,
                $this->callback(fn($reason): bool => is_string($reason) && $reason !== ''),
            )
            ->willReturn(new ServiceResult(true));
        $dispatcher = $this->createMock(TriggerDispatcher::class);
        $dispatcher->method('getEngine')->willReturn($engine);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                'Warrants.RosterCreated',
                $this->callback(
                    fn(array $context): bool => ($context['rosterId'] ?? null) !== $oldRoster->id
                        && ($context['requesterId'] ?? null) === self::ADMIN_MEMBER_ID,
                ),
                self::ADMIN_MEMBER_ID,
            )
            ->willReturn([new ServiceResult(true, '', ['instanceId' => 12345])]);
        $manager = $this->createWarrantManager(null, $dispatcher);
        $request = $this->createReconciliationRequest([
            'expires_on' => new DateTime('2088-07-31'),
        ]);

        $result = $manager->request(
            'Authoritative replacement roster',
            'The required warrant window changed.',
            [$request],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertTrue($result->isSuccess());
        $this->assertNotSame($oldRoster->id, $result->getData());

        $rosters = TableRegistry::getTableLocator()->get('WarrantRosters');
        $warrants = TableRegistry::getTableLocator()->get('Warrants');
        $this->assertSame(Warrant::REPLACED_STATUS, $warrants->get($oldWarrant->id)->status);
        $this->assertSame(WarrantRoster::STATUS_REPLACED, $rosters->get($oldRoster->id)->status);

        $replacementRoster = $rosters->get($result->getData());
        $this->assertSame(WarrantRoster::STATUS_PENDING, $replacementRoster->status);
        $replacementWarrant = $warrants->find()
            ->where(['warrant_roster_id' => $replacementRoster->id])
            ->firstOrFail();
        $this->assertSame(Warrant::PENDING_STATUS, $replacementWarrant->status);
        $this->assertSame('2088-03-01', $replacementWarrant->start_on->toDateString());
        $this->assertSame('2088-07-31', $replacementWarrant->expires_on->toDateString());
    }

    public function testRequestLeavesSharedRosterWorkflowActiveWhenAnotherWarrantIsPending(): void
    {
        $this->createReconciliationPeriod();
        $oldRoster = $this->createPendingRoster('Shared pending roster');
        $oldWarrant = $this->addPendingWarrant($oldRoster->id);
        $otherWarrant = $this->addPendingWarrant($oldRoster->id, [
            'name' => 'Unrelated pending warrant',
            'member_id' => self::TEST_MEMBER_AGATHA_ID,
            'entity_id' => self::SHARED_ROSTER_ENTITY_ID,
        ]);
        $workflow = $this->createActiveRosterWorkflow($oldRoster->id);

        $engine = $this->createMock(WorkflowEngineInterface::class);
        $engine->expects($this->never())->method('cancelWorkflow');
        $dispatcher = $this->createMock(TriggerDispatcher::class);
        $dispatcher->method('getEngine')->willReturn($engine);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturn([new ServiceResult(true, '', ['instanceId' => 12346])]);
        $manager = $this->createWarrantManager(null, $dispatcher);
        $request = $this->createReconciliationRequest([
            'expires_on' => new DateTime('2088-08-31'),
        ]);

        $result = $manager->request(
            'Replacement from shared roster',
            'Only one warrant in the old roster changed.',
            [$request],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertTrue($result->isSuccess());
        $rosters = TableRegistry::getTableLocator()->get('WarrantRosters');
        $warrants = TableRegistry::getTableLocator()->get('Warrants');
        $this->assertSame(WarrantRoster::STATUS_PENDING, $rosters->get($oldRoster->id)->status);
        $this->assertSame(Warrant::REPLACED_STATUS, $warrants->get($oldWarrant->id)->status);
        $this->assertSame(Warrant::PENDING_STATUS, $warrants->get($otherWarrant->id)->status);
        $this->assertSame(
            WorkflowInstance::STATUS_WAITING,
            TableRegistry::getTableLocator()->get('WorkflowInstances')->get($workflow->id)->status,
        );
    }

    public function testRequestDoesNotReplacePendingWarrantForDifferentMemberRole(): void
    {
        $this->createReconciliationPeriod();
        $oldMemberRoleId = $this->createReconciliationMemberRole();
        $newMemberRoleId = $this->createReconciliationMemberRole();
        $oldRoster = $this->createPendingRoster('Different role identity roster');
        $oldWarrant = $this->addPendingWarrant($oldRoster->id, [
            'member_role_id' => $oldMemberRoleId,
        ]);
        $workflow = $this->createActiveRosterWorkflow($oldRoster->id);

        $engine = $this->createMock(WorkflowEngineInterface::class);
        $engine->expects($this->never())->method('cancelWorkflow');
        $dispatcher = $this->createMock(TriggerDispatcher::class);
        $dispatcher->method('getEngine')->willReturn($engine);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturn([new ServiceResult(true, '', ['instanceId' => 12347])]);
        $manager = $this->createWarrantManager(null, $dispatcher);

        $result = $manager->request(
            'Warrant for a different role assignment',
            'The role assignment is part of the warrant identity.',
            [$this->createReconciliationRequest(['member_role_id' => $newMemberRoleId])],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertTrue($result->isSuccess());
        $this->assertNotSame($oldRoster->id, $result->getData());
        $rosters = TableRegistry::getTableLocator()->get('WarrantRosters');
        $warrants = TableRegistry::getTableLocator()->get('Warrants');
        $this->assertSame(WarrantRoster::STATUS_PENDING, $rosters->get($oldRoster->id)->status);
        $this->assertSame(Warrant::PENDING_STATUS, $warrants->get($oldWarrant->id)->status);
        $this->assertSame(
            WorkflowInstance::STATUS_WAITING,
            TableRegistry::getTableLocator()->get('WorkflowInstances')->get($workflow->id)->status,
        );
        $newWarrant = $warrants->find()
            ->where(['warrant_roster_id' => $result->getData()])
            ->firstOrFail();
        $this->assertSame($newMemberRoleId, $newWarrant->member_role_id);
    }

    public function testWithdrawPendingRequestsReplacesOnlyMatchingPendingWarrant(): void
    {
        $pendingRoster = $this->createPendingRoster('Roster to withdraw');
        $pendingWarrant = $this->addPendingWarrant($pendingRoster->id);
        $workflow = $this->createActiveRosterWorkflow($pendingRoster->id);
        $currentRoster = $this->createPendingRoster('Issued warrant roster');
        $currentRoster->status = WarrantRoster::STATUS_APPROVED;
        TableRegistry::getTableLocator()->get('WarrantRosters')->saveOrFail($currentRoster);
        $currentEnd = new DateTime('2088-11-30');
        $currentWarrant = $this->addPendingWarrant($currentRoster->id, [
            'status' => Warrant::CURRENT_STATUS,
            'expires_on' => $currentEnd,
        ]);

        $engine = $this->createMock(WorkflowEngineInterface::class);
        $engine->expects($this->once())
            ->method('cancelWorkflow')
            ->with(
                $workflow->id,
                $this->callback(fn($reason): bool => is_string($reason) && $reason !== ''),
            )
            ->willReturn(new ServiceResult(true));
        $dispatcher = $this->createMock(TriggerDispatcher::class);
        $dispatcher->method('getEngine')->willReturn($engine);
        $dispatcher->expects($this->never())->method('dispatch');
        $manager = $this->createWarrantManager(null, $dispatcher);

        $result = $manager->withdrawPendingRequests(
            self::TEST_ENTITY_TYPE,
            self::TEST_ENTITY_ID,
            self::TEST_MEMBER_BRYCE_ID,
            null,
            self::ADMIN_MEMBER_ID,
            'The underlying assignment term was shortened.',
        );

        $this->assertTrue($result->isSuccess());
        $this->assertSame(1, $result->getData());
        $warrants = TableRegistry::getTableLocator()->get('Warrants');
        $this->assertSame(Warrant::REPLACED_STATUS, $warrants->get($pendingWarrant->id)->status);
        $this->assertSame(Warrant::CURRENT_STATUS, $warrants->get($currentWarrant->id)->status);
        $this->assertSame(
            $currentEnd->toDateTimeString(),
            $warrants->get($currentWarrant->id)->expires_on->toDateTimeString(),
        );
        $this->assertSame(
            WarrantRoster::STATUS_REPLACED,
            TableRegistry::getTableLocator()->get('WarrantRosters')->get($pendingRoster->id)->status,
        );
    }

    public function testRequestRollsBackWhenRosterWorkflowDoesNotStart(): void
    {
        $this->createReconciliationPeriod();
        $dispatcher = $this->createMock(TriggerDispatcher::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                'Warrants.RosterCreated',
                $this->isType('array'),
                self::ADMIN_MEMBER_ID,
            )
            ->willReturn([]);
        $manager = $this->createWarrantManager(null, $dispatcher);
        $rosters = TableRegistry::getTableLocator()->get('WarrantRosters');
        $warrants = TableRegistry::getTableLocator()->get('Warrants');
        $rosterCount = $rosters->find()->count();
        $warrantCount = $warrants->find()->count();

        $result = $manager->request(
            'Roster without workflow',
            'The trigger dispatcher cannot start a workflow.',
            [$this->createReconciliationRequest()],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertFalse($result->isSuccess());
        $this->assertSame($rosterCount, $rosters->find()->count());
        $this->assertSame($warrantCount, $warrants->find()->count());
    }

    public function testDeclineRejectsNonPendingRoster(): void
    {
        $manager = $this->createWarrantManager();

        // Find a non-pending roster (approved or declined)
        $warrantRosterTable = TableRegistry::getTableLocator()->get('WarrantRosters');
        $nonPendingRoster = $warrantRosterTable->find()
            ->where(['status !=' => 'Pending'])
            ->first();

        if ($nonPendingRoster === null) {
            $this->markTestSkipped('No non-pending warrant rosters in seed data');
        }

        $result = $manager->decline($nonPendingRoster->id, self::ADMIN_MEMBER_ID, 'test reason');
        $this->assertInstanceOf(ServiceResult::class, $result);
        $this->assertFalse($result->isSuccess());
    }

    public function testCancelNonExistentWarrantReturnsSuccess(): void
    {
        $manager = $this->createWarrantManager();

        // cancel() returns success for non-existent warrants
        // But it does a get() which throws, so let's test cancelByEntity instead
        $result = $manager->cancelByEntity(
            'NonExistentType',
            99999,
            'test reason',
            self::ADMIN_MEMBER_ID,
            new DateTime(),
        );
        $this->assertInstanceOf(ServiceResult::class, $result);
        $this->assertTrue($result->isSuccess(), 'cancelByEntity with no matching warrant should return success');
    }

    public function testRequestReturnsServiceResult(): void
    {
        $manager = $this->createWarrantManager();

        // Test with a member that is not warrantable - should fail gracefully
        $warrantPeriodTable = TableRegistry::getTableLocator()->get('WarrantPeriods');
        $currentPeriod = $warrantPeriodTable->find()
            ->where([
                'start_date <=' => DateTime::now(),
                'end_date >=' => DateTime::now(),
            ])
            ->first();

        if ($currentPeriod === null) {
            $this->markTestSkipped('No current warrant period in seed data');
        }

        $warrantRequest = new WarrantRequest(
            'Test Request',
            'Branches',
            self::TEST_BRANCH_STARGATE_ID,
            self::ADMIN_MEMBER_ID,
            self::ADMIN_MEMBER_ID,
            DateTime::now(),
            null,
            null,
        );

        $result = $manager->request('Test Roster', 'Test Description', [$warrantRequest]);
        $this->assertInstanceOf(ServiceResult::class, $result);
    }
}
