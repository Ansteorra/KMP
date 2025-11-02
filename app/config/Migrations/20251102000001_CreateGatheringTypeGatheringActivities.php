<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * CreateGatheringTypeGatheringActivities Migration
 *
 * This creates a join table for the many-to-many relationship between
 * GatheringTypes and GatheringActivities. This allows GatheringTypes to
 * define template activities that will be automatically added to gatherings
 * of that type.
 *
 * The `not_removable` flag indicates whether the activity can be removed
 * from a gathering once added via the template.
 */
class CreateGatheringTypeGatheringActivities extends BaseMigration
{
    public bool $autoId = false;

    /**
     * Create the gathering_type_gathering_activities join table with its columns, indexes, and foreign key constraints.
     *
     * This migration defines an explicit `id` primary key, `gathering_type_id` and `gathering_activity_id` foreign keys,
     * a `not_removable` flag for template enforcement, a `sort_order` column for ordering activities,
     * audit fields (`created`, `modified`, `created_by`, `modified_by`), and indexes including a unique composite
     * index on (`gathering_type_id`, `gathering_activity_id`) to prevent duplicate assignments.
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('gathering_type_gathering_activities');
        $table->addColumn("id", "integer", [
            "autoIncrement" => true,
            "default" => null,
            "limit" => 11,
            "null" => false,
        ]);

        // Foreign keys
        $table->addColumn('gathering_type_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => false,
            'comment' => 'FK to gathering_types table'
        ]);
        $table->addColumn('gathering_activity_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => false,
            'comment' => 'FK to gathering_activities table'
        ]);

        // Not removable flag - if true, activity cannot be removed from gatherings of this type
        $table->addColumn('not_removable', 'boolean', [
            'default' => false,
            'null' => false,
            'comment' => 'If true, this activity cannot be removed from gatherings of this type'
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
        $table->addIndex(['gathering_type_id'], ['name' => 'idx_gtgact_type']);
        $table->addIndex(['gathering_activity_id'], ['name' => 'idx_gtgact_activity']);
        // Prevent duplicate activity assignments to same gathering type
        $table->addIndex(
            ['gathering_type_id', 'gathering_activity_id'],
            ['name' => 'idx_gtgact_unique', 'unique' => true]
        );
        $table->addIndex(['not_removable'], ['name' => 'idx_gtgact_not_removable']);

        // Foreign keys
        $table->addForeignKey('gathering_type_id', 'gathering_types', 'id', [
            'delete' => 'CASCADE',
            'update' => 'NO_ACTION',
            'constraint' => 'fk_gtgact_type'
        ]);
        $table->addForeignKey('gathering_activity_id', 'gathering_activities', 'id', [
            'delete' => 'CASCADE',
            'update' => 'NO_ACTION',
            'constraint' => 'fk_gtgact_activity'
        ]);

        $table->create();
    }
}