<?php
declare(strict_types=1);

namespace Awards\Services;

use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Table;
use RuntimeException;
use Throwable;

/**
 * Workflow-aware bestowal handoff service.
 *
 * Validates recommendation eligibility for bestowal creation:
 * - Blocks creation when a recommendation is under active approval review.
 * - Blocks direct handoff of grouped child recommendations.
 * - Records the source approval run ID for provenance.
 *
 * Routes all eligibility checks before delegating to BestowalCreationService.
 */
class BestowalHandoffService
{
    use BestowalNotesSupportTrait;
    use LocatorAwareTrait;

    private Table $recommendationsTable;
    private Table $approvalRunsTable;
    private Table $bestowalsTable;
    private BestowalCreationService $creationService;
    private RecommendationApprovalWorkflowLifecycleService $approvalLifecycleService;

    /**
     * @param \Cake\ORM\Table|null $recommendationsTable Optional injected recommendations table.
     * @param \Cake\ORM\Table|null $approvalRunsTable Optional injected approval runs table.
     * @param \Cake\ORM\Table|null $bestowalsTable Optional injected bestowals table.
     * @param \Awards\Services\BestowalCreationService|null $creationService Optional injected creation service.
     * @param \Awards\Services\RecommendationApprovalWorkflowLifecycleService|null $approvalLifecycleService Optional lifecycle service.
     */
    public function __construct(
        ?Table $recommendationsTable = null,
        ?Table $approvalRunsTable = null,
        ?Table $bestowalsTable = null,
        ?BestowalCreationService $creationService = null,
        ?RecommendationApprovalWorkflowLifecycleService $approvalLifecycleService = null,
    ) {
        $this->recommendationsTable = $recommendationsTable ?? $this->fetchTable('Awards.Recommendations');
        $this->approvalRunsTable = $approvalRunsTable ?? $this->fetchTable('Awards.RecommendationApprovalRuns');
        $this->bestowalsTable = $bestowalsTable ?? $this->fetchTable('Awards.Bestowals');
        $this->creationService = $creationService ?? new BestowalCreationService();
        $this->approvalLifecycleService = $approvalLifecycleService
            ?? new RecommendationApprovalWorkflowLifecycleService(
                recommendationsTable: $this->recommendationsTable,
                approvalRunsTable: $this->approvalRunsTable,
            );
    }

    /**
     * Create a bestowal from a single recommendation, validating approval eligibility.
     *
     * Returns a standardized result array:
     * - `success` (bool)
     * - `skipped` (bool) when the recommendation is a group child or already linked
     * - `error` (string) on failure
     * - `data` (array) on success: bestowalId, recommendationIds, sourceApprovalRunId
     *
     * @param int $recommendationId Recommendation ID.
     * @param int $actorId Current user ID.
     * @param int|null $gatheringId Optional gathering override selected during approval.
     * @return array<string, mixed>
     */
    public function createBestowal(int $recommendationId, int $actorId, ?int $gatheringId = null): array
    {
        if ($recommendationId <= 0) {
            return $this->failureResult('Recommendation ID must be greater than zero.');
        }

        try {
            $this->assertHandoffEligible($recommendationId);
        } catch (RuntimeException $e) {
            return $this->failureResult($e->getMessage());
        }

        $result = $this->creationService->createFromRecommendation($recommendationId, $actorId, $gatheringId);

        if (($result['success'] ?? false) && !($result['skipped'] ?? false)) {
            $bestowalId = (int)($result['data']['bestowalId'] ?? 0);
            if ($bestowalId > 0) {
                $sourceRunId = $this->approvalLifecycleService->findLatestApprovedRunId($recommendationId);
                if ($sourceRunId !== null) {
                    $this->recordSourceApprovalRun($bestowalId, $sourceRunId, $actorId);
                    $this->approvalLifecycleService->consumeLatestApprovedRunForBestowal(
                        $recommendationId,
                        $bestowalId,
                        $actorId,
                    );
                    $result['data']['sourceApprovalRunId'] = $sourceRunId;
                }
                $this->refreshReasonSummary($bestowalId, $actorId);
            }
        }

        return $result;
    }

    /**
     * Create bestowals for multiple recommendations, validating each one's eligibility.
     *
     * @param array<int> $recommendationIds Recommendation IDs.
     * @param int $actorId Current user ID.
     * @param int|null $gatheringId Optional gathering override selected during approval.
     * @return array<string, mixed>
     */
    public function createBestowals(array $recommendationIds, int $actorId, ?int $gatheringId = null): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $recommendationIds))));
        if ($ids === []) {
            return [
                'success' => false,
                'error' => 'At least one recommendation ID is required.',
                'data' => ['processedCount' => 0, 'results' => []],
            ];
        }

        $results = [];
        $bestowalIds = [];
        $errors = [];

        foreach ($ids as $recommendationId) {
            $result = $this->createBestowal($recommendationId, $actorId, $gatheringId);
            $results[] = $result;

            if (!($result['success'] ?? false)) {
                $errors[] = (string)($result['error'] ?? 'Bestowal handoff failed');
                continue;
            }

            if (($result['skipped'] ?? false) || !isset($result['data']['bestowalId'])) {
                continue;
            }

            $bestowalIds[] = (int)$result['data']['bestowalId'];
        }

        if ($errors !== []) {
            return [
                'success' => false,
                'error' => implode('; ', $errors),
                'data' => [
                    'processedCount' => count($ids),
                    'bestowalIds' => $bestowalIds,
                    'results' => $results,
                ],
            ];
        }

        return [
            'success' => true,
            'data' => [
                'processedCount' => count($ids),
                'bestowalIds' => $bestowalIds,
                'results' => $results,
            ],
        ];
    }

    /**
     * Assert that a recommendation is eligible for bestowal handoff.
     *
     * @param int $recommendationId Recommendation ID.
     * @throws \RuntimeException when the recommendation is under active approval review.
     */
    public function assertHandoffEligible(int $recommendationId): void
    {
        if ($this->approvalLifecycleService->hasActiveRun($recommendationId)) {
            throw new RuntimeException(
                "Recommendation #{$recommendationId} is currently under active approval review. "
                . 'Complete or cancel the approval before creating a bestowal.',
            );
        }
    }

    /**
     * Check whether a recommendation has any active approval run (in_progress or changes_requested).
     *
     * @param int $recommendationId Recommendation ID.
     * @return bool
     */
    public function hasActiveApprovalRun(int $recommendationId): bool
    {
        return $this->approvalLifecycleService->hasActiveRun($recommendationId);
    }

    /**
     * Find the most recently completed (approved) approval run ID for a recommendation.
     *
     * @param int $recommendationId Recommendation ID.
     * @return int|null
     */
    public function findLatestApprovedRunId(int $recommendationId): ?int
    {
        return $this->approvalLifecycleService->findLatestApprovedRunId($recommendationId);
    }

    /**
     * @param int $bestowalId Bestowal ID.
     * @param int $sourceApprovalRunId Source approval run ID.
     * @param int $actorId Actor ID.
     */
    private function recordSourceApprovalRun(int $bestowalId, int $sourceApprovalRunId, int $actorId): void
    {
        try {
            $bestowal = $this->bestowalsTable->get($bestowalId);
            $bestowal->set('source_approval_run_id', $sourceApprovalRunId, ['guard' => false]);
            $bestowal->setDirty('source_approval_run_id', true);
            $bestowal->modified_by = $actorId;
            $this->bestowalsTable->saveOrFail($bestowal);
        } catch (Throwable $e) {
            Log::warning("Failed to record source_approval_run_id on bestowal #{$bestowalId}: {$e->getMessage()}");
        }
    }

    /**
     * Ensure approval-created handoffs persist linked recommendation planning details.
     *
     * @param int $bestowalId Bestowal ID.
     * @param int $actorId Actor ID.
     * @return void
     */
    private function refreshReasonSummary(int $bestowalId, int $actorId): void
    {
        $bestowal = $this->bestowalsTable->get($bestowalId, contain: [
            'Recommendations' => ['Requesters'],
        ]);
        $bestowal->specialty = $this->buildSpecialtySummary($bestowal->recommendations ?? []);
        $bestowal->reason_summary = $this->buildReasonSummary($bestowal->recommendations ?? []);
        $bestowal->modified_by = $actorId;
        $this->bestowalsTable->saveOrFail($bestowal);
    }

    /**
     * @param string $message Error message.
     * @return array<string, mixed>
     */
    private function failureResult(string $message): array
    {
        return ['success' => false, 'error' => $message, 'data' => ['errors' => [$message]]];
    }
}
