<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable Squiz.Classes.ClassFileName.NoMatch

class ConsolidatePlatformQueueExecution extends AbstractMigration
{
    /**
     * Move queue execution out of minute schedules and into the fleet worker loop.
     */
    public function up(): void
    {
        $this->execute(
            "UPDATE platform_schedules
                SET enabled = FALSE,
                    modified_at = CURRENT_TIMESTAMP
              WHERE name IN ('platform-admin-job-runner', 'tenant-queue-drain')",
        );
    }

    /**
     * Restore the legacy queue schedules.
     */
    public function down(): void
    {
        $this->execute(
            "UPDATE platform_schedules
                SET enabled = TRUE,
                    modified_at = CURRENT_TIMESTAMP
              WHERE name IN ('platform-admin-job-runner', 'tenant-queue-drain')",
        );
    }
}
