<?php
declare(strict_types=1);

namespace Awards\Services;

use App\Model\Entity\WorkflowInstance;
use App\Services\ServiceResult;
use App\Services\WorkflowEngine\WorkflowEngineInterface;
use Awards\Model\Entity\ApprovalProcess;
use Awards\Model\Entity\Recommendation;
use Awards\Model\Entity\RecommendationApprovalRun;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use RuntimeException;
use Throwable;

/**
 * Restarts open award recommendation approvals against current configuration.
 */
class RecommendationApprovalWorkflowSyncService
{
    use LocatorAwareTrait;

    private const ACTIVE_SYNC_FAILURE_REASON =
        'Active recommendation approval workflow restart failed. Review server logs for details.';

    private const APPROVAL_STATES = [
        'Submitted',
        'In Consideration',
        'Awaiting Feedback',
    ];

    private RecommendationApprovalWorkflowLifecycleService $lifecycleService;

    /**
     * @param \App\Services\WorkflowEngine\WorkflowEngineInterface $workflowEngine Workflow runtime.
     * @param \Awards\Services\RecommendationApprovalWorkflowLifecycleService|null $lifecycleService Lifecycle owner.
     */
    public function __construct(
        private WorkflowEngineInterface $workflowEngine,
        ?RecommendationApprovalWorkflowLifecycleService $lifecycleService = null,
    ) {
        $this->lifecycleService = $lifecycleService ?? new RecommendationApprovalWorkflowLifecycleService();
    }

    /**
     * Count active recommendations assigned to a process but running an older configuration.
     */
    public function countOutdatedRecommendations(int $approvalProcessId): int
    {
        return count($this->findOutdatedRecommendationRunMap($approvalProcessId));
    }

    /**
     * Restart only outdated active recommendation approvals assigned to one current process.
     *
     * Active progress is never mapped into the replacement workflow. Each recommendation is
     * isolated in its own transaction so a failed restart preserves its original workflow.
     *
     * @param int $approvalProcessId Approval process whose assigned recommendations may restart.
     * @param int $actorId Member performing the synchronization.
     * @return \App\Services\ServiceResult
     */
    public function syncApprovalProcess(int $approvalProcessId, int $actorId): ServiceResult
    {
        $outdatedRunMap = $this->findOutdatedRecommendationRunMap($approvalProcessId);

        $summary = [
            'approvalProcessId' => $approvalProcessId,
            'candidateCount' => count($outdatedRunMap),
            'processedCount' => 0,
            'restartedCount' => 0,
            'cancelledRunCount' => 0,
            'activeRunSkippedCount' => 0,
            'activeRunFailedCount' => 0,
            'skippedCount' => 0,
            'failedCount' => 0,
            'failures' => [],
        ];

        $this->restartRecommendations($outdatedRunMap, $approvalProcessId, $actorId, $summary);

        $success = $summary['failedCount'] === 0;

        return new ServiceResult(
            $success,
            $success ? null : 'One or more outdated recommendation approval workflows could not be restarted.',
            $summary,
        );
    }

    /**
     * Find active runs that differ from the process or workflow currently assigned to their recommendations.
     *
     * @return array<int, array<int>> Active run IDs keyed by recommendation ID.
     */
    private function findOutdatedRecommendationRunMap(int $approvalProcessId): array
    {
        $process = $this->fetchTable('Awards.ApprovalProcesses')->get($approvalProcessId, contain: [
            'ApprovalProcessSteps',
        ]);
        $legacyChangedAt = $this->latestLegacyConfigurationChange($approvalProcessId);
        $runs = $this->fetchTable('Awards.RecommendationApprovalRuns')->find()
            ->contain([
                'Recommendations' => ['Awards'],
                'WorkflowInstances' => ['WorkflowDefinitions'],
            ])
            ->innerJoinWith('Recommendations.Awards', function ($query) use ($approvalProcessId) {
                return $query->where(['Awards.approval_process_id' => $approvalProcessId]);
            })
            ->where([
                'RecommendationApprovalRuns.status IN' =>
                    RecommendationApprovalWorkflowLifecycleService::ACTIVE_STATUSES,
                'RecommendationApprovalRuns.deleted IS' => null,
                'WorkflowInstances.status IN' => WorkflowInstance::ACTIVE_STATUSES,
                'Recommendations.status !=' => 'Closed',
                'Recommendations.state IN' => self::APPROVAL_STATES,
                'Recommendations.bestowal_id IS' => null,
                'Recommendations.recommendation_group_id IS' => null,
            ])
            ->orderBy([
                'RecommendationApprovalRuns.recommendation_id' => 'ASC',
                'RecommendationApprovalRuns.id' => 'ASC',
            ])
            ->all();

        $runIdsByRecommendation = [];
        $outdatedRecommendationIds = [];
        foreach ($runs as $run) {
            if (!$run instanceof RecommendationApprovalRun) {
                continue;
            }

            $recommendationId = (int)$run->recommendation_id;
            $runIdsByRecommendation[$recommendationId] ??= [];
            $runIdsByRecommendation[$recommendationId][] = (int)$run->id;
            if ($this->isRunOutdated($run, $process, $legacyChangedAt)) {
                $outdatedRecommendationIds[$recommendationId] = true;
            }
        }

        $outdated = [];
        foreach (array_keys($outdatedRecommendationIds) as $recommendationId) {
            $outdated[$recommendationId] = $runIdsByRecommendation[$recommendationId];
        }

        return $outdated;
    }

    /**
     * Determine whether one run predates the current process snapshot or workflow definition.
     */
    private function isRunOutdated(
        RecommendationApprovalRun $run,
        ApprovalProcess $process,
        ?DateTime $legacyChangedAt,
    ): bool {
        if ((int)$run->approval_process_id !== (int)$process->id) {
            return true;
        }

        $instance = $run->workflow_instance ?? null;
        $definition = $instance?->workflow_definition;
        if (
            $instance instanceof WorkflowInstance
            && $definition !== null
            && $definition->current_version_id !== null
            && (int)$instance->workflow_version_id !== (int)$definition->current_version_id
        ) {
            return true;
        }

        if (!empty($run->approval_process_signature)) {
            return !hash_equals(
                (string)$run->approval_process_signature,
                (string)$process->configuration_signature,
            );
        }

        return $legacyChangedAt !== null && $legacyChangedAt->greaterThan($run->started);
    }

    /**
     * Find the latest step change for pre-snapshot runs, including soft-deleted steps.
     */
    private function latestLegacyConfigurationChange(int $approvalProcessId): ?DateTime
    {
        $latest = null;
        $steps = $this->fetchTable('Awards.ApprovalProcessSteps')->find('withTrashed')
            ->where(['approval_process_id' => $approvalProcessId])
            ->all();
        foreach ($steps as $step) {
            foreach ([$step->created, $step->modified, $step->deleted] as $changedAt) {
                if ($changedAt instanceof DateTime && ($latest === null || $changedAt->greaterThan($latest))) {
                    $latest = $changedAt;
                }
            }
        }

        return $latest;
    }

    /**
     * Restart selected recommendations and add their outcomes to a bulk summary.
     *
     * @param array<int, array<int>> $outdatedRunMap Run IDs keyed by recommendation ID.
     * @param int $approvalProcessId Current approval process ID.
     * @param int $actorId Synchronizing member ID.
     * @param array<string, mixed> $summary Bulk summary, updated in place.
     */
    private function restartRecommendations(
        array $outdatedRunMap,
        int $approvalProcessId,
        int $actorId,
        array &$summary,
    ): void {
        foreach ($outdatedRunMap as $recommendationId => $expectedRunIds) {
            $summary['processedCount']++;
            try {
                $outcome = $this->restartRecommendation(
                    (int)$recommendationId,
                    $approvalProcessId,
                    $expectedRunIds,
                    $actorId,
                );
                if (($outcome['status'] ?? null) === 'restarted') {
                    $summary['restartedCount']++;
                    $summary['cancelledRunCount'] += (int)($outcome['cancelledRunCount'] ?? 0);
                } else {
                    $summary['activeRunSkippedCount']++;
                    $summary['skippedCount']++;
                }
            } catch (Throwable $exception) {
                $summary['activeRunFailedCount']++;
                $summary['failedCount']++;
                $summary['failures'][] = [
                    'recommendationId' => $recommendationId,
                    'reason' => self::ACTIVE_SYNC_FAILURE_REASON,
                ];
                Log::error(sprintf(
                    'Award recommendation approval restart failed for recommendation %d: %s',
                    $recommendationId,
                    $exception->getMessage(),
                ));
            }
        }
    }

    /**
     * Restart all active runs for one eligible recommendation atomically.
     *
     * @return array{status:string,cancelledRunCount?:int,newRunId?:int}
     */
    private function restartRecommendation(
        int $recommendationId,
        int $approvalProcessId,
        array $expectedRunIds,
        int $actorId,
    ): array {
        $runsTable = $this->fetchTable('Awards.RecommendationApprovalRuns');
        $connection = $runsTable->getConnection();
        $savePointsWereEnabled = $connection->isSavePointsEnabled();
        if (!$savePointsWereEnabled) {
            $connection->enableSavePoints();
        }

        try {
            return $connection->transactional(function () use (
                $runsTable,
                $recommendationId,
                $approvalProcessId,
                $expectedRunIds,
                $actorId,
            ): array {
                $runReferences = $runsTable->find()
                    ->select(['id', 'workflow_instance_id'])
                    ->where([
                        'RecommendationApprovalRuns.recommendation_id' => $recommendationId,
                        'RecommendationApprovalRuns.status IN' =>
                            RecommendationApprovalWorkflowLifecycleService::ACTIVE_STATUSES,
                        'RecommendationApprovalRuns.deleted IS' => null,
                    ])
                    ->orderBy(['RecommendationApprovalRuns.workflow_instance_id' => 'ASC'])
                    ->all()
                    ->toArray();
                if ($runReferences === []) {
                    return ['status' => 'skipped'];
                }

                $instanceIds = array_values(array_unique(array_map(
                    static fn(RecommendationApprovalRun $run): int => (int)$run->workflow_instance_id,
                    $runReferences,
                )));

                // Recommendation mutations acquire this owner row before touching workflow
                // projections. Keep restart synchronization on the same lock order so an
                // edit and a process sync cannot deadlock each other.
                $recommendation = $this->fetchTable('Awards.Recommendations')->find()
                    ->where(['Recommendations.id' => $recommendationId])
                    ->epilog('FOR UPDATE')
                    ->first();
                if (
                    !$recommendation instanceof Recommendation
                    || !$this->isEligibleForRestart($recommendation, $approvalProcessId)
                ) {
                    return ['status' => 'skipped'];
                }

                $lockedInstances = $this->fetchTable('WorkflowInstances')->find()
                    ->where(['WorkflowInstances.id IN' => $instanceIds])
                    ->orderBy(['WorkflowInstances.id' => 'ASC'])
                    ->epilog('FOR UPDATE')
                    ->all()
                    ->toArray();
                if (count($lockedInstances) !== count($instanceIds)) {
                    throw new RuntimeException('One or more active recommendation workflow instances were not found.');
                }
                foreach ($lockedInstances as $lockedInstance) {
                    if (
                        !$lockedInstance instanceof WorkflowInstance
                        || !in_array($lockedInstance->status, WorkflowInstance::ACTIVE_STATUSES, true)
                    ) {
                        return ['status' => 'skipped'];
                    }
                }

                $activeRuns = $runsTable->find()
                    ->where([
                        'RecommendationApprovalRuns.recommendation_id' => $recommendationId,
                        'RecommendationApprovalRuns.status IN' =>
                            RecommendationApprovalWorkflowLifecycleService::ACTIVE_STATUSES,
                        'RecommendationApprovalRuns.deleted IS' => null,
                    ])
                    ->orderBy(['RecommendationApprovalRuns.id' => 'ASC'])
                    ->epilog('FOR UPDATE')
                    ->all()
                    ->toArray();
                if ($activeRuns === []) {
                    return ['status' => 'skipped'];
                }

                $activeRunIds = array_map(
                    static fn(RecommendationApprovalRun $run): int => (int)$run->id,
                    $activeRuns,
                );
                sort($activeRunIds);
                sort($expectedRunIds);
                if ($activeRunIds !== $expectedRunIds) {
                    return ['status' => 'skipped'];
                }

                $oldRunIds = array_map(
                    static fn(RecommendationApprovalRun $run): int => (int)$run->id,
                    $activeRuns,
                );
                $cancelledRunIds = $this->lifecycleService->cancelActiveRunsForProcessRestart(
                    [$recommendationId],
                    $actorId,
                );
                sort($cancelledRunIds);
                sort($oldRunIds);
                if ($cancelledRunIds !== $oldRunIds) {
                    throw new RuntimeException('The active recommendation approval runs changed during restart.');
                }

                $results = $this->workflowEngine->dispatchTrigger(
                    RecommendationMigrationService::WORKFLOW_EVENT,
                    [
                        'recommendationId' => $recommendationId,
                        'actorId' => $actorId,
                        'restartReason' => RecommendationApprovalRun::TERMINAL_REASON_PROCESS_RESTARTED,
                        'cancelledRunIds' => $cancelledRunIds,
                    ],
                    $actorId,
                );
                if ($results === []) {
                    throw new RuntimeException('No current approval workflow accepted the restart request.');
                }
                foreach ($results as $result) {
                    if (!$result instanceof ServiceResult || !$result->isSuccess()) {
                        throw new RuntimeException(
                            $result instanceof ServiceResult
                                ? ($result->getError() ?? 'The replacement approval workflow failed to start.')
                                : 'The replacement approval workflow returned an invalid result.',
                        );
                    }
                }

                $replacementRuns = $runsTable->find()
                    ->where([
                        'RecommendationApprovalRuns.recommendation_id' => $recommendationId,
                        'RecommendationApprovalRuns.status IN' =>
                            RecommendationApprovalWorkflowLifecycleService::ACTIVE_STATUSES,
                        'RecommendationApprovalRuns.deleted IS' => null,
                    ])
                    ->orderBy(['RecommendationApprovalRuns.id' => 'ASC'])
                    ->all()
                    ->toArray();
                if (count($replacementRuns) !== 1) {
                    throw new RuntimeException('The restart did not create exactly one active approval run.');
                }

                $replacementRun = $replacementRuns[0];
                if (in_array((int)$replacementRun->id, $oldRunIds, true)) {
                    throw new RuntimeException('The restart reused a cancelled approval run.');
                }
                $replacementInstance = $this->fetchTable('WorkflowInstances')
                    ->get((int)$replacementRun->workflow_instance_id);
                if (
                    !$replacementInstance instanceof WorkflowInstance
                    || !in_array($replacementInstance->status, WorkflowInstance::ACTIVE_STATUSES, true)
                ) {
                    throw new RuntimeException('The replacement approval workflow is not active.');
                }
                if ((int)$replacementRun->approval_process_id !== $approvalProcessId) {
                    throw new RuntimeException('The replacement run did not use the selected approval process.');
                }

                return [
                    'status' => 'restarted',
                    'cancelledRunCount' => count($cancelledRunIds),
                    'newRunId' => (int)$replacementRun->id,
                ];
            });
        } finally {
            if (!$savePointsWereEnabled) {
                $connection->disableSavePoints();
            }
        }
    }

    /**
     * Terminal, grouped, deleted, and bestowal-owned recommendations are never restarted.
     */
    private function isEligibleForRestart(Recommendation $recommendation, int $approvalProcessId): bool
    {
        $awardProcessId = $this->fetchTable('Awards.Awards')
            ->find()
            ->select(['approval_process_id'])
            ->where(['id' => (int)$recommendation->award_id])
            ->first()?->approval_process_id;

        return $recommendation->deleted === null
            && (string)$recommendation->status !== 'Closed'
            && in_array((string)$recommendation->state, self::APPROVAL_STATES, true)
            && $recommendation->bestowal_id === null
            && $recommendation->recommendation_group_id === null
            && (int)$awardProcessId === $approvalProcessId;
    }
}
