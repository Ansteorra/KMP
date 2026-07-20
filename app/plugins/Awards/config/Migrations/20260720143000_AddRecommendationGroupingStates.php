<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Ensure recommendation grouping states exist in upgraded seed data.
 */
class AddRecommendationGroupingStates extends BaseMigration
{
    /**
     * Add linked recommendation states without duplicating existing values.
     *
     * @return void
     */
    public function up(): void
    {
        $this->execute(
            "UPDATE app_settings
             SET value = REPLACE(value, '- Queen Approved', '- Queen Approved\n- Linked')
             WHERE name = 'Awards.RecommendationStatuses'
               AND value NOT LIKE '%- Linked\n%'",
        );
        $this->execute(
            "UPDATE app_settings
             SET value = REPLACE(value, '- No Action', '- No Action\n- Linked - Closed')
             WHERE name = 'Awards.RecommendationStatuses'
               AND value NOT LIKE '%- Linked - Closed%'",
        );
    }

    /**
     * Remove recommendation grouping states added by this migration.
     *
     * @return void
     */
    public function down(): void
    {
        $this->execute(
            "UPDATE app_settings
             SET value = REPLACE(value, '\n- Linked - Closed', '')
             WHERE name = 'Awards.RecommendationStatuses'",
        );
        $this->execute(
            "UPDATE app_settings
             SET value = REPLACE(value, '\n- Linked', '')
             WHERE name = 'Awards.RecommendationStatuses'",
        );
    }
}
