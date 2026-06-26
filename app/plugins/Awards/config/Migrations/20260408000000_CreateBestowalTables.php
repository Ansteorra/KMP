<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Create database tables for award bestowals.
 *
 * Bestowals take over operational work (scroll prep, court scheduling, confirmation)
 * after recommendations enter "Need to Schedule". A bestowal carries a minimal
 * `lifecycle_status` (open|given|cancelled); parallel operational checks are tracked
 * separately by the action-item to-do subsystem. Includes the recommendation join
 * table and the recommendation -> active bestowal link.
 */
class CreateBestowalTables extends BaseMigration
{
    public bool $autoId = false;

    /**
     * Create bestowal, recommendation-join, and recommendation-link tables.
     *
     * @return void
     */
    public function change(): void
    {
        // 1. Main bestowals entity
        $this->table('awards_bestowals', ['id' => false])
            ->addColumn('id', 'integer', [
                'autoIncrement' => true,
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('member_id', 'integer', [
                'limit' => 11,
                'null' => true,
            ])
            ->addColumn('member_sca_name', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('gathering_id', 'integer', [
                'limit' => 11,
                'null' => true,
            ])
            ->addColumn('gathering_scheduled_activity_id', 'integer', [
                'limit' => 11,
                'null' => true,
            ])
            ->addColumn('primary_recommendation_id', 'integer', [
                'limit' => 11,
                'null' => true,
            ])
            ->addColumn('lifecycle_status', 'string', [
                'default' => 'open',
                'limit' => 20,
                'null' => false,
            ])
            ->addColumn('stack_rank', 'integer', [
                'default' => 0,
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('bestowed_at', 'datetime', [
                'null' => true,
            ])
            ->addColumn('source', 'string', [
                'default' => 'recommendation',
                'limit' => 50,
                'null' => false,
            ])
            ->addColumn('noble_notes', 'text', [
                'null' => true,
            ])
            ->addColumn('herald_notes', 'text', [
                'null' => true,
            ])
            ->addColumn('call_into_court', 'string', [
                'limit' => 100,
                'null' => true,
            ])
            ->addColumn('court_availability', 'string', [
                'limit' => 100,
                'null' => true,
            ])
            ->addColumn('person_to_notify', 'string', [
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('close_reason', 'text', [
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
            ->addIndex(['deleted'], ['name' => 'idx_bestowals_deleted'])
            ->addIndex(['gathering_id'], ['name' => 'idx_bestowals_gathering_id'])
            ->addIndex(['stack_rank'], ['name' => 'idx_bestowals_stack_rank'])
            ->addIndex(['member_id'], ['name' => 'idx_bestowals_member_id'])
            ->addIndex(['member_sca_name'], ['name' => 'idx_bestowals_member_sca_name'])
            ->addIndex(['lifecycle_status'], ['name' => 'idx_bestowals_lifecycle_status'])
            ->addForeignKey('member_id', 'members', 'id', [
                'delete' => 'RESTRICT',
                'update' => 'CASCADE',
            ])
            ->addForeignKey('gathering_id', 'gatherings', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'CASCADE',
            ])
            ->addForeignKey('gathering_scheduled_activity_id', 'gathering_scheduled_activities', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'CASCADE',
            ])
            ->addForeignKey('primary_recommendation_id', 'awards_recommendations', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'CASCADE',
            ])
            ->create();

        // 2. Bestowal ↔ recommendation join table
        $this->table('awards_bestowal_recommendations', ['id' => false])
            ->addColumn('id', 'integer', [
                'autoIncrement' => true,
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('bestowal_id', 'integer', [
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('recommendation_id', 'integer', [
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('created', 'datetime', [
                'null' => false,
            ])
            ->addPrimaryKey(['id'])
            ->addIndex(['bestowal_id', 'recommendation_id'], [
                'unique' => true,
                'name' => 'idx_best_rec_unique',
            ])
            ->addForeignKey('bestowal_id', 'awards_bestowals', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
            ])
            ->addForeignKey('recommendation_id', 'awards_recommendations', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
            ])
            ->create();

        // 3. Link recommendations to active bestowal
        $this->table('awards_recommendations')
            ->addColumn('bestowal_id', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 11,
            ])
            ->addIndex(['bestowal_id'], [
                'name' => 'idx_rec_bestowal_id',
            ])
            ->addForeignKey('bestowal_id', 'awards_bestowals', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'CASCADE',
                'constraint' => 'fk_rec_bestowal_id',
            ])
            ->update();
    }
}
