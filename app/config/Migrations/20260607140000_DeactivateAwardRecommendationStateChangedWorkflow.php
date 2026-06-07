<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class DeactivateAwardRecommendationStateChangedWorkflow extends BaseMigration
{
    /**
     * Retire the legacy recommendation-state-driven bestowal handoff path.
     *
     * @return void
     */
    public function up(): void
    {
        $this->execute(
            "UPDATE workflow_definitions
            SET is_active = false,
                 modified_by = 1,
                 modified = '" . date('Y-m-d H:i:s') . "'
             WHERE slug = 'awards-recommendation-state-changed'",
        );
    }

    /**
     * Reactivate the legacy definition on rollback.
     *
     * @return void
     */
    public function down(): void
    {
        $this->execute(
            "UPDATE workflow_definitions
            SET is_active = true,
                 modified_by = 1,
                 modified = '" . date('Y-m-d H:i:s') . "'
             WHERE slug = 'awards-recommendation-state-changed'",
        );
    }
}
