<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class AddIsCircleToGatheringActivities extends BaseMigration
{
    /**
     * Flag activities that represent an order circle (Laurel, Pelican, etc.).
     * The public kingdom calendar shows a circle icon and highlighted chip for
     * these, and it can be filtered on explicitly instead of guessing from the
     * activity name.
     *
     * @return void
     */
    public function up(): void
    {
        $this->table('gathering_activities')
            ->addColumn('is_circle', 'boolean', [
                'default' => false,
                'null' => false,
                'comment' => 'Activity is an order circle (Laurel, Pelican, etc.)',
            ])
            ->update();

        // Preserve the previous name-based heuristic for existing rows.
        $this->execute(
            "UPDATE gathering_activities SET is_circle = TRUE WHERE name ILIKE '%circle%'",
        );
    }

    public function down(): void
    {
        $this->table('gathering_activities')
            ->removeColumn('is_circle')
            ->update();
    }
}
