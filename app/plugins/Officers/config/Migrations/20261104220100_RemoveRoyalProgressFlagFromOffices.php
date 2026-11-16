<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class RemoveRoyalProgressFlagFromOffices extends BaseMigration
{
    /** Offices no longer determine Royal Progress through attendance. */
    public function up(): void
    {
        $this->table('officers_offices')->removeColumn('is_royal_progress')->update();
    }

    /** Restore the legacy flag without restoring its former values. */
    public function down(): void
    {
        $this->table('officers_offices')
            ->addColumn('is_royal_progress', 'boolean', ['default' => false, 'null' => false])
            ->update();
    }
}
