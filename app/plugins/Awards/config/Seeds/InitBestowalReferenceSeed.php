<?php

declare(strict_types=1);

use Migrations\BaseSeed;

/**
 * Seed bestowal statuses, states, field rules, and transitions.
 *
 * Idempotent — skips when statuses already exist. Used by tests when the test
 * database schema is cloned without migration seed data.
 */
class InitBestowalReferenceSeed extends BaseSeed
{
    /**
     * @return void
     */
    public function run(): void
    {
        if ($this->fetchRow('SELECT id FROM awards_bestowal_statuses LIMIT 1')) {
            return;
        }

        $now = date('Y-m-d H:i:s');

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

        $recStateRows = $this->fetchAll(
            'SELECT id, name FROM awards_recommendation_states ORDER BY id',
        );
        $recStateIdMap = [];
        foreach ($recStateRows as $row) {
            $recStateIdMap[$row['name']] = (int)$row['id'];
        }

        $needToScheduleId = $recStateIdMap['Need to Schedule'] ?? null;
        $scheduledId = $recStateIdMap['Scheduled'] ?? null;
        $givenId = $recStateIdMap['Given'] ?? null;
        $announcedNotGivenId = $recStateIdMap['Announced Not Given'] ?? null;
        $kingApprovedId = $recStateIdMap['King Approved'] ?? null;

        $statusRows = $this->fetchAll(
            'SELECT id, sort_order FROM awards_bestowal_statuses ORDER BY sort_order',
        );
        $statusIdMap = [];
        foreach ($statusRows as $row) {
            $statusIdMap[(int)$row['sort_order']] = (int)$row['id'];
        }

        $stateConfigs = [
            1 => [
                [
                    'name' => 'Created',
                    'sort_order' => 1,
                    'supports_gathering' => false,
                    'is_hidden' => false,
                    'sync_recommendation_state_id' => $needToScheduleId,
                    'unwind_recommendation_state_id' => null,
                    'locks_recommendations' => true,
                ],
                [
                    'name' => 'Gathering Assigned',
                    'sort_order' => 2,
                    'supports_gathering' => true,
                    'is_hidden' => false,
                    'sync_recommendation_state_id' => $needToScheduleId,
                    'unwind_recommendation_state_id' => null,
                    'locks_recommendations' => true,
                ],
            ],
            2 => [
                [
                    'name' => 'Scroll Notified',
                    'sort_order' => 1,
                    'supports_gathering' => false,
                    'is_hidden' => false,
                    'sync_recommendation_state_id' => $needToScheduleId,
                    'unwind_recommendation_state_id' => null,
                    'locks_recommendations' => true,
                ],
                [
                    'name' => 'Scroll Ready',
                    'sort_order' => 2,
                    'supports_gathering' => false,
                    'is_hidden' => false,
                    'sync_recommendation_state_id' => $needToScheduleId,
                    'unwind_recommendation_state_id' => null,
                    'locks_recommendations' => true,
                ],
            ],
            3 => [
                [
                    'name' => 'Court Pending',
                    'sort_order' => 1,
                    'supports_gathering' => true,
                    'is_hidden' => false,
                    'sync_recommendation_state_id' => $needToScheduleId,
                    'unwind_recommendation_state_id' => null,
                    'locks_recommendations' => true,
                ],
                [
                    'name' => 'Court Scheduled',
                    'sort_order' => 2,
                    'supports_gathering' => true,
                    'is_hidden' => false,
                    'sync_recommendation_state_id' => $scheduledId,
                    'unwind_recommendation_state_id' => null,
                    'locks_recommendations' => true,
                ],
            ],
            4 => [
                [
                    'name' => 'Ready for Court',
                    'sort_order' => 1,
                    'supports_gathering' => true,
                    'is_hidden' => false,
                    'sync_recommendation_state_id' => $scheduledId,
                    'unwind_recommendation_state_id' => null,
                    'locks_recommendations' => true,
                ],
            ],
            5 => [
                [
                    'name' => 'Given',
                    'sort_order' => 1,
                    'supports_gathering' => true,
                    'is_hidden' => false,
                    'sync_recommendation_state_id' => $givenId,
                    'unwind_recommendation_state_id' => null,
                    'locks_recommendations' => false,
                ],
                [
                    'name' => 'Cancelled',
                    'sort_order' => 2,
                    'supports_gathering' => false,
                    'is_hidden' => false,
                    'sync_recommendation_state_id' => null,
                    'unwind_recommendation_state_id' => $kingApprovedId,
                    'locks_recommendations' => false,
                ],
                [
                    'name' => 'Announced Not Given',
                    'sort_order' => 3,
                    'supports_gathering' => false,
                    'is_hidden' => false,
                    'sync_recommendation_state_id' => $announcedNotGivenId,
                    'unwind_recommendation_state_id' => null,
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
                    'sync_recommendation_state_id' => $state['sync_recommendation_state_id'],
                    'unwind_recommendation_state_id' => $state['unwind_recommendation_state_id'],
                    'locks_recommendations' => $state['locks_recommendations'],
                    'is_system' => false,
                    'supports_gathering' => $state['supports_gathering'],
                    'is_hidden' => $state['is_hidden'],
                    'created' => $now,
                ]);
            }
        }
        $stateTable->saveData();

        $stateRows = $this->fetchAll(
            'SELECT id, name FROM awards_bestowal_states ORDER BY id',
        );
        $stateIdMap = [];
        foreach ($stateRows as $row) {
            $stateIdMap[$row['name']] = (int)$row['id'];
        }

        require_once dirname(__DIR__) . '/bestowal_workflow_field_rules.php';

        $rulesTable = $this->table('awards_bestowal_state_field_rules');
        insertBestowalCumulativeFieldRules($stateIdMap, $rulesTable, $now);
        $rulesTable->saveData();

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
