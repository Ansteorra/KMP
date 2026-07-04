<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\WorkflowEngine\Providers;

use App\Model\Entity\WarrantPeriod;
use App\Services\ServiceResult;
use App\Services\WarrantManager\WarrantManagerInterface;
use App\Services\WorkflowEngine\Providers\WarrantWorkflowActions;
use App\Services\WorkflowEngine\Providers\WarrantWorkflowConditions;
use App\Services\WorkflowEngine\Providers\WarrantWorkflowProvider;
use App\Services\WorkflowRegistry\WorkflowActionRegistry;
use App\Services\WorkflowRegistry\WorkflowConditionRegistry;
use App\Services\WorkflowRegistry\WorkflowTriggerRegistry;
use App\Test\TestCase\BaseTestCase;
use Cake\I18n\DateTime;

/**
 * Tests for Warrant workflow actions, conditions, and provider registration.
 */
class WarrantsWorkflowActionsTest extends BaseTestCase
{
    private WarrantWorkflowActions $actions;
    private WarrantWorkflowConditions $conditions;
    private WarrantManagerInterface $warrantManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->warrantManager = $this->createMock(WarrantManagerInterface::class);
        $this->actions = new WarrantWorkflowActions($this->warrantManager);
        $this->conditions = new WarrantWorkflowConditions();
    }

    protected function tearDown(): void
    {
        WorkflowActionRegistry::clear();
        WorkflowConditionRegistry::clear();
        WorkflowTriggerRegistry::clear();
        parent::tearDown();
    }

    // =====================================================
    // Provider Registration
    // =====================================================

    public function testProviderRegistersAllActions(): void
    {
        WarrantWorkflowProvider::register();
        $actions = WorkflowActionRegistry::getActionsBySource('Warrants');
        $actionKeys = array_column($actions, 'action');

        $this->assertContains('Warrants.CreateWarrantRoster', $actionKeys);
        $this->assertContains('Warrants.ActivateWarrants', $actionKeys);
        $this->assertContains('Warrants.CreateDirectWarrant', $actionKeys);
        $this->assertContains('Warrants.DeclineRoster', $actionKeys);
        $this->assertContains('Warrants.NotifyWarrantIssued', $actionKeys);
        $this->assertContains('Warrants.RevokeWarrant', $actionKeys);
        $this->assertContains('Warrants.CancelByEntity', $actionKeys);
        $this->assertContains('Warrants.DeclineSingleWarrant', $actionKeys);
        $this->assertContains('Warrants.ValidateWarrantability', $actionKeys);
        $this->assertContains('Warrants.GetWarrantPeriod', $actionKeys);
    }

    public function testProviderRegistersAllConditions(): void
    {
        WarrantWorkflowProvider::register();
        $conditions = WorkflowConditionRegistry::getConditionsBySource('Warrants');
        $conditionKeys = array_column($conditions, 'condition');

        $this->assertContains('Warrants.IsMemberWarrantable', $conditionKeys);
        $this->assertContains('Warrants.HasRequiredApprovals', $conditionKeys);
        $this->assertContains('Warrants.IsWithinWarrantPeriod', $conditionKeys);
        $this->assertContains('Warrants.IsRosterApproved', $conditionKeys);
        $this->assertContains('Warrants.IsWarrantActive', $conditionKeys);
    }

    public function testProviderRegistersAllTriggers(): void
    {
        WarrantWorkflowProvider::register();
        $triggers = WorkflowTriggerRegistry::getTriggersBySource('Warrants');
        $eventKeys = array_column($triggers, 'event');

        $this->assertContains('Warrants.RosterCreated', $eventKeys);
        $this->assertContains('Warrants.Approved', $eventKeys);
        $this->assertContains('Warrants.Declined', $eventKeys);
        $this->assertContains('Warrants.WarrantRevoked', $eventKeys);
        $this->assertContains('Warrants.WarrantExpired', $eventKeys);
    }

    // =====================================================
    // revokeWarrant action
    // =====================================================

    public function testRevokeWarrantSuccess(): void
    {
        $this->warrantManager->expects($this->once())
            ->method('cancel')
            ->with(
                42,
                'No longer needed',
                self::ADMIN_MEMBER_ID,
                $this->isInstanceOf(DateTime::class),
            )
            ->willReturn(new ServiceResult(true));

        $context = ['triggeredBy' => self::ADMIN_MEMBER_ID];
        $config = [
            'warrantId' => 42,
            'reason' => 'No longer needed',
            'expiresOn' => '2025-06-01',
        ];

        $result = $this->actions->revokeWarrant($context, $config);

        $this->assertTrue($result['revoked']);
    }

    public function testRevokeWarrantFailure(): void
    {
        $this->warrantManager->expects($this->once())
            ->method('cancel')
            ->willReturn(new ServiceResult(false, 'Warrant not found'));

        $context = ['triggeredBy' => self::ADMIN_MEMBER_ID];
        $config = [
            'warrantId' => 999,
            'reason' => 'Test',
        ];

        $result = $this->actions->revokeWarrant($context, $config);

        $this->assertFalse($result['success']);
        $this->assertSame('Warrant not found', $result['error']);
    }

    // =====================================================
    // cancelByEntity action
    // =====================================================

    public function testCancelByEntitySuccess(): void
    {
        $this->warrantManager->expects($this->once())
            ->method('cancelByEntity')
            ->with(
                'Branches',
                10,
                'Branch dissolved',
                self::ADMIN_MEMBER_ID,
                $this->isInstanceOf(DateTime::class),
            )
            ->willReturn(new ServiceResult(true));

        $context = ['triggeredBy' => self::ADMIN_MEMBER_ID];
        $config = [
            'entityType' => 'Branches',
            'entityId' => 10,
            'reason' => 'Branch dissolved',
        ];

        $result = $this->actions->cancelByEntity($context, $config);

        $this->assertTrue($result['cancelled']);
    }

    public function testCancelByEntityFailure(): void
    {
        $this->warrantManager->expects($this->once())
            ->method('cancelByEntity')
            ->willReturn(new ServiceResult(false, 'Error'));

        $context = ['triggeredBy' => self::ADMIN_MEMBER_ID];
        $config = [
            'entityType' => 'Branches',
            'entityId' => 999,
            'reason' => 'Test',
        ];

        $result = $this->actions->cancelByEntity($context, $config);

        $this->assertFalse($result['cancelled']);
    }

    // =====================================================
    // declineSingleWarrant action
    // =====================================================

    public function testDeclineSingleWarrantSuccess(): void
    {
        $this->warrantManager->expects($this->once())
            ->method('declineSingleWarrant')
            ->with(42, 'Not qualified', self::ADMIN_MEMBER_ID)
            ->willReturn(new ServiceResult(true));

        $context = ['triggeredBy' => self::ADMIN_MEMBER_ID];
        $config = [
            'warrantId' => 42,
            'reason' => 'Not qualified',
        ];

        $result = $this->actions->declineSingleWarrant($context, $config);

        $this->assertTrue($result['declined']);
    }

    public function testDeclineSingleWarrantFailure(): void
    {
        $this->warrantManager->expects($this->once())
            ->method('declineSingleWarrant')
            ->willReturn(new ServiceResult(false, 'Warrant not found'));

        $context = ['triggeredBy' => self::ADMIN_MEMBER_ID];
        $config = [
            'warrantId' => 999,
            'reason' => 'Test',
        ];

        $result = $this->actions->declineSingleWarrant($context, $config);

        $this->assertFalse($result['declined']);
    }

    // =====================================================
    // validateWarrantability action
    // =====================================================

    public function testValidateWarrantabilitySuccess(): void
    {
        // Agatha is warrantable
        $context = ['triggeredBy' => self::ADMIN_MEMBER_ID];
        $config = ['memberId' => self::TEST_MEMBER_AGATHA_ID];

        $result = $this->actions->validateWarrantability($context, $config);

        $this->assertTrue($result['warrantable']);
        $this->assertNull($result['reason']);
    }

    public function testValidateWarrantabilityNotWarrantable(): void
    {
        // Devon is not warrantable
        $context = ['triggeredBy' => self::ADMIN_MEMBER_ID];
        $config = ['memberId' => self::TEST_MEMBER_DEVON_ID];

        $result = $this->actions->validateWarrantability($context, $config);

        $this->assertFalse($result['warrantable']);
        $this->assertEquals('Member is not warrantable', $result['reason']);
    }

    public function testValidateWarrantabilityInvalidMember(): void
    {
        $context = ['triggeredBy' => self::ADMIN_MEMBER_ID];
        $config = ['memberId' => 999999];

        $result = $this->actions->validateWarrantability($context, $config);

        $this->assertFalse($result['warrantable']);
        $this->assertNotNull($result['reason']);
    }

    // =====================================================
    // getWarrantPeriod action
    // =====================================================

    public function testGetWarrantPeriodReturnsDates(): void
    {
        $period = new WarrantPeriod();
        $period->id = 7;
        $period->start_date = new DateTime('2025-01-01');
        $period->end_date = new DateTime('2025-12-31');

        $this->warrantManager->expects($this->once())
            ->method('getWarrantPeriod')
            ->with(
                $this->isInstanceOf(DateTime::class),
                $this->isInstanceOf(DateTime::class),
            )
            ->willReturn($period);

        $context = ['triggeredBy' => self::ADMIN_MEMBER_ID];
        $config = [
            'startOn' => '2025-01-01',
            'endOn' => '2025-12-31',
        ];

        $result = $this->actions->getWarrantPeriod($context, $config);

        $this->assertEquals('2025-01-01', $result['startDate']);
        $this->assertEquals('2025-12-31', $result['endDate']);
        $this->assertEquals(7, $result['periodId']);
    }

    public function testGetWarrantPeriodReturnsNullWhenNoPeriod(): void
    {
        $this->warrantManager->expects($this->once())
            ->method('getWarrantPeriod')
            ->willReturn(null);

        $context = ['triggeredBy' => self::ADMIN_MEMBER_ID];
        $config = ['startOn' => '2025-01-01'];

        $result = $this->actions->getWarrantPeriod($context, $config);

        $this->assertNull($result['startDate']);
        $this->assertNull($result['endDate']);
        $this->assertNull($result['periodId']);
    }

    // =====================================================
    // isMemberWarrantable condition
    // =====================================================

    public function testIsMemberWarrantableTrue(): void
    {
        // Agatha is warrantable
        $result = $this->conditions->isMemberWarrantable(
            [],
            ['memberId' => self::TEST_MEMBER_AGATHA_ID],
        );

        $this->assertTrue($result);
    }

    public function testIsMemberWarrantableFalse(): void
    {
        // Devon is not warrantable
        $result = $this->conditions->isMemberWarrantable(
            [],
            ['memberId' => self::TEST_MEMBER_DEVON_ID],
        );

        $this->assertFalse($result);
    }

    public function testIsMemberWarrantableMissingId(): void
    {
        $result = $this->conditions->isMemberWarrantable([], []);

        $this->assertFalse($result);
    }

    // =====================================================
    // hasRequiredApprovals condition
    // =====================================================

    public function testHasRequiredApprovalsReturnsFalseForMissingRoster(): void
    {
        $result = $this->conditions->hasRequiredApprovals([], []);

        $this->assertFalse($result);
    }

    public function testHasRequiredApprovalsReturnsFalseForInvalidRoster(): void
    {
        $result = $this->conditions->hasRequiredApprovals(
            [],
            ['rosterId' => 999999],
        );

        $this->assertFalse($result);
    }

    // =====================================================
    // isWithinWarrantPeriod condition
    // =====================================================

    public function testIsWithinWarrantPeriodTrueForCurrentRange(): void
    {
        $result = $this->conditions->isWithinWarrantPeriod([], [
            'startOn' => DateTime::now()->subMonths(1)->format('Y-m-d'),
            'expiresOn' => DateTime::now()->addMonths(1)->format('Y-m-d'),
        ]);

        $this->assertTrue($result);
    }

    public function testIsWithinWarrantPeriodFalseForFutureStart(): void
    {
        $result = $this->conditions->isWithinWarrantPeriod([], [
            'startOn' => DateTime::now()->addMonths(1)->format('Y-m-d'),
            'expiresOn' => DateTime::now()->addMonths(2)->format('Y-m-d'),
        ]);

        $this->assertFalse($result);
    }

    public function testIsWithinWarrantPeriodFalseForExpiredPeriod(): void
    {
        $result = $this->conditions->isWithinWarrantPeriod([], [
            'startOn' => DateTime::now()->subMonths(2)->format('Y-m-d'),
            'expiresOn' => DateTime::now()->subMonths(1)->format('Y-m-d'),
        ]);

        $this->assertFalse($result);
    }

    public function testIsWithinWarrantPeriodFalseForMissingStart(): void
    {
        $result = $this->conditions->isWithinWarrantPeriod([], []);

        $this->assertFalse($result);
    }

    // =====================================================
    // isRosterApproved condition
    // =====================================================

    public function testIsRosterApprovedReturnsFalseForMissingRoster(): void
    {
        $result = $this->conditions->isRosterApproved([], []);

        $this->assertFalse($result);
    }

    public function testIsRosterApprovedReturnsFalseForInvalidRoster(): void
    {
        $result = $this->conditions->isRosterApproved(
            [],
            ['rosterId' => 999999],
        );

        $this->assertFalse($result);
    }

    // =====================================================
    // Context path resolution
    // =====================================================

    public function testRevokeWarrantResolvesContextPaths(): void
    {
        $this->warrantManager->expects($this->once())
            ->method('cancel')
            ->with(
                55,
                'Context reason',
                self::ADMIN_MEMBER_ID,
                $this->isInstanceOf(DateTime::class),
            )
            ->willReturn(new ServiceResult(true));

        $context = [
            'triggeredBy' => self::ADMIN_MEMBER_ID,
            'trigger' => [
                'warrantId' => 55,
                'reason' => 'Context reason',
            ],
        ];
        $config = [
            'warrantId' => '$.trigger.warrantId',
            'reason' => '$.trigger.reason',
        ];

        $result = $this->actions->revokeWarrant($context, $config);

        $this->assertTrue($result['revoked']);
    }
}
