<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/** Move existing court delegation into the shared activity assignment configuration. */
class UnifyCourtActivityAssignment extends BaseMigration
{
    /** @return void */
    public function up(): void
    {
        $this->execute(<<<'SQL'
            INSERT INTO gathering_activity_assignment_permissions (gathering_activity_id, permission_id, created)
            SELECT DISTINCT eligibility.gathering_activity_id, permissions.id, CURRENT_TIMESTAMP
            FROM award_gathering_activities eligibility CROSS JOIN permissions
            WHERE permissions.deleted IS NULL AND permissions.name IN (
                'Crown Court Management', 'Principality Court Management',
                'Baronial Court Management', 'Can Manage Court Schedule'
            ) AND NOT EXISTS (
                SELECT 1 FROM gathering_activity_assignment_permissions existing
                WHERE existing.gathering_activity_id = eligibility.gathering_activity_id
                    AND existing.permission_id = permissions.id
            )
            SQL);
        $this->execute(<<<'SQL'
            DELETE FROM permission_policies
            WHERE policy_class = 'App\Policy\GatheringPolicy'
                AND policy_method IN ('canAddCourtActivity', 'canCreateScheduledActivity', 'canEditScheduledActivity')
                AND permission_id IN (SELECT id FROM permissions WHERE name IN (
                    'Crown Court Management', 'Principality Court Management',
                    'Baronial Court Management', 'Can Manage Court Schedule'
                ))
            SQL);
    }

    /** @return void */
    public function down(): void
    {
        // Assignment rules may have been deliberately changed by an administrator; retain them on rollback.
        $this->execute(<<<'SQL'
            INSERT INTO permission_policies (permission_id, policy_class, policy_method)
            SELECT permissions.id, 'App\Policy\GatheringPolicy', methods.policy_method
            FROM permissions CROSS JOIN (VALUES
                ('canAddCourtActivity'), ('canCreateScheduledActivity'), ('canEditScheduledActivity')
            ) AS methods(policy_method)
            WHERE permissions.deleted IS NULL AND permissions.name IN (
                'Crown Court Management', 'Principality Court Management',
                'Baronial Court Management', 'Can Manage Court Schedule'
            ) AND NOT EXISTS (
                SELECT 1 FROM permission_policies existing
                WHERE existing.permission_id = permissions.id
                    AND existing.policy_class = 'App\Policy\GatheringPolicy'
                    AND existing.policy_method = methods.policy_method
            )
            SQL);
    }
}
