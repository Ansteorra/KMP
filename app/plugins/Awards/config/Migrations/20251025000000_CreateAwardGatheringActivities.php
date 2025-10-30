<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * CreateAwardGatheringActivities Migration
 *
 * This creates a join table for the many-to-many relationship between
 * Awards and GatheringActivities.
 *
 * GatheringActivities are configuration/template objects that define
 * types of activities (e.g., "Armored Combat", "Archery").
 * A single activity can be associated with many different awards,
 * and an award can be given out during many activities.
 */
class CreateAwardGatheringActivities extends BaseMigration
{
    public bool $autoId = false;

    /**
     * Create the award_gathering_activities join table linking awards to gathering activities.
     *
     * The table includes an auto-increment primary key `id`, foreign keys `award_id` and `gathering_activity_id`,
     * audit fields (`created`, `modified`, `created_by`, `modified_by`), indexes (including a unique composite index
     * on `award_id` and `gathering_activity_id`), and foreign key constraints with cascade-on-delete.
     */
    public function change(): void
    {
        $table = $this->table('award_gathering_activities');
        $table->addColumn("id", "integer", [
            "autoIncrement" => true,
            "default" => null,
            "limit" => 11,
            "null" => false,
        ]);

        // Foreign keys
        $table->addColumn('award_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => false,
            'comment' => 'FK to awards table'
        ]);
        $table->addColumn('gathering_activity_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => false,
            'comment' => 'FK to gathering_activities table'
        ]);

        // Audit fields
        $table->addColumn("created", "datetime", [
            "default" => null,
            "limit" => null,
            "null" => false,
        ]);
        $table->addColumn("modified", "datetime", [
            "default" => null,
            "limit" => null,
            "null" => true,
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

        // Indexes
        $table->addPrimaryKey(["id"]);
        $table->addIndex(['award_id'], ['name' => 'idx_awact_award']);
        $table->addIndex(['gathering_activity_id'], ['name' => 'idx_awact_activity']);
        // Prevent duplicate activity assignments to same award
        $table->addIndex(
            ['award_id', 'gathering_activity_id'],
            ['name' => 'idx_awact_unique', 'unique' => true]
        );

        // Foreign keys
        $table->addForeignKey('award_id', 'awards_awards', 'id', [
            'delete' => 'CASCADE',
            'update' => 'NO_ACTION',
            'constraint' => 'fk_awact_award'
        ]);
        $table->addForeignKey('gathering_activity_id', 'gathering_activities', 'id', [
            'delete' => 'CASCADE',
            'update' => 'NO_ACTION',
            'constraint' => 'fk_awact_activity'
        ]);

        $table->create();
    }
}