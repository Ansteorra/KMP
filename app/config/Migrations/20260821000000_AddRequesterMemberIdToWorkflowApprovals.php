<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Cache the approval requester's member ID for grid filtering.
 */
class AddRequesterMemberIdToWorkflowApprovals extends BaseMigration
{
    private const INDEX_NAME = 'idx_workflow_approvals_requester_member';
    private const CONSTRAINT_NAME = 'fk_workflow_approvals_requester_member';

    /**
     * Add the requester snapshot and backfill renderer-backed workflow domains.
     */
    public function up(): void
    {
        $this->table('workflow_approvals')
            ->addColumn('requester_member_id', 'integer', [
                'default' => null,
                'null' => true,
                'after' => 'request_title',
                'comment' => 'Requester member snapshot for approval grid filtering',
            ])
            ->addIndex(['requester_member_id'], [
                'name' => self::INDEX_NAME,
            ])
            ->update();

        $this->backfillActivitiesAuthorizationRequesters();
        $this->backfillAwardRecommendationRequesters();
        $this->backfillAwardRecommendationRunRequesters();
        $this->backfillAwardFeedbackRequesters();
        $this->backfillWarrantRosterRequesters();

        $this->table('workflow_approvals')
            ->addForeignKey('requester_member_id', 'members', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'CASCADE',
                'constraint' => self::CONSTRAINT_NAME,
            ])
            ->update();
    }

    /**
     * Remove the requester snapshot.
     */
    public function down(): void
    {
        $table = $this->table('workflow_approvals');
        $table->dropForeignKey('requester_member_id')->update();
        $table
            ->removeIndexByName(self::INDEX_NAME)
            ->removeColumn('requester_member_id')
            ->update();
    }

    /**
     * Backfill Activity authorization requesters from trigger snapshots first,
     * then from the authorization row for older workflow contexts.
     */
    private function backfillActivitiesAuthorizationRequesters(): void
    {
        if (!$this->hasTable('activities_authorizations')) {
            return;
        }

        $contextRows = $this->fetchAll(
            "SELECT wa.id, wi.context
             FROM workflow_approvals wa
             INNER JOIN workflow_instances wi ON wi.id = wa.workflow_instance_id
             WHERE wi.entity_type IN ('Activities', 'Activities.Authorizations')
               AND wa.requester_member_id IS NULL",
        );
        foreach ($contextRows as $row) {
            $context = $this->decodeContext($row['context'] ?? null);
            $triggerData = $context['trigger'] ?? $context['event'] ?? $context;
            if (!is_array($triggerData)) {
                continue;
            }
            $requesterMemberId = $this->positiveIntOrNull($triggerData['memberId'] ?? null);
            if ($requesterMemberId !== null) {
                $this->updateRequesterMemberId((int)$row['id'], $requesterMemberId);
            }
        }

        $rows = $this->fetchAll(
            "SELECT wa.id, authorizations.member_id AS requester_member_id
             FROM workflow_approvals wa
             INNER JOIN workflow_instances wi ON wi.id = wa.workflow_instance_id
             INNER JOIN activities_authorizations authorizations ON authorizations.id = wi.entity_id
             INNER JOIN members requesters ON requesters.id = authorizations.member_id
             WHERE wi.entity_type IN ('Activities', 'Activities.Authorizations')
               AND wa.requester_member_id IS NULL",
        );
        $this->updateRequesterRows($rows);
    }

    /**
     * Backfill workflow instances that directly reference award recommendations.
     */
    private function backfillAwardRecommendationRequesters(): void
    {
        if (!$this->hasTable('awards_recommendations')) {
            return;
        }

        $rows = $this->fetchAll(
            "SELECT wa.id, recommendations.requester_id AS requester_member_id
             FROM workflow_approvals wa
             INNER JOIN workflow_instances wi ON wi.id = wa.workflow_instance_id
             INNER JOIN awards_recommendations recommendations ON recommendations.id = wi.entity_id
             INNER JOIN members requesters ON requesters.id = recommendations.requester_id
             WHERE wi.entity_type = 'Awards.Recommendations'
               AND wa.requester_member_id IS NULL",
        );
        $this->updateRequesterRows($rows);
    }

    /**
     * Backfill recommendation workflows owned by their latest active approval run.
     */
    private function backfillAwardRecommendationRunRequesters(): void
    {
        if (
            !$this->hasTable('awards_recommendations')
            || !$this->hasTable('awards_recommendation_approval_runs')
        ) {
            return;
        }

        $rows = $this->fetchAll(
            "SELECT wa.id, recommendations.requester_id AS requester_member_id
             FROM workflow_approvals wa
             INNER JOIN workflow_instances wi ON wi.id = wa.workflow_instance_id
             INNER JOIN awards_recommendation_approval_runs runs ON runs.workflow_instance_id = wi.id
             INNER JOIN awards_recommendations recommendations ON recommendations.id = runs.recommendation_id
             INNER JOIN members requesters ON requesters.id = recommendations.requester_id
             WHERE wa.requester_member_id IS NULL
               AND runs.deleted IS NULL
               AND NOT EXISTS (
                   SELECT 1
                   FROM awards_recommendation_approval_runs newer_runs
                   WHERE newer_runs.workflow_instance_id = runs.workflow_instance_id
                     AND newer_runs.deleted IS NULL
                     AND newer_runs.id > runs.id
               )",
        );
        $this->updateRequesterRows($rows);
    }

    /**
     * Backfill recommendation feedback requesters.
     */
    private function backfillAwardFeedbackRequesters(): void
    {
        if (!$this->hasTable('awards_recommendation_feedback_requests')) {
            return;
        }

        $rows = $this->fetchAll(
            "SELECT wa.id, feedback.requester_id AS requester_member_id
             FROM workflow_approvals wa
             INNER JOIN workflow_instances wi ON wi.id = wa.workflow_instance_id
             INNER JOIN awards_recommendation_feedback_requests feedback ON feedback.id = wi.entity_id
             INNER JOIN members requesters ON requesters.id = feedback.requester_id
             WHERE wi.entity_type = 'Awards.RecommendationFeedbackRequests'
               AND wa.requester_member_id IS NULL",
        );
        $this->updateRequesterRows($rows);
    }

    /**
     * Backfill warrant roster creators.
     */
    private function backfillWarrantRosterRequesters(): void
    {
        if (!$this->hasTable('warrant_rosters')) {
            return;
        }

        $rows = $this->fetchAll(
            "SELECT wa.id, rosters.created_by AS requester_member_id
             FROM workflow_approvals wa
             INNER JOIN workflow_instances wi ON wi.id = wa.workflow_instance_id
             INNER JOIN warrant_rosters rosters ON rosters.id = wi.entity_id
             INNER JOIN members requesters ON requesters.id = rosters.created_by
             WHERE wi.entity_type = 'WarrantRosters'
               AND wa.requester_member_id IS NULL",
        );
        $this->updateRequesterRows($rows);
    }

    /**
     * @param array<int, array<string, mixed>> $rows Approval/requester rows.
     */
    private function updateRequesterRows(array $rows): void
    {
        foreach ($rows as $row) {
            $requesterMemberId = $this->positiveIntOrNull($row['requester_member_id'] ?? null);
            if ($requesterMemberId === null) {
                continue;
            }

            $this->updateRequesterMemberId((int)$row['id'], $requesterMemberId);
        }
    }

    /**
     * Persist a requester only when the referenced member still exists.
     */
    private function updateRequesterMemberId(int $approvalId, int $requesterMemberId): void
    {
        $this->execute(sprintf(
            'UPDATE workflow_approvals '
            . 'SET requester_member_id = %d '
            . 'WHERE id = %d '
            . 'AND requester_member_id IS NULL '
            . 'AND EXISTS (SELECT 1 FROM members WHERE members.id = %d)',
            $requesterMemberId,
            $approvalId,
            $requesterMemberId,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeContext(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Normalize a positive integer identifier.
     */
    private function positiveIntOrNull(mixed $value): ?int
    {
        if (is_string($value)) {
            $value = trim($value);
        }
        if (!(is_int($value) || (is_string($value) && ctype_digit($value)))) {
            return null;
        }

        $value = (int)$value;

        return $value > 0 ? $value : null;
    }
}
