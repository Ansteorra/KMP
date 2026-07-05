<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class BackfillBestowalTodoBranchScopes extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        if (
            !$this->hasTable('action_items')
            || !$this->hasTable('awards_bestowals')
            || !$this->hasTable('awards_awards')
        ) {
            return;
        }

        $this->execute(
            "UPDATE action_items
            SET branch_id = (
                SELECT awards_awards.branch_id
                FROM awards_bestowals
                INNER JOIN awards_awards ON awards_awards.id = awards_bestowals.award_id
                WHERE awards_bestowals.id = action_items.entity_id
                AND awards_bestowals.deleted IS NULL
                AND awards_awards.deleted IS NULL
            )
            WHERE entity_type = 'Awards.Bestowals'
            AND deleted IS NULL
            AND assignee_type <> 'member'
            AND EXISTS (
                SELECT 1
                FROM awards_bestowals
                INNER JOIN awards_awards ON awards_awards.id = awards_bestowals.award_id
                WHERE awards_bestowals.id = action_items.entity_id
                AND awards_bestowals.deleted IS NULL
                AND awards_awards.deleted IS NULL
                AND awards_awards.branch_id IS NOT NULL
            )",
        );
    }

    /**
     * @return void
     */
    public function down(): void
    {
        // No-op: branch scopes may have been corrected manually after this migration.
    }
}
