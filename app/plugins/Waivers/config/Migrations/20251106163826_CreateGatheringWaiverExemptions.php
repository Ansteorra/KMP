<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * CreateGatheringWaiverExemptions Migration
 * 
 * Creates the waivers_gathering_waiver_exemptions table for tracking attestations
 * that waivers were not needed for specific activity/waiver type combinations.
 * 
 * This allows authorized users to attest that a waiver was not required for a
 * specific activity (e.g., "No minors present", "Activity cancelled", etc.)
 */
class CreateGatheringWaiverExemptions extends BaseMigration
{
    public bool $autoId = false;

    /**
     * Change Method.
     *
     * Creates the waivers_gathering_waiver_exemptions table.
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('waivers_gathering_waiver_exemptions');

        // Primary key
        $table->addColumn("id", "integer", [
            "autoIncrement" => true,
            "default" => null,
            "limit" => 11,
            "null" => false,
            "comment" => "Unique identifier"
        ]);

        // Foreign key: gathering_activity_id
        $table->addColumn('gathering_activity_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => false,
            'signed' => true,
            'comment' => 'FK to gathering_activities.id - the activity this exemption is for'
        ]);

        // Foreign key: waiver_type_id
        $table->addColumn('waiver_type_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => false,
            'signed' => true,
            'comment' => 'FK to waivers_waiver_types.id - the waiver type being exempted'
        ]);

        // Exemption reason
        $table->addColumn('reason', 'string', [
            'default' => null,
            'limit' => 500,
            'null' => false,
            'comment' => 'Reason why waiver was not required (from waiver type exemption_reasons)'
        ]);

        // Foreign key: member_id (who attested)
        $table->addColumn('member_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => false,
            'signed' => true,
            'comment' => 'FK to members.id - who attested that waiver was not needed'
        ]);

        // Additional notes (optional)
        $table->addColumn('notes', 'text', [
            'default' => null,
            'null' => true,
            'comment' => 'Optional additional notes about the exemption'
        ]);

        // Timestamps
        $table->addColumn('created', 'datetime', [
            'default' => null,
            'null' => false,
            'comment' => 'When exemption was recorded'
        ]);

        // Primary key
        $table->addPrimaryKey(["id"]);

        // Indexes
        $table->addIndex(['gathering_activity_id'], ['name' => 'idx_gwe_activity']);
        $table->addIndex(['waiver_type_id'], ['name' => 'idx_gwe_waiver_type']);
        $table->addIndex(['member_id'], ['name' => 'idx_gwe_member']);
        $table->addIndex(['created'], ['name' => 'idx_gwe_created']);

        // Unique constraint: one exemption per activity/waiver type combination
        $table->addIndex(
            ['gathering_activity_id', 'waiver_type_id'],
            ['name' => 'idx_gwe_unique_exemption', 'unique' => true]
        );

        $table->create();

        // Add foreign key constraints
        $table->addForeignKey(
            'gathering_activity_id',
            'gathering_activities',
            'id',
            ['delete' => 'CASCADE', 'update' => 'CASCADE']
        )->update();

        $table->addForeignKey(
            'waiver_type_id',
            'waivers_waiver_types',
            'id',
            ['delete' => 'RESTRICT', 'update' => 'CASCADE']
        )->update();

        $table->addForeignKey(
            'member_id',
            'members',
            'id',
            ['delete' => 'RESTRICT', 'update' => 'CASCADE']
        )->update();
    }
}
