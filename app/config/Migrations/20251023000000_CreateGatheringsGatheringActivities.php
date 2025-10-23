<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * CreateGatheringsGatheringActivities Migration
 *
 * This creates a join table for the many-to-many relationship between
 * Gatherings and GatheringActivities.
 *
 * GatheringActivities are configuration/template objects that define
 * types of activities (e.g., "Armored Combat", "Archery").
 * A single activity can be used at many different gatherings,
 * and a gathering can have many activities.
 */
class CreateGatheringsGatheringActivities extends BaseMigration
{
    public bool $autoId = false;

    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/4/en/migrations.html#the-change-method
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('gatherings_gathering_activities');
        $table->addColumn("id", "integer", [
            "autoIncrement" => true,
            "default" => null,
            "limit" => 11,
            "null" => false,
        ]);

        // Foreign keys
        $table->addColumn('gathering_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => false,
            'comment' => 'FK to gatherings table'
        ]);
        $table->addColumn('gathering_activity_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => false,
            'comment' => 'FK to gathering_activities table'
        ]);

        // Sort order for activities within a gathering
        $table->addColumn('sort_order', 'integer', [
            'default' => 0,
            'limit' => 11,
            'null' => false,
            'comment' => 'Display order of activities within a gathering'
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
        $table->addIndex(['gathering_id'], ['name' => 'idx_ggact_gathering']);
        $table->addIndex(['gathering_activity_id'], ['name' => 'idx_ggact_activity']);
        // Prevent duplicate activity assignments to same gathering
        $table->addIndex(
            ['gathering_id', 'gathering_activity_id'],
            ['name' => 'idx_ggact_unique', 'unique' => true]
        );
        $table->addIndex(['sort_order'], ['name' => 'idx_ggact_sort']);

        // Foreign keys
        $table->addForeignKey('gathering_id', 'gatherings', 'id', [
            'delete' => 'CASCADE',
            'update' => 'NO_ACTION',
            'constraint' => 'fk_ggact_gathering'
        ]);
        $table->addForeignKey('gathering_activity_id', 'gathering_activities', 'id', [
            'delete' => 'CASCADE',
            'update' => 'NO_ACTION',
            'constraint' => 'fk_ggact_activity'
        ]);
        $table->addForeignKey('created_by', 'members', 'id', [
            'delete' => 'NO_ACTION',
            'update' => 'NO_ACTION',
            'constraint' => 'fk_ggact_created_by'
        ]);

        $table->create();
    }
}
