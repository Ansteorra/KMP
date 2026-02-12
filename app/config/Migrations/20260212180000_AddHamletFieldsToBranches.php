<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Compatibility stub for environments that already recorded this version.
 *
 * Actual hamlet field creation was retimed to an earlier migration ID:
 * 20260115000000_AddHamletFieldsToBranchesEarly.
 */
class AddHamletFieldsToBranches extends AbstractMigration
{
    public function up(): void
    {
        // Intentionally no-op.
    }

    public function down(): void
    {
        // Intentionally no-op.
    }
}

