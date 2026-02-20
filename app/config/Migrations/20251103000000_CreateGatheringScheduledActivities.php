<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * CreateGatheringScheduledActivities Migration
 *
 * This creates a table for scheduling activities within a gathering.
 * Allows gathering hosts to create detailed schedules with start/end times,
 * custom titles and descriptions, and pre-registration flags.
 *
 * Activities can either reference an existing GatheringActivity (from the gathering's
 * activity list) or be marked as "other" for ad-hoc scheduled items.
 */
class CreateGatheringScheduledActivities extends BaseMigration
{
    public bool $autoId = false;

    /**
     * Create the gathering_scheduled_activities table with its columns, indexes, and foreign key constraints.
     *
     * This migration defines an explicit `id` primary key, `gathering_id` foreign key,
     * optional `gathering_activity_id` foreign key (null for "other" activities),
     * datetime fields for scheduling, text fields for custom content, boolean flags,
     * and standard audit fields.
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('gathering_scheduled_activities', ['id' => false]);
        $table->addColumn("id", "integer", [
            "autoIncrement" => true,
            "default" => null,
            "limit" => 11,
            "null" => false,
        ]);

        // Foreign key to gatherings table (required)
        $table->addColumn('gathering_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => false,
            'comment' => 'FK to gatherings table - which gathering this schedule belongs to'
        ]);

        // Foreign key to gathering_activities table (optional - null for "other" activities)
        $table->addColumn('gathering_activity_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => true,
            'comment' => 'FK to gathering_activities table - null for "other" activities'
        ]);

        // Scheduling fields
        $table->addColumn('start_datetime', 'datetime', [
            'default' => null,
            'null' => false,
            'comment' => 'When the scheduled activity begins'
        ]);

        $table->addColumn('end_datetime', 'datetime', [
            'default' => null,
            'null' => false,
            'comment' => 'When the scheduled activity ends'
        ]);

        // Custom content fields
        $table->addColumn('display_title', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
            'comment' => 'Custom title for this scheduled activity'
        ]);

        $table->addColumn('description', 'text', [
            'default' => null,
            'null' => true,
            'comment' => 'Custom description for this scheduled activity'
        ]);

        // Flags
        $table->addColumn('pre_register', 'boolean', [
            'default' => false,
            'null' => false,
            'comment' => 'Whether pre-registration is required/available'
        ]);

        $table->addColumn('is_other', 'boolean', [
            'default' => false,
            'null' => false,
            'comment' => 'Whether this is an "other" activity (not linked to gathering_activity)'
        ]);

        // Standard audit fields
        $table->addColumn('created', 'datetime', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('modified', 'datetime', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('created_by', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => true,
            'comment' => 'FK to members table - who created this'
        ]);
        $table->addColumn('modified_by', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => true,
            'comment' => 'FK to members table - who last modified this'
        ]);

        // Set primary key
        $table->addPrimaryKey(['id']);

        // Add indexes for performance
        $table->addIndex(['gathering_id'], [
            'name' => 'idx_gathering_scheduled_activities_gathering',
        ]);
        $table->addIndex(['gathering_activity_id'], [
            'name' => 'idx_gathering_scheduled_activities_activity',
        ]);
        $table->addIndex(['start_datetime'], [
            'name' => 'idx_gathering_scheduled_activities_start',
        ]);
        $table->addIndex(['end_datetime'], [
            'name' => 'idx_gathering_scheduled_activities_end',
        ]);
        $table->addIndex(['created_by'], [
            'name' => 'idx_gathering_scheduled_activities_created_by',
        ]);
        $table->addIndex(['modified_by'], [
            'name' => 'idx_gathering_scheduled_activities_modified_by',
        ]);

        // Add foreign key constraints
        $table->addForeignKey(
            'gathering_id',
            'gatherings',
            'id',
            [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
                'constraint' => 'fk_gathering_scheduled_activities_gathering'
            ]
        );

        $table->addForeignKey(
            'gathering_activity_id',
            'gathering_activities',
            'id',
            [
                'delete' => 'SET_NULL',
                'update' => 'CASCADE',
                'constraint' => 'fk_gathering_scheduled_activities_activity'
            ]
        );

        $table->addForeignKey(
            'created_by',
            'members',
            'id',
            [
                'delete' => 'SET_NULL',
                'update' => 'CASCADE',
                'constraint' => 'fk_gathering_scheduled_activities_created_by'
            ]
        );

        $table->addForeignKey(
            'modified_by',
            'members',
            'id',
            [
                'delete' => 'SET_NULL',
                'update' => 'CASCADE',
                'constraint' => 'fk_gathering_scheduled_activities_modified_by'
            ]
        );

        $table->create();
    }
}
