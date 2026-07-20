<?php

declare(strict_types=1);

namespace App\Test\TestCase\Services\WorkflowEngine\Providers;

use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use App\Model\Entity\Warrant;
use App\Services\ServiceResult;
use App\Services\WarrantManager\WarrantManagerInterface;
use App\Services\WarrantManager\WarrantRequest;
use App\Services\WorkflowEngine\TriggerDispatcher;
use App\Services\WorkflowRegistry\WorkflowActionRegistry;
use App\Services\WorkflowRegistry\WorkflowConditionRegistry;
use App\Test\TestCase\BaseTestCase;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Officers\Model\Entity\Officer;
use Officers\Services\OfficerManagerInterface;
use Officers\Services\OfficerWorkflowActions;
use Officers\Services\OfficerWorkflowConditions;
use Officers\Services\OfficersWorkflowProvider;

/**
 * Tests for Officers plugin workflow actions and conditions.
 */
class OfficersWorkflowActionsTest extends BaseTestCase
{
    private OfficerWorkflowActions $actions;
    private OfficerWorkflowConditions $conditions;
    private ActiveWindowManagerInterface $activeWindowManager;
    private WarrantManagerInterface $warrantManager;
    private OfficerManagerInterface $officerManager;
    private TriggerDispatcher $triggerDispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activeWindowManager = $this->createMock(ActiveWindowManagerInterface::class);
        $this->warrantManager = $this->createMock(WarrantManagerInterface::class);
        $this->officerManager = $this->createMock(OfficerManagerInterface::class);
        $this->triggerDispatcher = $this->createMock(TriggerDispatcher::class);

        $this->actions = $this->getMockBuilder(OfficerWorkflowActions::class)
            ->setConstructorArgs([
                $this->activeWindowManager,
                $this->warrantManager,
                $this->officerManager,
                $this->triggerDispatcher,
            ])
            ->onlyMethods(['queueMail'])
            ->getMock();
        $this->conditions = new OfficerWorkflowConditions();
    }

    protected function tearDown(): void
    {
        WorkflowActionRegistry::clear();
        WorkflowConditionRegistry::clear();
        parent::tearDown();
    }

    // =====================================================
    // Provider Registration
    // =====================================================

    public function testProviderRegistersAllActions(): void
    {
        OfficersWorkflowProvider::register();
        $actions = WorkflowActionRegistry::getActionsBySource('Officers');
        $actionKeys = array_column($actions, 'action');

        $this->assertContains('Officers.CreateOfficerRecord', $actionKeys);
        $this->assertContains('Officers.ReleaseOfficer', $actionKeys);
        $this->assertContains('Officers.RequestWarrantRoster', $actionKeys);
        $this->assertContains('Officers.CalculateReportingFields', $actionKeys);
        $this->assertContains('Officers.ReleaseConflictingOfficers', $actionKeys);
        $this->assertContains('Officers.RecalculateOfficersForOffice', $actionKeys);
        $this->assertContains('Officers.PrepareHireNotificationVars', $actionKeys);
        $this->assertContains('Officers.PrepareReleaseNotificationVars', $actionKeys);
        $this->assertNotContains('Officers.SendHireNotification', $actionKeys);
        $this->assertNotContains('Officers.SendReleaseNotification', $actionKeys);
    }

    public function testProviderRegistersAllConditions(): void
    {
        OfficersWorkflowProvider::register();
        $conditions = WorkflowConditionRegistry::getConditionsBySource('Officers');
        $conditionKeys = array_column($conditions, 'condition');

        $this->assertContains('Officers.OfficeRequiresWarrant', $conditionKeys);
        $this->assertContains('Officers.IsOnlyOnePerBranch', $conditionKeys);
        $this->assertContains('Officers.IsMemberWarrantable', $conditionKeys);
        $this->assertContains('Officers.HasConflictingOfficer', $conditionKeys);
    }

    public function testOfficerHireWorkflowChecksWarrantabilityBeforeConflictResolution(): void
    {
        $definition = json_decode(
            (string)file_get_contents(ROOT . '/config/Seeds/WorkflowDefinitions/officers-hire.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->assertSame(
            'check-requires-warrant-before-create',
            $definition['nodes']['trigger-hire']['outputs'][0]['target'],
        );
        $this->assertSame(
            'check-only-one-per-branch',
            $definition['nodes']['check-requires-warrant-before-create']['outputs'][1]['target'],
        );
        $this->assertSame(
            'check-only-one-per-branch',
            $definition['nodes']['check-warrantable']['outputs'][0]['target'],
        );
        $this->assertSame(
            'create-officer',
            $definition['nodes']['check-has-conflict']['outputs'][1]['target'],
        );
        $this->assertSame(
            'create-officer',
            $definition['nodes']['release-conflicting']['outputs'][0]['target'],
        );
    }

    // =====================================================
    // CreateOfficerRecord action
    // =====================================================

    public function testCreateOfficerRecordThrowsWhenWarrantRequiredMemberIsNotWarrantable(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Member is not warrantable');

        $this->actions->createOfficerRecord(
            ['triggeredBy' => self::ADMIN_MEMBER_ID],
            [
                'memberId' => self::TEST_MEMBER_DEVON_ID,
                'officeId' => 2,
                'branchId' => self::KINGDOM_BRANCH_ID,
                'startOn' => DateTime::now()->format('Y-m-d H:i:s'),
                'expiresOn' => DateTime::now()->addMonths(6)->format('Y-m-d H:i:s'),
                'emailAddress' => 'devon@example.test',
                'deputyDescription' => '',
            ],
        );
    }

    public function testCreateOfficerRecordDoesNotLetActiveWindowManagerRecloseUniqueOfficeConflicts(): void
    {
        $startOn = DateTime::now()->addDays(10);
        $expiresOn = DateTime::now()->addDays(40);

        $this->activeWindowManager->expects($this->once())
            ->method('start')
            ->with(
                'Officers.Officers',
                $this->isType('int'),
                self::ADMIN_MEMBER_ID,
                $this->callback(fn (DateTime $value) => $value->toDateTimeString() === $startOn->toDateTimeString()),
                $this->callback(fn (?DateTime $value) => $value?->toDateTimeString() === $expiresOn->toDateTimeString()),
                $this->isType('int'),
                $this->isType('int'),
                false,
                self::KINGDOM_BRANCH_ID,
            )
            ->willReturn(new ServiceResult(true));

        $result = $this->actions->createOfficerRecord(
            ['triggeredBy' => self::ADMIN_MEMBER_ID],
            [
                'memberId' => self::TEST_MEMBER_AGATHA_ID,
                'officeId' => 3,
                'branchId' => self::KINGDOM_BRANCH_ID,
                'startOn' => $startOn->format('Y-m-d H:i:s'),
                'expiresOn' => $expiresOn->format('Y-m-d H:i:s'),
                'emailAddress' => 'agatha@example.test',
                'deputyDescription' => 'Northern Deputy',
            ],
        );

        $this->assertArrayHasKey('officerId', $result);
        $this->assertIsInt($result['officerId']);
    }

    // =====================================================
    // CalculateReportingFields action
    // =====================================================

    public function testCalculateReportingFieldsForDeputyOffice(): void
    {
        // Office 3 = Kingdom Rapier Marshal, deputy_to_id = 2
        $context = ['triggeredBy' => self::ADMIN_MEMBER_ID];
        $config = [
            'officeId' => 3,
            'branchId' => self::KINGDOM_BRANCH_ID,
        ];

        $result = $this->actions->calculateReportingFieldsAction($context, $config);

        $this->assertArrayHasKey('reports_to_office_id', $result);
        $this->assertArrayHasKey('deputy_to_office_id', $result);
        // Deputy offices set deputy_to_office_id to the deputy_to_id value
        $this->assertEquals(2, $result['deputy_to_office_id']);
        $this->assertEquals(2, $result['reports_to_office_id']);
        $this->assertEquals(self::KINGDOM_BRANCH_ID, $result['deputy_to_branch_id']);
    }

    public function testCalculateReportingFieldsForRegularOffice(): void
    {
        // Office 4 = Regional Rapier Marshal, reports_to_id = 3, no deputy
        // Branch 12 = Central Region, parent = 2 (Kingdom)
        $context = ['triggeredBy' => self::ADMIN_MEMBER_ID];
        $config = [
            'officeId' => 4,
            'branchId' => self::TEST_BRANCH_CENTRAL_REGION_ID,
        ];

        $result = $this->actions->calculateReportingFieldsAction($context, $config);

        $this->assertArrayHasKey('reports_to_office_id', $result);
        $this->assertEquals(3, $result['reports_to_office_id']);
        $this->assertNull($result['deputy_to_office_id']);
    }

    public function testCalculateReportingFieldsWithInvalidOfficeReturnsNulls(): void
    {
        $context = [];
        $config = [
            'officeId' => 999999,
            'branchId' => self::KINGDOM_BRANCH_ID,
        ];

        $result = $this->actions->calculateReportingFieldsAction($context, $config);

        $this->assertNull($result['reports_to_office_id']);
        $this->assertNull($result['deputy_to_office_id']);
    }

    // =====================================================
    // ReleaseConflictingOfficers action
    // =====================================================

    public function testReleaseConflictingOfficersReleasesCurrentOfficers(): void
    {
        $office = TableRegistry::getTableLocator()->get('Officers.Offices')->get(6);
        $replacementStart = DateTime::now();
        $officer = $this->createOfficerConflictFixture(
            $office->id,
            Officer::CURRENT_STATUS,
            $replacementStart,
            DateTime::now()->addMonths(11),
        );
        $createdId = $officer->id;

        $context = ['triggeredBy' => self::ADMIN_MEMBER_ID];
        $config = [
            'officeId' => $office->id,
            'branchId' => self::KINGDOM_BRANCH_ID,
            'newOfficerStartDate' => $replacementStart->format('Y-m-d H:i:s'),
        ];

        $this->triggerDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                'Officers.Released',
                $this->callback(function (array $payload) use ($createdId) {
                    $this->assertSame($createdId, $payload['officerId']);
                    $this->assertSame(self::ADMIN_MEMBER_ID, $payload['releasedById']);
                    $this->assertSame('Replaced by new officer', $payload['reason']);
                    $this->assertSame(Officer::REPLACED_STATUS, $payload['releaseStatus']);
                    $this->assertArrayHasKey('expiresOn', $payload);

                    return true;
                }),
                self::ADMIN_MEMBER_ID,
            )
            ->willReturn([new ServiceResult(true, null, ['instanceId' => 321])]);

        $this->activeWindowManager->expects($this->never())->method('stop');
        $this->warrantManager->expects($this->never())->method('cancelByEntity');

        $result = $this->actions->releaseConflictingOfficers($context, $config);

        $this->assertArrayHasKey('releasedOfficerIds', $result);
        $this->assertContains($createdId, $result['releasedOfficerIds']);
    }

    public function testReleaseConflictingOfficersTrimsCurrentOfficerAndLinkedRecords(): void
    {
        $office = TableRegistry::getTableLocator()->get('Officers.Offices')->get(2);
        $officer = $this->createOfficerConflictFixture(
            $office->id,
            Officer::CURRENT_STATUS,
            DateTime::now()->subMonths(1),
            DateTime::now()->addMonths(11),
            true,
        );
        $memberRoleId = $officer->granted_member_role_id;
        $newHireStart = DateTime::now()->addDays(10);
        $expectedEnd = $newHireStart->subSeconds(1);

        $this->actions->expects($this->once())
            ->method('queueMail')
            ->with(
                'KMP',
                'sendFromTemplate',
                $this->anything(),
                $this->callback(function (array $vars) {
                    return $vars['_templateId'] === 'officer-assignment-adjusted-notification';
                }),
            );
        $this->triggerDispatcher->expects($this->never())->method('dispatch');

        $result = $this->actions->releaseConflictingOfficers(
            ['triggeredBy' => self::ADMIN_MEMBER_ID],
            [
                'officeId' => $office->id,
                'branchId' => self::KINGDOM_BRANCH_ID,
                'newOfficerStartDate' => $newHireStart->format('Y-m-d H:i:s'),
                'newOfficerEndDate' => $newHireStart->addMonths(2)->format('Y-m-d H:i:s'),
            ],
        );

        $updatedOfficer = TableRegistry::getTableLocator()->get('Officers.Officers')->get($officer->id);
        $updatedRole = TableRegistry::getTableLocator()->get('MemberRoles')->get($memberRoleId);
        $updatedWarrant = TableRegistry::getTableLocator()->get('Warrants')
            ->find()
            ->where(['entity_type' => 'Officers.Officers', 'entity_id' => $officer->id])
            ->firstOrFail();

        $this->assertSame([], $result['releasedOfficerIds']);
        $this->assertContains($officer->id, $result['adjustedOfficerIds']);
        $this->assertSame($expectedEnd->toDateTimeString(), $updatedOfficer->expires_on->toDateTimeString());
        $this->assertSame(Officer::CURRENT_STATUS, $updatedOfficer->status);
        $this->assertSame($expectedEnd->toDateTimeString(), $updatedRole->expires_on->toDateTimeString());
        $this->assertSame($expectedEnd->toDateTimeString(), $updatedWarrant->expires_on->toDateTimeString());
        $this->assertSame(Warrant::CURRENT_STATUS, $updatedWarrant->status);
    }

    public function testReleaseConflictingOfficersPushesUpcomingOfficerStart(): void
    {
        $office = TableRegistry::getTableLocator()->get('Officers.Offices')->get(2);
        $upcomingStart = DateTime::now()->addDays(30);
        $upcomingEnd = DateTime::now()->addMonths(6);
        $officer = $this->createOfficerConflictFixture(
            $office->id,
            Officer::UPCOMING_STATUS,
            $upcomingStart,
            $upcomingEnd,
            true,
        );
        $memberRoleId = $officer->granted_member_role_id;
        $newHireStart = DateTime::now()->addDays(10);
        $newHireEnd = DateTime::now()->addDays(40);
        $expectedStart = $newHireEnd->addSeconds(1);

        $this->actions->expects($this->once())
            ->method('queueMail')
            ->with(
                'KMP',
                'sendFromTemplate',
                $this->anything(),
                $this->callback(function (array $vars) {
                    return $vars['_templateId'] === 'officer-assignment-adjusted-notification';
                }),
            );
        $this->triggerDispatcher->expects($this->never())->method('dispatch');

        $result = $this->actions->releaseConflictingOfficers(
            ['triggeredBy' => self::ADMIN_MEMBER_ID],
            [
                'officeId' => $office->id,
                'branchId' => self::KINGDOM_BRANCH_ID,
                'newOfficerStartDate' => $newHireStart->format('Y-m-d H:i:s'),
                'newOfficerEndDate' => $newHireEnd->format('Y-m-d H:i:s'),
            ],
        );

        $updatedOfficer = TableRegistry::getTableLocator()->get('Officers.Officers')->get($officer->id);
        $updatedRole = TableRegistry::getTableLocator()->get('MemberRoles')->get($memberRoleId);
        $updatedWarrant = TableRegistry::getTableLocator()->get('Warrants')
            ->find()
            ->where(['entity_type' => 'Officers.Officers', 'entity_id' => $officer->id])
            ->firstOrFail();

        $this->assertSame([], $result['releasedOfficerIds']);
        $this->assertContains($officer->id, $result['adjustedOfficerIds']);
        $this->assertSame($expectedStart->toDateTimeString(), $updatedOfficer->start_on->toDateTimeString());
        $this->assertSame($upcomingEnd->toDateTimeString(), $updatedOfficer->expires_on->toDateTimeString());
        $this->assertSame(Officer::UPCOMING_STATUS, $updatedOfficer->status);
        $this->assertSame($expectedStart->toDateTimeString(), $updatedRole->start_on->toDateTimeString());
        $this->assertSame($expectedStart->toDateTimeString(), $updatedWarrant->start_on->toDateTimeString());
        $this->assertSame(Warrant::PENDING_STATUS, $updatedWarrant->status);
    }

    public function testReleaseConflictingOfficersReleasesFullyCoveredUpcomingOfficer(): void
    {
        $office = TableRegistry::getTableLocator()->get('Officers.Offices')->get(6);
        $officer = $this->createOfficerConflictFixture(
            $office->id,
            Officer::UPCOMING_STATUS,
            DateTime::now()->addDays(30),
            DateTime::now()->addDays(60),
        );

        $this->triggerDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                'Officers.Released',
                $this->callback(function (array $payload) use ($officer) {
                    return $payload['officerId'] === $officer->id
                        && $payload['releaseStatus'] === Officer::REPLACED_STATUS;
                }),
                self::ADMIN_MEMBER_ID,
            )
            ->willReturn([new ServiceResult(true, null, ['instanceId' => 222])]);
        $this->actions->expects($this->never())->method('queueMail');

        $result = $this->actions->releaseConflictingOfficers(
            ['triggeredBy' => self::ADMIN_MEMBER_ID],
            [
                'officeId' => $office->id,
                'branchId' => self::KINGDOM_BRANCH_ID,
                'newOfficerStartDate' => DateTime::now()->addDays(15)->format('Y-m-d H:i:s'),
                'newOfficerEndDate' => DateTime::now()->addDays(90)->format('Y-m-d H:i:s'),
            ],
        );

        $this->assertContains($officer->id, $result['releasedOfficerIds']);
        $this->assertSame([], $result['adjustedOfficerIds']);
    }

    public function testReleaseConflictingOfficersWithNoConflictsReturnsEmpty(): void
    {
        $context = ['triggeredBy' => self::ADMIN_MEMBER_ID];
        $config = [
            'officeId' => 6,
            'branchId' => 999999,
            'newOfficerStartDate' => DateTime::now()->format('Y-m-d H:i:s'),
            'newOfficerEndDate' => DateTime::now()->addDays(10)->format('Y-m-d H:i:s'),
        ];

        $this->triggerDispatcher->expects($this->never())->method('dispatch');

        $result = $this->actions->releaseConflictingOfficers($context, $config);

        $this->assertEmpty($result['releasedOfficerIds']);
        $this->assertEmpty($result['adjustedOfficerIds']);
    }

    // =====================================================
    // RequestWarrantRoster action
    // =====================================================

    public function testRequestWarrantRosterCreatesRosterRequest(): void
    {
        $officerTable = TableRegistry::getTableLocator()->get('Officers.Officers');
        $office = TableRegistry::getTableLocator()->get('Officers.Offices')->get(2);

        $officer = $officerTable->newEntity([
            'member_id' => self::TEST_MEMBER_AGATHA_ID,
            'office_id' => $office->id,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'status' => Officer::CURRENT_STATUS,
            'start_on' => DateTime::now()->subDays(1),
            'expires_on' => DateTime::now()->addMonths(3),
            'approver_id' => self::ADMIN_MEMBER_ID,
            'approval_date' => DateTime::now(),
            'deputy_description' => 'North',
            'granted_member_role_id' => null,
        ]);
        $officerTable->saveOrFail($officer);

        $this->warrantManager->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains($office->name),
                '',
                $this->callback(function (array $requests) use ($officer) {
                    $this->assertCount(1, $requests);
                    $this->assertInstanceOf(WarrantRequest::class, $requests[0]);
                    $this->assertSame('Officers.Officers', $requests[0]->entity_type);
                    $this->assertSame($officer->id, $requests[0]->entity_id);
                    $this->assertSame(self::TEST_MEMBER_AGATHA_ID, $requests[0]->member_id);
                    $this->assertStringContainsString('North', $requests[0]->name);

                    return true;
                }),
                self::ADMIN_MEMBER_ID,
            )
            ->willReturn(new ServiceResult(true, null, 1234));

        $result = $this->actions->requestWarrantRoster(
            ['triggeredBy' => self::ADMIN_MEMBER_ID],
            ['officerId' => $officer->id],
        );

        $this->assertSame(1234, $result['rosterId']);
    }

    // =====================================================
    // RecalculateOfficersForOffice action
    // =====================================================

    public function testRecalculateOfficersForOfficeDelegatesToManager(): void
    {
        $this->officerManager->expects($this->once())
            ->method('recalculateOfficersForOffice')
            ->with(6, self::ADMIN_MEMBER_ID)
            ->willReturn(new ServiceResult(true, null, [
                'updated_count' => 3,
                'current_count' => 2,
                'upcoming_count' => 1,
            ]));

        $context = ['triggeredBy' => self::ADMIN_MEMBER_ID];
        $config = [
            'officeId' => 6,
            'updaterId' => self::ADMIN_MEMBER_ID,
        ];

        $result = $this->actions->recalculateOfficersForOffice($context, $config);

        $this->assertEquals(3, $result['updatedCount']);
    }

    public function testRecalculateOfficersForOfficeHandlesFailure(): void
    {
        $this->officerManager->expects($this->once())
            ->method('recalculateOfficersForOffice')
            ->willReturn(new ServiceResult(false, 'Some error'));

        $context = ['triggeredBy' => self::ADMIN_MEMBER_ID];
        $config = ['officeId' => 6];

        $result = $this->actions->recalculateOfficersForOffice($context, $config);

        $this->assertEquals(0, $result['updatedCount']);
    }

    public function testRecalculateOfficersWithoutManagerReturnsZero(): void
    {
        // Create actions instance without officer manager
        $actions = new OfficerWorkflowActions(
            $this->activeWindowManager,
            $this->warrantManager,
            null,
            null,
        );

        $context = ['triggeredBy' => self::ADMIN_MEMBER_ID];
        $config = ['officeId' => 6];

        $result = $actions->recalculateOfficersForOffice($context, $config);

        $this->assertEquals(0, $result['updatedCount']);
    }

    // =====================================================
    // Hire notification vars
    // =====================================================

    public function testPrepareHireNotificationVarsIncludesWarrantNoticeWhenRequired(): void
    {
        $officerTable = TableRegistry::getTableLocator()->get('Officers.Officers');
        $office = TableRegistry::getTableLocator()->get('Officers.Offices')->get(2);

        $officer = $officerTable->newEntity([
            'member_id' => self::TEST_MEMBER_AGATHA_ID,
            'office_id' => $office->id,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'status' => Officer::CURRENT_STATUS,
            'start_on' => DateTime::now()->subDays(1),
            'expires_on' => DateTime::now()->addMonths(2),
            'approver_id' => self::ADMIN_MEMBER_ID,
            'approval_date' => DateTime::now(),
        ]);
        $officerTable->saveOrFail($officer);

        $result = $this->actions->prepareHireNotificationVars([], ['officerId' => $officer->id]);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('requires a warrant', $result['data']['requiresWarrantNotice']);
    }

    public function testPrepareHireNotificationVarsLeavesWarrantNoticeBlankWhenNotRequired(): void
    {
        $officerTable = TableRegistry::getTableLocator()->get('Officers.Officers');
        $office = TableRegistry::getTableLocator()->get('Officers.Offices')->find()
            ->where(['requires_warrant' => false])
            ->firstOrFail();

        $officer = $officerTable->newEntity([
            'member_id' => self::TEST_MEMBER_BRYCE_ID,
            'office_id' => $office->id,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'status' => Officer::CURRENT_STATUS,
            'start_on' => DateTime::now()->subDays(1),
            'expires_on' => DateTime::now()->addMonths(2),
            'approver_id' => self::ADMIN_MEMBER_ID,
            'approval_date' => DateTime::now(),
        ]);
        $officerTable->saveOrFail($officer);

        $result = $this->actions->prepareHireNotificationVars([], ['officerId' => $officer->id]);

        $this->assertTrue($result['success']);
        $this->assertSame('', $result['data']['requiresWarrantNotice']);
    }

    // =====================================================
    // ReleaseOfficer action
    // =====================================================

    public function testReleaseOfficerStopsActiveWindowAndCancelsWarrantWhenRequired(): void
    {
        $officerTable = TableRegistry::getTableLocator()->get('Officers.Officers');
        $office = TableRegistry::getTableLocator()->get('Officers.Offices')->get(2); // requires_warrant = 1

        $officer = $officerTable->newEntity([
            'member_id' => self::TEST_MEMBER_AGATHA_ID,
            'office_id' => $office->id,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'status' => Officer::CURRENT_STATUS,
            'start_on' => DateTime::now()->subMonths(1),
            'expires_on' => DateTime::now()->addMonths(1),
            'approver_id' => self::ADMIN_MEMBER_ID,
            'approval_date' => DateTime::now(),
        ]);
        $officerTable->saveOrFail($officer);

        $releaseDate = DateTime::now();
        $matchesReleaseDate = $this->callback(fn ($value) => $value instanceof DateTime
            && $value->format('Y-m-d H:i:s') === $releaseDate->format('Y-m-d H:i:s'));

        $this->activeWindowManager->expects($this->once())
            ->method('stop')
            ->with(
                'Officers.Officers',
                $officer->id,
                self::ADMIN_MEMBER_ID,
                Officer::RELEASED_STATUS,
                'Stepping down',
                $matchesReleaseDate,
            )
            ->willReturn(new ServiceResult(true));

        $this->warrantManager->expects($this->once())
            ->method('cancelByEntity')
            ->with(
                'Officers.Officers',
                $officer->id,
                'Stepping down',
                self::ADMIN_MEMBER_ID,
                $matchesReleaseDate,
            )
            ->willReturn(new ServiceResult(true));

        $result = $this->actions->releaseOfficer(
            ['triggeredBy' => self::ADMIN_MEMBER_ID],
            [
                'officerId' => $officer->id,
                'releasedById' => self::ADMIN_MEMBER_ID,
                'reason' => 'Stepping down',
                'expiresOn' => $releaseDate->format('Y-m-d H:i:s'),
            ],
        );

        $this->assertTrue($result['released']);
        $this->assertSame($officer->id, $result['officerId']);
    }

    public function testReleaseOfficerSkipsWarrantCancellationWhenOfficeDoesNotRequireOne(): void
    {
        $officerTable = TableRegistry::getTableLocator()->get('Officers.Officers');
        $office = TableRegistry::getTableLocator()->get('Officers.Offices')->find()
            ->where(['requires_warrant' => false])
            ->firstOrFail();

        $officer = $officerTable->newEntity([
            'member_id' => self::TEST_MEMBER_BRYCE_ID,
            'office_id' => $office->id,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'status' => Officer::CURRENT_STATUS,
            'start_on' => DateTime::now()->subMonths(1),
            'expires_on' => DateTime::now()->addMonths(1),
            'approver_id' => self::ADMIN_MEMBER_ID,
            'approval_date' => DateTime::now(),
        ]);
        $officerTable->saveOrFail($officer);

        $this->activeWindowManager->expects($this->once())
            ->method('stop')
            ->willReturn(new ServiceResult(true));

        $this->warrantManager->expects($this->never())
            ->method('cancelByEntity');

        $result = $this->actions->releaseOfficer(
            ['triggeredBy' => self::ADMIN_MEMBER_ID],
            [
                'officerId' => $officer->id,
                'releasedById' => self::ADMIN_MEMBER_ID,
                'reason' => 'End of term',
                'expiresOn' => DateTime::now()->format('Y-m-d H:i:s'),
            ],
        );

        $this->assertTrue($result['released']);
    }

    public function testReleaseOfficerThrowsWhenActiveWindowStopFails(): void
    {
        $officerTable = TableRegistry::getTableLocator()->get('Officers.Officers');
        $office = TableRegistry::getTableLocator()->get('Officers.Offices')->get(2);

        $officer = $officerTable->newEntity([
            'member_id' => self::TEST_MEMBER_AGATHA_ID,
            'office_id' => $office->id,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'status' => Officer::CURRENT_STATUS,
            'start_on' => DateTime::now()->subMonths(1),
            'expires_on' => DateTime::now()->addMonths(1),
            'approver_id' => self::ADMIN_MEMBER_ID,
            'approval_date' => DateTime::now(),
        ]);
        $officerTable->saveOrFail($officer);

        $this->activeWindowManager->expects($this->once())
            ->method('stop')
            ->willReturn(new ServiceResult(false, 'Unable to stop active window'));

        $this->warrantManager->expects($this->never())
            ->method('cancelByEntity');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to release officer active window: Unable to stop active window');

        $this->actions->releaseOfficer(
            ['triggeredBy' => self::ADMIN_MEMBER_ID],
            [
                'officerId' => $officer->id,
                'releasedById' => self::ADMIN_MEMBER_ID,
                'reason' => 'End of term',
                'expiresOn' => DateTime::now()->format('Y-m-d H:i:s'),
            ],
        );
    }

    // =====================================================
    // HasConflictingOfficer condition
    // =====================================================

    public function testHasConflictingOfficerReturnsTrueWhenConflictExists(): void
    {
        // Officer 932 is Agatha (Current) in office 14, branch 31
        $context = [];
        $config = [
            'officeId' => 14,
            'branchId' => 31,
            'newOfficerStartDate' => DateTime::now()->format('Y-m-d H:i:s'),
        ];

        $result = $this->conditions->hasConflictingOfficer($context, $config);

        $this->assertTrue($result);
    }

    public function testHasConflictingOfficerReturnsFalseWhenNone(): void
    {
        $context = [];
        $config = [
            'officeId' => 999999,
            'branchId' => self::KINGDOM_BRANCH_ID,
            'newOfficerStartDate' => DateTime::now()->format('Y-m-d H:i:s'),
        ];

        $result = $this->conditions->hasConflictingOfficer($context, $config);

        $this->assertFalse($result);
    }

    public function testHasConflictingOfficerReturnsFalseWithMissingParams(): void
    {
        $context = [];
        $config = [];

        $result = $this->conditions->hasConflictingOfficer($context, $config);

        $this->assertFalse($result);
    }

    private function createOfficerConflictFixture(
        int $officeId,
        string $status,
        DateTime $startOn,
        ?DateTime $expiresOn,
        bool $withWarrant = false,
    ): Officer {
        $officerTable = TableRegistry::getTableLocator()->get('Officers.Officers');
        $memberRoleTable = TableRegistry::getTableLocator()->get('MemberRoles');
        $rolesTable = TableRegistry::getTableLocator()->get('Roles');

        $officer = $officerTable->newEntity([
            'member_id' => self::TEST_MEMBER_AGATHA_ID,
            'office_id' => $officeId,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'status' => $status,
            'start_on' => $startOn,
            'expires_on' => $expiresOn,
            'approver_id' => self::ADMIN_MEMBER_ID,
            'approval_date' => DateTime::now(),
            'email_address' => 'agatha@example.test',
        ]);
        $officerTable->saveOrFail($officer);

        $role = $rolesTable->find()->firstOrFail();
        $memberRole = $memberRoleTable->newEntity([
            'member_id' => self::TEST_MEMBER_AGATHA_ID,
            'role_id' => $role->id,
            'entity_type' => 'Officers.Officers',
            'entity_id' => $officer->id,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'status' => $status,
            'start_on' => $startOn,
            'expires_on' => $expiresOn,
            'approver_id' => self::ADMIN_MEMBER_ID,
        ], ['accessibleFields' => ['*' => true]]);
        $memberRoleTable->saveOrFail($memberRole);

        $officer->granted_member_role_id = $memberRole->id;
        $officerTable->saveOrFail($officer);

        if ($withWarrant) {
            $rosterTable = TableRegistry::getTableLocator()->get('WarrantRosters');
            $warrantTable = TableRegistry::getTableLocator()->get('Warrants');

            $roster = $rosterTable->newEntity([
                'name' => 'Officer overlap roster ' . uniqid(),
                'approvals_required' => 1,
                'approval_count' => $status === Officer::CURRENT_STATUS ? 1 : 0,
                'status' => $status === Officer::CURRENT_STATUS ? Warrant::CURRENT_STATUS : Warrant::PENDING_STATUS,
                'created_by' => self::ADMIN_MEMBER_ID,
            ], ['accessibleFields' => ['*' => true]]);
            $rosterTable->saveOrFail($roster);

            $warrant = $warrantTable->newEntity([
                'name' => 'Officer overlap warrant ' . uniqid(),
                'member_id' => self::TEST_MEMBER_AGATHA_ID,
                'warrant_roster_id' => $roster->id,
                'entity_type' => 'Officers.Officers',
                'entity_id' => $officer->id,
                'member_role_id' => $memberRole->id,
                'requester_id' => self::ADMIN_MEMBER_ID,
                'start_on' => $startOn,
                'expires_on' => $expiresOn,
                'status' => $status === Officer::CURRENT_STATUS ? Warrant::CURRENT_STATUS : Warrant::PENDING_STATUS,
            ], ['accessibleFields' => ['*' => true]]);
            $warrantTable->saveOrFail($warrant);
        }

        return $officerTable->get($officer->id, contain: ['Offices']);
    }

    // =====================================================
    // Existing conditions (verify they still work)
    // =====================================================

    public function testOfficeRequiresWarrantReturnsTrue(): void
    {
        // Office 2 = Kingdom Earl Marshal, requires_warrant = 1
        $result = $this->conditions->officeRequiresWarrant([], ['officeId' => 2]);
        $this->assertTrue($result);
    }

    public function testIsOnlyOnePerBranchReturnsTrue(): void
    {
        // Office 2 = Kingdom Earl Marshal, only_one_per_branch = 1
        $result = $this->conditions->isOnlyOnePerBranch([], ['officeId' => 2]);
        $this->assertTrue($result);
    }

    public function testIsMemberWarrantableReturnsCorrectly(): void
    {
        // Agatha is warrantable (1), Devon is not (0)
        $this->assertTrue($this->conditions->isMemberWarrantable([], ['memberId' => self::TEST_MEMBER_AGATHA_ID]));
        $this->assertFalse($this->conditions->isMemberWarrantable([], ['memberId' => self::TEST_MEMBER_DEVON_ID]));
    }
}
