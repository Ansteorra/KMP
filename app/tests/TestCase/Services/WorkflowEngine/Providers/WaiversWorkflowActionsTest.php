<?php

declare(strict_types=1);

namespace App\Test\TestCase\Services\WorkflowEngine\Providers;

use App\Services\ServiceResult;
use App\Services\WorkflowRegistry\WorkflowTriggerRegistry;
use App\Test\TestCase\BaseTestCase;
use Waivers\Services\WaiversWorkflowActions;
use Waivers\Services\WaiversWorkflowConditions;
use Waivers\Services\WaiversWorkflowProvider;
use Waivers\Services\WaiverStateService;
use Cake\I18n\Date;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

/**
 * Tests for Waivers plugin workflow actions and conditions.
 */
class WaiversWorkflowActionsTest extends BaseTestCase
{
    private WaiversWorkflowActions $actions;
    private WaiversWorkflowConditions $conditions;
    private $mockWaiverService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockWaiverService = $this->createMock(WaiverStateService::class);
        $this->actions = new WaiversWorkflowActions($this->mockWaiverService);
        $this->conditions = new WaiversWorkflowConditions();
    }

    public function testProviderRegistersAllWaiverTriggers(): void
    {
        WorkflowTriggerRegistry::clear();
        WaiversWorkflowProvider::register();

        $triggers = WorkflowTriggerRegistry::getTriggersBySource('Waivers');
        $triggerEvents = array_column($triggers, 'event');

        $this->assertContains('Waivers.ReadyToClose', $triggerEvents);
        $this->assertContains('Waivers.CollectionClosed', $triggerEvents);
        $this->assertContains('Waivers.CollectionReopened', $triggerEvents);
        $this->assertContains('Waivers.WaiverDeclined', $triggerEvents);
    }

    // ==========================================================
    // MarkReadyToClose Action Tests
    // ==========================================================

    public function testMarkReadyToCloseSuccess(): void
    {
        $this->mockWaiverService->method('markReadyToClose')
            ->willReturn(new ServiceResult(true, 'Marked ready'));

        $result = $this->actions->markReadyToClose(
            ['triggeredBy' => 1],
            ['gatheringId' => 10, 'markedBy' => 1]
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(10, $result['data']['gatheringId']);
    }

    public function testMarkReadyToCloseFailure(): void
    {
        $this->mockWaiverService->method('markReadyToClose')
            ->willReturn(new ServiceResult(false, 'Already closed'));

        $result = $this->actions->markReadyToClose(
            [],
            ['gatheringId' => 10, 'markedBy' => 1]
        );

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['error']);
    }

    // ==========================================================
    // CloseWaiverCollection Action Tests
    // ==========================================================

    public function testCloseWaiverCollectionSuccess(): void
    {
        $this->mockWaiverService->method('close')
            ->willReturn(new ServiceResult(true, 'Closed'));

        $result = $this->actions->closeWaiverCollection(
            ['triggeredBy' => 1],
            ['gatheringId' => 20, 'closedBy' => 1]
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(20, $result['data']['gatheringId']);
    }

    public function testCloseWaiverCollectionFailure(): void
    {
        $this->mockWaiverService->method('close')
            ->willReturn(new ServiceResult(false, 'Already closed'));

        $result = $this->actions->closeWaiverCollection(
            [],
            ['gatheringId' => 20, 'closedBy' => 1]
        );

        $this->assertFalse($result['success']);
    }

    // ==========================================================
    // ReopenWaiverCollection Action Tests
    // ==========================================================

    public function testReopenWaiverCollectionSuccess(): void
    {
        $this->mockWaiverService->method('reopen')
            ->willReturn(new ServiceResult(true, 'Reopened'));

        $result = $this->actions->reopenWaiverCollection(
            [],
            ['gatheringId' => 30]
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(30, $result['data']['gatheringId']);
    }

    public function testReopenWaiverCollectionFailure(): void
    {
        $this->mockWaiverService->method('reopen')
            ->willReturn(new ServiceResult(false, 'Already open'));

        $result = $this->actions->reopenWaiverCollection(
            [],
            ['gatheringId' => 30]
        );

        $this->assertFalse($result['success']);
    }

    // ==========================================================
    // DeclineWaiver Action Tests
    // ==========================================================

    public function testDeclineWaiverSuccess(): void
    {
        $this->mockWaiverService->method('decline')
            ->willReturn(new ServiceResult(true, 'Declined'));

        $result = $this->actions->declineWaiver(
            [],
            ['waiverId' => 5, 'declineReason' => 'Invalid signature', 'declinedBy' => 1]
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(5, $result['data']['waiverId']);
    }

    public function testDeclineWaiverFailure(): void
    {
        $this->mockWaiverService->method('decline')
            ->willReturn(new ServiceResult(false, 'Already declined'));

        $result = $this->actions->declineWaiver(
            [],
            ['waiverId' => 5, 'declineReason' => 'Invalid', 'declinedBy' => 1]
        );

        $this->assertFalse($result['success']);
    }

    // ==========================================================
    // UnmarkReadyToClose Action Tests
    // ==========================================================

    public function testUnmarkReadyToCloseSuccess(): void
    {
        $this->mockWaiverService->method('unmarkReadyToClose')
            ->willReturn(new ServiceResult(true, 'Unmarked'));

        $result = $this->actions->unmarkReadyToClose(
            [],
            ['gatheringId' => 40]
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(40, $result['data']['gatheringId']);
    }

    // ==========================================================
    // Context Path Resolution Tests
    // ==========================================================

    public function testMarkReadyToCloseResolvesContextPaths(): void
    {
        $this->mockWaiverService->expects($this->once())
            ->method('markReadyToClose')
            ->with(42, 7)
            ->willReturn(new ServiceResult(true));

        $context = [
            'entity' => ['id' => 42],
            'actor' => ['id' => 7],
        ];

        $result = $this->actions->markReadyToClose($context, [
            'gatheringId' => '$.entity.id',
            'markedBy' => '$.actor.id',
        ]);

        $this->assertTrue($result['success']);
    }

    public function testCloseWaiverCollectionResolvesContextPaths(): void
    {
        $this->mockWaiverService->expects($this->once())
            ->method('close')
            ->with(55, 12)
            ->willReturn(new ServiceResult(true));

        $context = [
            'gathering' => ['id' => 55],
            'member' => ['id' => 12],
        ];

        $result = $this->actions->closeWaiverCollection($context, [
            'gatheringId' => '$.gathering.id',
            'closedBy' => '$.member.id',
        ]);

        $this->assertTrue($result['success']);
    }

    public function testDeclineWaiverResolvesContextPaths(): void
    {
        $this->mockWaiverService->expects($this->once())
            ->method('decline')
            ->with(88, 'Bad waiver', 3)
            ->willReturn(new ServiceResult(true));

        $context = [
            'waiver' => ['id' => 88],
            'reason' => 'Bad waiver',
            'actor' => ['id' => 3],
        ];

        $result = $this->actions->declineWaiver($context, [
            'waiverId' => '$.waiver.id',
            'declineReason' => '$.reason',
            'declinedBy' => '$.actor.id',
        ]);

        $this->assertTrue($result['success']);
    }

    // ==========================================================
    // Error Handling Tests
    // ==========================================================

    public function testActionHandlesExceptionGracefully(): void
    {
        $this->mockWaiverService->method('close')
            ->willThrowException(new \RuntimeException('Database error'));

        $result = $this->actions->closeWaiverCollection(
            [],
            ['gatheringId' => 1, 'closedBy' => 1]
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Database error', $result['error']);
    }

    // ==========================================================
    // Condition Tests
    // ==========================================================

    public function testIsReadyToCloseReturnsFalseForMissingParams(): void
    {
        $result = $this->conditions->isReadyToClose([], []);
        $this->assertFalse($result);
    }

    public function testIsClosedReturnsFalseForMissingParams(): void
    {
        $result = $this->conditions->isClosed([], []);
        $this->assertFalse($result);
    }

    public function testHasUndeclinedWaiversReturnsFalseForMissingParams(): void
    {
        $result = $this->conditions->hasUndeclinedWaivers([], []);
        $this->assertFalse($result);
    }

    public function testIsPastRetentionDateReturnsFalseForMissingParams(): void
    {
        $result = $this->conditions->isPastRetentionDate([], []);
        $this->assertFalse($result);
    }

    public function testIsReadyToCloseWithNonExistentGathering(): void
    {
        $this->skipIfPostgres();

        $result = $this->conditions->isReadyToClose(
            [],
            ['gatheringId' => 999999]
        );

        $this->assertFalse($result);
    }

    public function testIsClosedWithNonExistentGathering(): void
    {
        $this->skipIfPostgres();

        $result = $this->conditions->isClosed(
            [],
            ['gatheringId' => 999999]
        );

        $this->assertFalse($result);
    }

    public function testIsPastRetentionDateWithNonExistentWaiver(): void
    {
        $this->skipIfPostgres();

        $result = $this->conditions->isPastRetentionDate(
            [],
            ['waiverId' => 999999]
        );

        $this->assertFalse($result);
    }

    public function testConditionResolvesContextPaths(): void
    {
        $context = ['gathering' => ['id' => 999999]];

        // Should not throw — tests that context path resolution works
        $result = $this->conditions->isReadyToClose($context, [
            'gatheringId' => '$.gathering.id',
        ]);

        $this->assertIsBool($result);
    }
}
