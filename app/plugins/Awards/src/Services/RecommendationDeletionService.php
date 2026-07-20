<?php
declare(strict_types=1);

namespace Awards\Services;

use Awards\Model\Entity\Recommendation;
use Awards\Model\Table\RecommendationsTable;
use Cake\Log\Log;
use Cake\ORM\Exception\PersistenceFailedException;
use Throwable;

/**
 * Deletes recommendations through the workflow-owned mutation layer.
 */
class RecommendationDeletionService
{
    private RecommendationGroupingService $groupingService;
    private RecommendationApprovalWorkflowLifecycleService $approvalLifecycleService;

    /**
     * @param \Awards\Services\RecommendationGroupingService|null $groupingService Optional grouping service.
     * @param \Awards\Services\RecommendationApprovalWorkflowLifecycleService|null $approvalLifecycleService Optional lifecycle service.
     */
    public function __construct(
        ?RecommendationGroupingService $groupingService = null,
        ?RecommendationApprovalWorkflowLifecycleService $approvalLifecycleService = null,
    ) {
        $this->groupingService = $groupingService ?? new RecommendationGroupingService();
        $this->approvalLifecycleService = $approvalLifecycleService
            ?? new RecommendationApprovalWorkflowLifecycleService();
    }

    /**
     * Soft-delete a recommendation and restore grouped children when deleting a head.
     *
     * @param \Awards\Model\Table\RecommendationsTable $recommendationsTable Recommendations table.
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation to delete.
     * @param int|null $actorId Current user ID.
     * @return array<string, mixed>
     */
    public function delete(
        RecommendationsTable $recommendationsTable,
        Recommendation $recommendation,
        ?int $actorId = null,
    ): array {
        try {
            $output = $recommendationsTable->getConnection()->transactional(
                function () use ($recommendationsTable, $recommendation, $actorId): array {
                    $recommendationIds = [(int)$recommendation->id];
                    if ($recommendation->recommendation_group_id === null) {
                        $childIds = $recommendationsTable->find()
                            ->select(['id'])
                            ->where(['recommendation_group_id' => (int)$recommendation->id])
                            ->all()
                            ->extract('id')
                            ->map(static fn($id): int => (int)$id)
                            ->toList();
                        $recommendationIds = array_values(array_unique(array_merge($recommendationIds, $childIds)));
                    }
                    $this->approvalLifecycleService->cancelActiveRunsForRecommendationDeletion(
                        $recommendationIds,
                        $actorId,
                    );

                    $restoredChildren = [];
                    if ($recommendation->recommendation_group_id === null) {
                        $restoredChildren = $this->groupingService->restoreChildrenForDeletedHead(
                            $recommendation,
                            $actorId,
                        );
                    }

                    if ($actorId !== null) {
                        $recommendation->modified_by = $actorId;
                    }

                    if (!$recommendationsTable->delete($recommendation)) {
                        throw new PersistenceFailedException(
                            $recommendation,
                            'Failed to delete recommendation.',
                        );
                    }

                    return [
                        'recommendationId' => (int)$recommendation->id,
                        'restoredChildIds' => array_map(
                            static fn(Recommendation $child): int => (int)$child->id,
                            $restoredChildren,
                        ),
                        'restoredChildCount' => count($restoredChildren),
                    ];
                },
            );
        } catch (PersistenceFailedException $exception) {
            Log::error('Error deleting recommendation: ' . $exception->getMessage());

            return [
                'success' => false,
                'recommendation' => $recommendation,
                'output' => null,
                'eventName' => null,
                'eventPayload' => null,
                'errorCode' => 'delete_failed',
                'message' => 'The recommendation could not be deleted.',
                'errors' => $recommendation->getErrors(),
            ];
        } catch (Throwable $exception) {
            Log::error('Error deleting recommendation: ' . $exception->getMessage());

            return [
                'success' => false,
                'recommendation' => $recommendation,
                'output' => null,
                'eventName' => null,
                'eventPayload' => null,
                'errorCode' => 'delete_failed',
                'message' => 'An error occurred while deleting the recommendation.',
                'errors' => $recommendation->getErrors(),
            ];
        }

        return [
            'success' => true,
            'recommendation' => $recommendation,
            'output' => $output,
            'eventName' => null,
            'eventPayload' => null,
            'errorCode' => null,
            'message' => null,
            'errors' => [],
        ];
    }
}
