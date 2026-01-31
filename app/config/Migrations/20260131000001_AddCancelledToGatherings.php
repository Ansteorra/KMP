<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Add cancelled_at field to gatherings table
 * 
 * This allows gatherings to be marked as cancelled while preserving
 * the record and any associated data (waivers, attendances, etc.)
 */
class AddCancelledToGatherings extends BaseMigration
{
    /**
     * Change Method.
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('gatherings');
        
        $table->addColumn('cancelled_at', 'datetime', [
            'default' => null,
            'null' => true,
            'after' => 'public_page_enabled',
        ]);
        
        $table->addColumn('cancellation_reason', 'text', [
            'default' => null,
            'null' => true,
            'after' => 'cancelled_at',
        ]);
        
        $table->addIndex(['cancelled_at'], [
            'name' => 'idx_gatherings_cancelled_at',
        ]);
        
        $table->update();
    }
}
