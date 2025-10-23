<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * CreateGatheringWaiverActivities Migration
 * 
 * Creates the waivers_gathering_waiver_activities table for associating uploaded waivers
 * with the specific activities they cover (many-to-many join table).
 * 
 * This migration creates:
 * - Table: waivers_gathering_waiver_activities
 * - Foreign keys to gathering_waivers and gathering_activities
 * - Unique constraint on waiver-activity combination
 * - Indexes for efficient querying
 */
class CreateGatheringWaiverActivities extends BaseMigration
{
    public bool $autoId = false;

    /**
     * Change Method.
     *
     * Creates the waivers_gathering_waiver_activities join table.
     * 
     * This table enables the many-to-many relationship between waivers and activities:
     * - One waiver can cover multiple activities (general waiver)
     * - One activity can have multiple waivers (different participants)
     * - Prevents duplicate waiver-activity associations
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('waivers_gathering_waiver_activities');

        // Primary key
        $table->addColumn("id", "integer", [
            "autoIncrement" => true,
            "default" => null,
            "limit" => 11,
            "null" => false,
            "comment" => "Unique identifier"
        ]);

        // Foreign key: gathering_waiver_id
        $table->addColumn('gathering_waiver_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => false,
            'signed' => true,
            'comment' => 'FK to gathering_waivers.id - the waiver document'
        ]);

        // Foreign key: gathering_activity_id
        $table->addColumn('gathering_activity_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => false,
            'signed' => true,
            'comment' => 'FK to gathering_activities.id - the activity covered'
        ]);

        $table->addColumn("modified", "datetime", [
            "default" => null,
            "limit" => null,
            "null" => true,
        ]);
        $table->addColumn("created", "datetime", [
            "default" => null,
            "limit" => null,
            "null" => false,
        ]);
        $table->addColumn("created_by", "integer", [
            "default" => null,
            "limit" => null,
            "null" => true,
        ]);
        $table->addColumn("modified_by", "integer", [
            "default" => null,
            "limit" => null,
            "null" => true,
        ]);
        $table->addColumn("deleted", "datetime", [
            "default" => null,
            "limit" => null,
            "null" => true,
        ]);

        // Primary key
        $table->addPrimaryKey(["id"]);

        // Indexes for efficient querying
        $table->addIndex(['gathering_waiver_id'], [
            'name' => 'idx_gathering_waiver_activities_waiver'
        ]);

        $table->addIndex(['gathering_activity_id'], [
            'name' => 'idx_gathering_waiver_activities_activity'
        ]);

        // Unique constraint: prevent duplicate waiver-activity associations
        $table->addIndex(['gathering_waiver_id', 'gathering_activity_id'], [
            'name' => 'idx_gathering_waiver_activities_unique',
            'unique' => true
        ]);

        // Foreign key constraints
        $table->addForeignKey('gathering_waiver_id', 'waivers_gathering_waivers', 'id', [
            'delete' => 'CASCADE',
            'update' => 'CASCADE',
            'constraint' => 'fk_gathering_waiver_activities_waiver'
        ]);

        $table->addForeignKey('gathering_activity_id', 'gathering_activities', 'id', [
            'delete' => 'CASCADE',
            'update' => 'CASCADE',
            'constraint' => 'fk_gathering_waiver_activities_activity'
        ]);

        $table->create();
    }
}