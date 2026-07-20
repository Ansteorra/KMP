<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Add visual court agenda planning for gathering bestowals.
 */
class CreateCourtAgendaTables extends BaseMigration
{
    /**
     * @return void
     */
    public function change(): void
    {
        $this->table('awards_court_agendas', ['id' => false])
            ->addColumn('id', 'integer', [
                'autoIncrement' => true,
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('gathering_id', 'integer', [
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('name', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('description', 'text', [
                'null' => true,
            ])
            ->addColumn('is_default', 'boolean', [
                'default' => false,
                'null' => false,
            ])
            ->addColumn('created', 'datetime', [
                'null' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'null' => true,
            ])
            ->addColumn('created_by', 'integer', [
                'null' => true,
            ])
            ->addColumn('modified_by', 'integer', [
                'null' => true,
            ])
            ->addColumn('deleted', 'datetime', [
                'null' => true,
            ])
            ->addPrimaryKey(['id'])
            ->addIndex(['gathering_id', 'is_default'], ['name' => 'idx_court_agendas_default'])
            ->addIndex(['deleted'], ['name' => 'idx_court_agendas_deleted'])
            ->addForeignKey('gathering_id', 'gatherings', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
            ])
            ->create();

        $this->table('awards_court_agenda_segments', ['id' => false])
            ->addColumn('id', 'integer', [
                'autoIncrement' => true,
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('court_agenda_id', 'integer', [
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('gathering_scheduled_activity_id', 'integer', [
                'limit' => 11,
                'null' => true,
            ])
            ->addColumn('name', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('court_type', 'string', [
                'default' => 'court',
                'limit' => 50,
                'null' => false,
            ])
            ->addColumn('sort_order', 'integer', [
                'default' => 0,
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('planned_start_time', 'string', [
                'limit' => 20,
                'null' => true,
            ])
            ->addColumn('planned_duration_minutes', 'integer', [
                'default' => 0,
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('notes', 'text', [
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'null' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'null' => true,
            ])
            ->addColumn('created_by', 'integer', [
                'null' => true,
            ])
            ->addColumn('modified_by', 'integer', [
                'null' => true,
            ])
            ->addColumn('deleted', 'datetime', [
                'null' => true,
            ])
            ->addPrimaryKey(['id'])
            ->addIndex(['court_agenda_id', 'sort_order'], ['name' => 'idx_court_segments_order'])
            ->addIndex(['gathering_scheduled_activity_id'], ['name' => 'idx_court_segments_activity'])
            ->addIndex(['deleted'], ['name' => 'idx_court_segments_deleted'])
            ->addForeignKey('court_agenda_id', 'awards_court_agendas', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
            ])
            ->addForeignKey('gathering_scheduled_activity_id', 'gathering_scheduled_activities', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'CASCADE',
            ])
            ->create();

        $this->table('awards_court_agenda_items', ['id' => false])
            ->addColumn('id', 'integer', [
                'autoIncrement' => true,
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('court_agenda_segment_id', 'integer', [
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('bestowal_id', 'integer', [
                'limit' => 11,
                'null' => true,
            ])
            ->addColumn('item_type', 'string', [
                'default' => 'bestowal',
                'limit' => 50,
                'null' => false,
            ])
            ->addColumn('role', 'string', [
                'default' => 'present',
                'limit' => 50,
                'null' => false,
            ])
            ->addColumn('title', 'string', [
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('sort_order', 'integer', [
                'default' => 0,
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('planned_action', 'string', [
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('estimated_minutes', 'integer', [
                'default' => 5,
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('duration_locked', 'boolean', [
                'default' => false,
                'null' => false,
            ])
            ->addColumn('presentation_notes', 'text', [
                'null' => true,
            ])
            ->addColumn('print_notes', 'text', [
                'null' => true,
            ])
            ->addColumn('is_optional', 'boolean', [
                'default' => false,
                'null' => false,
            ])
            ->addColumn('include_reasons', 'boolean', [
                'default' => true,
                'null' => false,
            ])
            ->addColumn('include_specialties', 'boolean', [
                'default' => true,
                'null' => false,
            ])
            ->addColumn('created', 'datetime', [
                'null' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'null' => true,
            ])
            ->addColumn('created_by', 'integer', [
                'null' => true,
            ])
            ->addColumn('modified_by', 'integer', [
                'null' => true,
            ])
            ->addColumn('deleted', 'datetime', [
                'null' => true,
            ])
            ->addPrimaryKey(['id'])
            ->addIndex(['court_agenda_segment_id', 'sort_order'], ['name' => 'idx_court_items_order'])
            ->addIndex(['bestowal_id'], ['name' => 'idx_court_items_bestowal'])
            ->addIndex(['deleted'], ['name' => 'idx_court_items_deleted'])
            ->addForeignKey('court_agenda_segment_id', 'awards_court_agenda_segments', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
            ])
            ->addForeignKey('bestowal_id', 'awards_bestowals', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
            ])
            ->create();
    }
}
