<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Add exemption fields to gathering_waivers table
 * 
 * Allows gathering_waivers to store exemptions (attestations that waiver was not needed)
 * instead of requiring a separate exemptions table. This simplifies reporting and allows
 * exemptions to be reviewed/declined like regular waivers.
 * 
 * Changes:
 * - Make document_id nullable (it will be null for exemptions)
 * - Add is_exemption flag
 * - Add exemption_reason field
 * - Drop the separate waivers_gathering_waiver_exemptions table
 * 
 * Note: Exemptions link to activities through the waivers_gathering_waiver_activities
 * join table, just like regular waivers do.
 */
class AddExemptionFieldsToGatheringWaivers extends BaseMigration
{
    /**
     * Change Method.
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('waivers_gathering_waivers');

        // Add is_exemption flag to identify exemption records
        $table->addColumn('is_exemption', 'boolean', [
            'default' => false,
            'null' => false,
            'comment' => 'True if this is an exemption (attestation waiver not needed)',
            'after' => 'document_id'
        ]);

        // Add exemption_reason field for storing why waiver wasn't needed
        $table->addColumn('exemption_reason', 'string', [
            'default' => null,
            'limit' => 500,
            'null' => true,
            'comment' => 'Reason why waiver was not required (only set for exemptions)',
            'after' => 'is_exemption'
        ]);

        // Add index on is_exemption for filtering
        $table->addIndex(['is_exemption'], [
            'name' => 'idx_gathering_waivers_is_exemption'
        ]);

        $table->update();

        // Drop the separate exemptions table since we no longer need it
        $this->table('waivers_gathering_waiver_exemptions')->drop()->save();
    }
}
