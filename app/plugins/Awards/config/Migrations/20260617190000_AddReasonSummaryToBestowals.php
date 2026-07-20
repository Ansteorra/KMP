<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Add a persisted recommendation reason summary to bestowals.
 */
class AddReasonSummaryToBestowals extends BaseMigration
{
    /**
     * @return void
     */
    public function change(): void
    {
        $this->table('awards_bestowals')
            ->addColumn('reason_summary', 'text', [
                'after' => 'herald_notes',
                'null' => true,
            ])
            ->update();
    }
}
