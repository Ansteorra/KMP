<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class AddRoyalProgressToGatheringAttendances extends BaseMigration
{
    /**
     * Model royal progress as RSVP metadata (issue #61) with a snapshot of the
     * office held at the time of the RSVP so the progress record keeps its
     * meaning after the office holder changes (issue #62).
     *
     * progress_office_id intentionally has no foreign key: it references the
     * Officers plugin's officers_offices table and is only a pointer for
     * pre-selection in the UI. The snapshot columns are the source of truth.
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('gathering_attendances')
            ->addColumn('is_royal_progress', 'boolean', [
                'default' => false,
                'limit' => null,
                'null' => false,
                'comment' => 'RSVP represents royal progress for a progress-eligible office',
            ])
            ->addColumn('progress_office_id', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => true,
                'comment' => 'officers_offices.id the progress RSVP was made for (reference only)',
            ])
            ->addColumn('progress_office_name', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
                'comment' => 'Snapshot of the office name at RSVP time',
            ])
            ->addColumn('progress_branch_name', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
                'comment' => 'Snapshot of the branch the office was held for at RSVP time',
            ]);

        $table->addIndex(
            ['gathering_id', 'is_royal_progress'],
            ['name' => 'idx_gathering_attendances_progress'],
        );

        $table->update();
    }
}
