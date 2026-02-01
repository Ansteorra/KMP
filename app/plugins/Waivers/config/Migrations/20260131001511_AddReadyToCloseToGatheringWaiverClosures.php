<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Add ready_to_close fields to gathering_waiver_closures table
 * 
 * Allows gathering editors/stewards to mark a gathering as ready for
 * the waiver secretary to review and close.
 */
class AddReadyToCloseToGatheringWaiverClosures extends BaseMigration
{
    /**
     * Change Method.
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('waivers_gathering_waiver_closures');

        // Make closed_at and closed_by nullable (not closed yet, just ready)
        $table->changeColumn('closed_at', 'datetime', [
            'default' => null,
            'null' => true,
            'comment' => 'Timestamp when waiver collection was closed by secretary',
        ]);
        $table->changeColumn('closed_by', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => true,
            'comment' => 'Member who closed waiver collection (waiver secretary)',
        ]);

        // Add ready_to_close fields
        $table->addColumn('ready_to_close_at', 'datetime', [
            'default' => null,
            'null' => true,
            'after' => 'closed_by',
            'comment' => 'Timestamp when gathering was marked ready to close',
        ]);
        $table->addColumn('ready_to_close_by', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => true,
            'after' => 'ready_to_close_at',
            'comment' => 'Member who marked gathering ready to close',
        ]);

        $table->addIndex(['ready_to_close_at'], [
            'name' => 'idx_gathering_waiver_closures_ready_at',
        ]);
        $table->addIndex(['ready_to_close_by'], [
            'name' => 'idx_gathering_waiver_closures_ready_by',
        ]);

        $table->addForeignKey('ready_to_close_by', 'members', 'id', [
            'delete' => 'SET_NULL',
            'update' => 'NO_ACTION',
            'constraint' => 'fk_gathering_waiver_closures_ready_by',
        ]);

        $table->update();
    }
}
