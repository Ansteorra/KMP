<?php
declare(strict_types=1);

use Awards\Services\RecommendationBestowalStatePolicyService;
use Awards\Services\RecommendationGroupingService;
use Awards\Services\RecommendationStateLogService;
use Cake\ORM\TableRegistry;
use Migrations\BaseMigration;

/**
 * Close recommendations whose latest assigned approval workflow was rejected.
 */
class BackfillRejectedRecommendationStates extends BaseMigration
{
    private const TARGET_STATE = RecommendationBestowalStatePolicyService::NO_ACTION_STATE;

    private const TARGET_STATUS = 'Closed';

    /**
     * @return void
     */
    public function up(): void
    {
        $recommendationsTable = TableRegistry::getTableLocator()->get('Awards.Recommendations');
        $stateLogService = new RecommendationStateLogService();
        $groupingService = new RecommendationGroupingService(
            $recommendationsTable,
            $stateLogService,
        );

        foreach ($this->rejectedRecommendations() as $row) {
            $recommendationId = (int)$row['recommendation_id'];
            $actorId = $row['actor_id'] !== null ? (int)$row['actor_id'] : null;

            $recommendationsTable->getConnection()->transactional(
                function () use (
                    $recommendationsTable,
                    $stateLogService,
                    $groupingService,
                    $recommendationId,
                    $actorId,
                ): void {
                    $recommendation = $recommendationsTable->get($recommendationId);
                    if (
                        (
                            (string)$recommendation->state === self::TARGET_STATE
                            && (string)$recommendation->status === self::TARGET_STATUS
                        )
                        || $recommendation->bestowal_id !== null
                        || $recommendation->recommendation_group_id !== null
                    ) {
                        return;
                    }

                    $fromState = (string)$recommendation->state;
                    $fromStatus = (string)$recommendation->status;
                    $recommendation->state = self::TARGET_STATE;
                    $recommendation->status = self::TARGET_STATUS;
                    $recommendation->modified_by = $actorId;
                    $recommendationsTable->saveOrFail($recommendation, ['systemSync' => true]);

                    $stateLogService->logStateTransition(
                        $recommendationId,
                        $fromState,
                        (string)$recommendation->state,
                        $fromStatus,
                        (string)$recommendation->status,
                        $actorId,
                    );
                    $groupingService->syncLinkedChildrenState($recommendation, $actorId);
                },
            );
        }
    }

    /**
     * This corrects historical state and audit data and is intentionally irreversible.
     *
     * @return void
     */
    public function down(): void
    {
    }

    /**
     * Select only recommendations whose latest approval run is rejected.
     *
     * The response lookup supplies the rejecting member for the state audit when available.
     * Explicit run provenance is preferred, while the rejected approval status covers runs
     * created before terminal_reason was consistently populated.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rejectedRecommendations(): array
    {
        return $this->fetchAll(
            "SELECT recommendations.id AS recommendation_id,
                    COALESCE(
                        (
                            SELECT rejected_responses.member_id
                            FROM workflow_approvals actor_approvals
                            INNER JOIN workflow_approval_responses rejected_responses
                                ON rejected_responses.workflow_approval_id = actor_approvals.id
                            WHERE actor_approvals.workflow_instance_id = rejected_runs.workflow_instance_id
                              AND rejected_responses.decision = 'reject'
                            ORDER BY rejected_responses.responded_at DESC, rejected_responses.id DESC
                            LIMIT 1
                        ),
                        rejected_runs.modified_by,
                        rejected_runs.created_by,
                        recommendations.modified_by,
                        recommendations.created_by
                    ) AS actor_id
             FROM awards_recommendations recommendations
             INNER JOIN awards_recommendation_approval_runs rejected_runs
                ON rejected_runs.recommendation_id = recommendations.id
             WHERE recommendations.deleted IS NULL
               AND recommendations.bestowal_id IS NULL
               AND recommendations.recommendation_group_id IS NULL
               AND (recommendations.state <> 'No Action' OR recommendations.status <> 'Closed')
               AND rejected_runs.deleted IS NULL
               AND NOT EXISTS (
                    SELECT 1
                    FROM awards_recommendation_approval_runs newer_runs
                    WHERE newer_runs.recommendation_id = rejected_runs.recommendation_id
                      AND newer_runs.deleted IS NULL
                      AND newer_runs.id > rejected_runs.id
               )
               AND (
                    (
                        rejected_runs.status = 'closed'
                        AND rejected_runs.terminal_reason = 'rejected'
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM workflow_approvals rejected_approvals
                        WHERE rejected_approvals.workflow_instance_id = rejected_runs.workflow_instance_id
                          AND rejected_approvals.status = 'rejected'
                    )
               )
             ORDER BY recommendations.id",
        );
    }
}
