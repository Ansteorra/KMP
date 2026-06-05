<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Create database tables for award bestowal lifecycle management.
 *
 * Bestowals take over operational work (scroll prep, court scheduling, confirmation)
 * after recommendations enter "Need to Schedule". Includes a full state machine,
 * join table for grouped recommendations, and sync mapping to recommendation states.
 */
class CreateBestowalTables extends BaseMigration
{
    public bool $autoId = false;

    /**
     * Create bestowal lifecycle, linking, and logging tables.
     *
     * @return void
     */
    public function change(): void
    {
        // 1. Statuses table
        $this->table('awards_bestowal_statuses', ['id' => false])
            ->addColumn('id', 'integer', [
                'autoIncrement' => true,
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('name', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('sort_order', 'integer', [
                'default' => 0,
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
            ->addIndex(['name'], ['unique' => true, 'name' => 'idx_best_statuses_name'])
            ->addIndex(['deleted'], ['name' => 'idx_best_statuses_deleted'])
            ->create();

        // 2. States table
        $this->table('awards_bestowal_states', ['id' => false])
            ->addColumn('id', 'integer', [
                'autoIncrement' => true,
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('status_id', 'integer', [
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('name', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('sort_order', 'integer', [
                'default' => 0,
                'null' => false,
            ])
            ->addColumn('sync_recommendation_state', 'string', [
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('unwind_recommendation_state', 'string', [
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('locks_recommendations', 'boolean', [
                'default' => false,
                'null' => false,
            ])
            ->addColumn('is_system', 'boolean', [
                'default' => false,
                'null' => false,
            ])
            ->addColumn('supports_gathering', 'boolean', [
                'default' => false,
                'null' => false,
            ])
            ->addColumn('is_hidden', 'boolean', [
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
            ->addIndex(['name'], ['unique' => true, 'name' => 'idx_best_states_name'])
            ->addIndex(['deleted'], ['name' => 'idx_best_states_deleted'])
            ->addForeignKey('status_id', 'awards_bestowal_statuses', 'id', [
                'delete' => 'RESTRICT',
                'update' => 'CASCADE',
            ])
            ->create();

        // 3. State field rules table
        $this->table('awards_bestowal_state_field_rules', ['id' => false])
            ->addColumn('id', 'integer', [
                'autoIncrement' => true,
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('state_id', 'integer', [
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('field_target', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('rule_type', 'string', [
                'limit' => 50,
                'null' => false,
            ])
            ->addColumn('rule_value', 'string', [
                'limit' => 255,
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
            ->addPrimaryKey(['id'])
            ->addIndex(['state_id', 'field_target', 'rule_type'], [
                'unique' => true,
                'name' => 'idx_best_state_field_rule_unique',
            ])
            ->addForeignKey('state_id', 'awards_bestowal_states', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
            ])
            ->create();

        // 4. State transitions table
        $this->table('awards_bestowal_state_transitions', ['id' => false])
            ->addColumn('id', 'integer', [
                'autoIncrement' => true,
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('from_state_id', 'integer', [
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('to_state_id', 'integer', [
                'limit' => 11,
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
            ->addPrimaryKey(['id'])
            ->addIndex(['from_state_id', 'to_state_id'], [
                'unique' => true,
                'name' => 'idx_best_state_transition_unique',
            ])
            ->addForeignKey('from_state_id', 'awards_bestowal_states', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
            ])
            ->addForeignKey('to_state_id', 'awards_bestowal_states', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
            ])
            ->create();

        // 5. Main bestowals entity
        $this->table('awards_bestowals', ['id' => false])
            ->addColumn('id', 'integer', [
                'autoIncrement' => true,
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('member_id', 'integer', [
                'limit' => 11,
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
            ->addColumn('status', 'string', [
                'limit' => 100,
                'null' => false,
            ])
            ->addColumn('state', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('state_date', 'datetime', [
                'null' => true,
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

        // 6. Bestowal ↔ recommendation join table
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

        // 7. State change audit log
        $this->table('awards_bestowals_states_logs', ['id' => false])
            ->addColumn('id', 'integer', [
                'autoIncrement' => true,
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('bestowal_id', 'integer', [
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('from_state', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('to_state', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('from_status', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('to_status', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('created', 'datetime', [
                'null' => false,
            ])
            ->addColumn('created_by', 'integer', [
                'null' => true,
            ])
            ->addPrimaryKey(['id'])
            ->addForeignKey('bestowal_id', 'awards_bestowals', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
            ])
            ->create();

        // 8. Link recommendations to active bestowal
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

        // Seed data
        $this->seedData();
    }

    /**
     * Seed default bestowal statuses, states, field rules, and transitions.
     *
     * @return void
     */
    private function seedData(): void
    {
        $now = date('Y-m-d H:i:s');

        // Statuses
        $statuses = [
            ['name' => 'Planning', 'sort_order' => 1],
            ['name' => 'Preparation', 'sort_order' => 2],
            ['name' => 'Scheduling', 'sort_order' => 3],
            ['name' => 'Ready', 'sort_order' => 4],
            ['name' => 'Closed', 'sort_order' => 5],
        ];

        $statusTable = $this->table('awards_bestowal_statuses');
        foreach ($statuses as $status) {
            $statusTable->insert([
                'name' => $status['name'],
                'sort_order' => $status['sort_order'],
                'created' => $now,
            ]);
        }
        $statusTable->saveData();

        // Recommendation state names for sync/unwind mappings (YAML state machine)
        $needToScheduleId = 'Need to Schedule';
        $scheduledId = 'Scheduled';
        $givenId = 'Given';
        $announcedNotGivenId = 'Announced Not Given';
        $kingApprovedId = 'King Approved';

        // Retrieve status IDs by sort_order
        $statusRows = $this->fetchAll(
            'SELECT id, sort_order FROM awards_bestowal_statuses ORDER BY sort_order',
        );
        $statusIdMap = [];
        foreach ($statusRows as $row) {
            $statusIdMap[(int)$row['sort_order']] = (int)$row['id'];
        }

        $stateConfigs = [
            1 => [ // Planning
                [
                    'name' => 'Created',
                    'sort_order' => 1,
                    'supports_gathering' => false,
                    'is_hidden' => false,
                    'sync_recommendation_state' => $needToScheduleId,
                    'unwind_recommendation_state' => null,
                    'locks_recommendations' => true,
                ],
                [
                    'name' => 'Gathering Assigned',
                    'sort_order' => 2,
                    'supports_gathering' => true,
                    'is_hidden' => false,
                    'sync_recommendation_state' => $needToScheduleId,
                    'unwind_recommendation_state' => null,
                    'locks_recommendations' => true,
                ],
            ],
            2 => [ // Preparation
                [
                    'name' => 'Scroll Notified',
                    'sort_order' => 1,
                    'supports_gathering' => false,
                    'is_hidden' => false,
                    'sync_recommendation_state' => $needToScheduleId,
                    'unwind_recommendation_state' => null,
                    'locks_recommendations' => true,
                ],
                [
                    'name' => 'Scroll Ready',
                    'sort_order' => 2,
                    'supports_gathering' => false,
                    'is_hidden' => false,
                    'sync_recommendation_state' => $needToScheduleId,
                    'unwind_recommendation_state' => null,
                    'locks_recommendations' => true,
                ],
            ],
            3 => [ // Scheduling
                [
                    'name' => 'Court Pending',
                    'sort_order' => 1,
                    'supports_gathering' => true,
                    'is_hidden' => false,
                    'sync_recommendation_state' => $needToScheduleId,
                    'unwind_recommendation_state' => null,
                    'locks_recommendations' => true,
                ],
                [
                    'name' => 'Court Scheduled',
                    'sort_order' => 2,
                    'supports_gathering' => true,
                    'is_hidden' => false,
                    'sync_recommendation_state' => $scheduledId,
                    'unwind_recommendation_state' => null,
                    'locks_recommendations' => true,
                ],
            ],
            4 => [ // Ready
                [
                    'name' => 'Ready for Court',
                    'sort_order' => 1,
                    'supports_gathering' => true,
                    'is_hidden' => false,
                    'sync_recommendation_state' => $scheduledId,
                    'unwind_recommendation_state' => null,
                    'locks_recommendations' => true,
                ],
            ],
            5 => [ // Closed
                [
                    'name' => 'Given',
                    'sort_order' => 1,
                    'supports_gathering' => true,
                    'is_hidden' => false,
                    'sync_recommendation_state' => $givenId,
                    'unwind_recommendation_state' => null,
                    'locks_recommendations' => false,
                ],
                [
                    'name' => 'Cancelled',
                    'sort_order' => 2,
                    'supports_gathering' => false,
                    'is_hidden' => false,
                    'sync_recommendation_state' => null,
                    'unwind_recommendation_state' => $kingApprovedId,
                    'locks_recommendations' => false,
                ],
                [
                    'name' => 'Announced Not Given',
                    'sort_order' => 3,
                    'supports_gathering' => false,
                    'is_hidden' => false,
                    'sync_recommendation_state' => $announcedNotGivenId,
                    'unwind_recommendation_state' => null,
                    'locks_recommendations' => true,
                ],
            ],
        ];

        $stateTable = $this->table('awards_bestowal_states');
        foreach ($stateConfigs as $statusSortOrder => $states) {
            $statusId = $statusIdMap[$statusSortOrder];
            foreach ($states as $state) {
                $stateTable->insert([
                    'status_id' => $statusId,
                    'name' => $state['name'],
                    'sort_order' => $state['sort_order'],
                    'sync_recommendation_state' => $state['sync_recommendation_state'],
                    'unwind_recommendation_state' => $state['unwind_recommendation_state'],
                    'locks_recommendations' => $state['locks_recommendations'],
                    'is_system' => false,
                    'supports_gathering' => $state['supports_gathering'],
                    'is_hidden' => $state['is_hidden'],
                    'created' => $now,
                ]);
            }
        }
        $stateTable->saveData();

        // Field rules
        $stateRows = $this->fetchAll(
            'SELECT id, name FROM awards_bestowal_states ORDER BY id',
        );
        $stateIdMap = [];
        foreach ($stateRows as $row) {
            $stateIdMap[$row['name']] = (int)$row['id'];
        }

        require_once __DIR__ . '/../bestowal_workflow_field_rules.php';

        $rulesTable = $this->table('awards_bestowal_state_field_rules');
        insertBestowalCumulativeFieldRules($stateIdMap, $rulesTable, $now);
        $rulesTable->saveData();

        // Transitions: seed all-to-all (every state can transition to every other state)
        $allStateIds = array_values($stateIdMap);
        $transitionsTable = $this->table('awards_bestowal_state_transitions');
        foreach ($allStateIds as $fromId) {
            foreach ($allStateIds as $toId) {
                if ($fromId !== $toId) {
                    $transitionsTable->insert([
                        'from_state_id' => $fromId,
                        'to_state_id' => $toId,
                        'created' => $now,
                    ]);
                }
            }
        }
        $transitionsTable->saveData();
    }
}
