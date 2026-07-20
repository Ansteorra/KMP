<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Removes the Authorization Renewal workflow definition (0 instances)
 * and activates the Authorization Request workflow.
 *
 * The renewal workflow is redundant — the main request workflow already
 * handles renewals via the isRenewal trigger data flag.
 */
class RemoveAuthRenewalWorkflowAndActivateRequest extends BaseMigration
{
    public function up(): void
    {
        // Delete renewal workflow versions first (FK constraint)
        $this->execute(
            "DELETE FROM workflow_versions WHERE workflow_definition_id IN (
                SELECT id FROM workflow_definitions WHERE slug = 'activities-authorization-renewal'
            )"
        );

        // Delete the renewal workflow definition
        $this->execute(
            "DELETE FROM workflow_definitions WHERE slug = 'activities-authorization-renewal'"
        );

        // Activate the authorization request workflow
        $this->execute(
            "UPDATE workflow_definitions SET is_active = TRUE WHERE slug = 'activities-authorization-request'"
        );
    }

    public function down(): void
    {
        // Deactivate the authorization request workflow
        $this->execute(
            "UPDATE workflow_definitions SET is_active = FALSE WHERE slug = 'activities-authorization-request'"
        );

        // Re-creating the renewal workflow requires re-running the seed
    }
}
