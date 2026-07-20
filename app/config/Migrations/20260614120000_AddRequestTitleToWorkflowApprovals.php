<?php

declare(strict_types=1);

use App\Migrations\CrossEngineMigrationTrait;
use Migrations\AbstractMigration;

class AddRequestTitleToWorkflowApprovals extends AbstractMigration
{
    use CrossEngineMigrationTrait;

    private const MAX_TITLE_LENGTH = 255;

    /**
     * Add cached approval request titles and backfill existing approvals.
     *
     * @return void
     */
    public function up(): void
    {
        $table = $this->table('workflow_approvals');
        $table->addColumn('request_title', 'string', [
            'limit' => self::MAX_TITLE_LENGTH,
            'null' => true,
            'default' => null,
            'after' => 'current_approver_id',
            'comment' => 'Cached approval request title for grid search',
        ]);
        $table->update();

        $this->backfillWarrantRosterTitles();
        $this->backfillAwardRecommendationTitles();
        $this->backfillFallbackTitles();
    }

    /**
     * Remove cached approval request titles.
     *
     * @return void
     */
    public function down(): void
    {
        $table = $this->table('workflow_approvals');
        $table->removeColumn('request_title');
        $table->update();
    }

    /**
     * Backfill titles for warrant roster approval workflow instances.
     *
     * @return void
     */
    private function backfillWarrantRosterTitles(): void
    {
        if (!$this->hasTable('warrant_rosters')) {
            return;
        }

        $rows = $this->fetchAll(
            "SELECT wa.id, wr.name
             FROM workflow_approvals wa
             INNER JOIN workflow_instances wi ON wi.id = wa.workflow_instance_id
             INNER JOIN warrant_rosters wr ON wr.id = wi.entity_id
             WHERE wi.entity_type = 'WarrantRosters'
               AND wa.request_title IS NULL",
        );

        foreach ($rows as $row) {
            $name = trim((string)($row['name'] ?? ''));
            $this->updateTitle(
                (int)$row['id'],
                sprintf('Warrant Roster: %s', $name !== '' ? $name : 'Unknown Roster'),
            );
        }
    }

    /**
     * Backfill titles for award recommendation approval workflow instances.
     *
     * @return void
     */
    private function backfillAwardRecommendationTitles(): void
    {
        if (!$this->hasTable('awards_recommendations')) {
            return;
        }

        $this->backfillAwardRecommendationEntityTitles();

        if ($this->hasTable('awards_recommendation_approval_runs')) {
            $this->backfillAwardRecommendationRunTitles();
        }
    }

    /**
     * Backfill titles for workflow instances that directly reference recommendations.
     *
     * @return void
     */
    private function backfillAwardRecommendationEntityTitles(): void
    {
        $rows = $this->fetchAll(
            "SELECT wa.id, ar.member_sca_name
             FROM workflow_approvals wa
             INNER JOIN workflow_instances wi ON wi.id = wa.workflow_instance_id
             INNER JOIN awards_recommendations ar ON ar.id = wi.entity_id
             WHERE wi.entity_type = 'Awards.Recommendations'
               AND wa.request_title IS NULL",
        );

        $this->updateAwardRecommendationRows($rows);
    }

    /**
     * Backfill titles for workflow instances tracked by recommendation approval runs.
     *
     * @return void
     */
    private function backfillAwardRecommendationRunTitles(): void
    {
        $rows = $this->fetchAll(
            "SELECT wa.id, ar.member_sca_name
             FROM workflow_approvals wa
             INNER JOIN workflow_instances wi ON wi.id = wa.workflow_instance_id
             INNER JOIN awards_recommendation_approval_runs runs ON runs.workflow_instance_id = wi.id
             INNER JOIN awards_recommendations ar ON ar.id = runs.recommendation_id
             WHERE wa.request_title IS NULL
               AND runs.deleted IS NULL",
        );

        $this->updateAwardRecommendationRows($rows);
    }

    /**
     * @param array<int, array<string, mixed>> $rows Award recommendation rows.
     */
    private function updateAwardRecommendationRows(array $rows): void
    {
        foreach ($rows as $row) {
            $memberName = trim((string)($row['member_sca_name'] ?? ''));
            $this->updateTitle(
                (int)$row['id'],
                sprintf('Award Recommendation: %s', $memberName !== '' ? $memberName : 'Unknown Recipient'),
            );
        }
    }

    /**
     * Backfill generic titles for approvals without a specific renderer-backed title.
     *
     * @return void
     */
    private function backfillFallbackTitles(): void
    {
        $rows = $this->fetchAll(
            "SELECT wa.id, wi.entity_type
             FROM workflow_approvals wa
             INNER JOIN workflow_instances wi ON wi.id = wa.workflow_instance_id
             WHERE wa.request_title IS NULL",
        );

        foreach ($rows as $row) {
            $entityType = trim((string)($row['entity_type'] ?? 'Unknown'));
            $this->updateTitle(
                (int)$row['id'],
                sprintf('Approval Required: %s', $entityType !== '' ? $entityType : 'Unknown'),
            );
        }
    }

    /**
     * Persist a truncated title for a workflow approval.
     *
     * @param int $approvalId Workflow approval ID.
     * @param string $title Request title.
     * @return void
     */
    private function updateTitle(int $approvalId, string $title): void
    {
        $title = mb_substr($title, 0, self::MAX_TITLE_LENGTH);
        $title = $this->sqlEscape($title);

        $this->execute(
            "UPDATE workflow_approvals
             SET request_title = '{$title}'
             WHERE id = {$approvalId}",
        );
    }
}
