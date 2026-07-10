<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Entity\WorkflowDefinition;
use App\Test\TestCase\BaseTestCase;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

class WorkflowSchedulesTableTest extends BaseTestCase
{
    public function testGetOrCreateForDefinitionReturnsExistingSchedule(): void
    {
        [$schedulesTable, $schedule] = $this->createSchedule();

        $result = $schedulesTable->getOrCreateForDefinition((int)$schedule->workflow_definition_id);

        $this->assertSame($schedule->id, $result->id);
        $this->assertSame(
            1,
            $schedulesTable->find()
                ->where(['workflow_definition_id' => $schedule->workflow_definition_id])
                ->count(),
        );
    }

    public function testClaimsAreAtomicAndStaleSnapshotsCannotReclaimCompletedRuns(): void
    {
        [$schedulesTable, $schedule] = $this->createSchedule();
        $staleSchedule = $schedulesTable->get($schedule->id);
        $now = DateTime::now();

        $claimToken = $schedulesTable->claimExecution($schedule, $now);
        $this->assertNotNull($claimToken);
        $this->assertNull($schedulesTable->claimExecution($staleSchedule, $now));

        $nextRunAt = (clone $now)->modify('+1 hour');
        $this->assertTrue(
            $schedulesTable->completeExecutionClaim(
                (int)$schedule->id,
                $claimToken,
                $now,
                $nextRunAt,
            ),
        );
        $this->assertNull(
            $schedulesTable->claimExecution($staleSchedule, (clone $now)->modify('+1 second')),
        );
    }

    public function testFailedClaimCanBeReleasedAndRetried(): void
    {
        [$schedulesTable, $schedule] = $this->createSchedule();
        $now = DateTime::now();

        $claimToken = $schedulesTable->claimExecution($schedule, $now);
        $this->assertNotNull($claimToken);
        $this->assertTrue($schedulesTable->releaseExecutionClaim((int)$schedule->id, $claimToken));

        $freshSchedule = $schedulesTable->get($schedule->id);
        $retryToken = $schedulesTable->claimExecution(
            $freshSchedule,
            (clone $now)->modify('+1 second'),
        );
        $this->assertNotNull($retryToken);
    }

    public function testExpiredClaimCanBeRecoveredAfterWorkerCrash(): void
    {
        [$schedulesTable, $schedule] = $this->createSchedule();
        $oldClaimTime = DateTime::now()->modify('-20 minutes');

        $oldToken = $schedulesTable->claimExecution($schedule, $oldClaimTime);
        $this->assertNotNull($oldToken);

        $claimedSchedule = $schedulesTable->get($schedule->id);
        $recoveryToken = $schedulesTable->claimExecution($claimedSchedule, DateTime::now());
        $this->assertNotNull($recoveryToken);
        $this->assertNotSame($oldToken, $recoveryToken);
    }

    public function testActiveClaimCannotBeRecoveredBeforeLeaseExpires(): void
    {
        [$schedulesTable, $schedule] = $this->createSchedule();
        $claimTime = DateTime::now();

        $claimToken = $schedulesTable->claimExecution($schedule, $claimTime);
        $this->assertNotNull($claimToken);

        $claimedSchedule = $schedulesTable->get($schedule->id);
        $prematureToken = $schedulesTable->claimExecution(
            $claimedSchedule,
            $claimTime->modify('+14 minutes'),
        );
        $this->assertNull($prematureToken);
    }

    private function createSchedule(): array
    {
        $definitionsTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $definition = $definitionsTable->newEntity([
            'name' => 'Schedule Claim ' . uniqid(),
            'slug' => 'schedule-claim-' . uniqid(),
            'trigger_type' => WorkflowDefinition::TRIGGER_SCHEDULED,
        ]);
        $definitionsTable->saveOrFail($definition);

        $schedulesTable = TableRegistry::getTableLocator()->get('WorkflowSchedules');
        $schedule = $schedulesTable->newEntity([
            'workflow_definition_id' => $definition->id,
            'is_enabled' => true,
        ]);
        $schedulesTable->saveOrFail($schedule);

        return [$schedulesTable, $schedule];
    }
}
