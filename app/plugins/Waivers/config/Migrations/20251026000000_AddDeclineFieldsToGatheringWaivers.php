<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Add decline tracking fields to gathering_waivers table
 * 
 * Adds fields to track when a waiver is declined/rejected:
 * - declined_at: Timestamp when waiver was declined
 * - declined_by: Member who declined the waiver
 * - decline_reason: Reason for declining the waiver
 * 
 * This allows authorized users to decline invalid waivers within 30 days of upload.
 */
class AddDeclineFieldsToGatheringWaivers extends BaseMigration
{
    /**
     * Change Method.
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('waivers_gathering_waivers');

        // Add decline tracking fields
        $table->addColumn('declined_at', 'datetime', [
            'default' => null,
            'null' => true,
            'comment' => 'Timestamp when waiver was declined/rejected',
            'after' => 'notes'
        ]);

        $table->addColumn('declined_by', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => true,
            'comment' => 'Member ID who declined the waiver',
            'after' => 'declined_at'
        ]);

        $table->addColumn('decline_reason', 'text', [
            'default' => null,
            'null' => true,
            'comment' => 'Reason for declining the waiver',
            'after' => 'declined_by'
        ]);

        // Add index on declined_at for performance
        $table->addIndex(['declined_at'], [
            'name' => 'idx_gathering_waivers_declined_at'
        ]);

        // Add foreign key for declined_by
        $table->addForeignKey('declined_by', 'members', 'id', [
            'delete' => 'NO_ACTION',
            'update' => 'NO_ACTION',
            'constraint' => 'fk_gathering_waivers_declined_by'
        ]);

        $table->update();
    }
}