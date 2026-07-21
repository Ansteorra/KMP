<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class ExpandCaseInsensitiveAwardText extends BaseMigration
{
    private const COLUMNS = [
        'awards_approval_process_steps' => [
            'label' => 'varchar(255)',
            'step_type' => 'varchar(30)',
            'approver_type' => 'varchar(30)',
            'branch_mode' => 'varchar(50)',
            'branch_type' => 'varchar(50)',
            'threshold_mode' => 'varchar(20)',
            'on_reject' => 'varchar(100)',
            'on_request_changes' => 'varchar(100)',
        ],
        'awards_approval_processes' => [
            'name' => 'varchar(255)',
            'description' => 'text',
        ],
        'awards_awards' => [
            'description' => 'text',
            'insignia' => 'text',
            'charter' => 'text',
            'badge' => 'text',
        ],
        'awards_bestowal_todo_template_items' => [
            'label' => 'varchar(255)',
            'description' => 'text',
            'assignee_type' => 'varchar(30)',
            'branch_type' => 'varchar(50)',
            'branch_mode' => 'varchar(50)',
        ],
        'awards_bestowal_todo_templates' => [
            'name' => 'varchar(255)',
            'description' => 'text',
        ],
        'awards_bestowals' => [
            'lifecycle_status' => 'varchar(20)',
            'source' => 'varchar(50)',
            'noble_notes' => 'text',
            'herald_notes' => 'text',
            'call_into_court' => 'varchar(100)',
            'court_availability' => 'varchar(100)',
            'person_to_notify' => 'varchar(255)',
            'specialty' => 'varchar(255)',
            'reason_summary' => 'text',
            'close_reason' => 'text',
        ],
        'awards_court_agenda_items' => [
            'title' => 'varchar(255)',
            'role' => 'varchar(50)',
            'item_type' => 'varchar(50)',
            'planned_action' => 'varchar(255)',
            'presentation_notes' => 'text',
            'print_notes' => 'text',
        ],
        'awards_court_agenda_segments' => [
            'name' => 'varchar(255)',
            'notes' => 'text',
            'court_type' => 'varchar(50)',
            'planned_start_time' => 'varchar(20)',
        ],
        'awards_court_agendas' => [
            'name' => 'varchar(255)',
            'description' => 'text',
        ],
        'awards_events' => [
            'description' => 'varchar(255)',
        ],
        'awards_recommendation_approval_runs' => [
            'status' => 'varchar(40)',
            'current_step_label' => 'varchar(255)',
            'terminal_reason' => 'varchar(100)',
        ],
        'awards_recommendation_feedback_request_recipients' => [
            'status' => 'varchar(32)',
            'response_comment' => 'text',
        ],
        'awards_recommendation_feedback_requests' => [
            'status' => 'varchar(32)',
            'message' => 'text',
        ],
        'awards_recommendation_migration_results' => [
            'result_status' => 'varchar(30)',
            'target_action' => 'varchar(60)',
            'reason' => 'text',
            'original_state' => 'varchar(255)',
            'original_status' => 'varchar(255)',
        ],
        'awards_recommendation_migration_runs' => [
            'status' => 'varchar(20)',
            'mode' => 'varchar(20)',
        ],
        'awards_recommendations' => [
            'contact_number' => 'varchar(100)',
            'specialty' => 'varchar(255)',
            'reason' => 'text',
            'call_into_court' => 'varchar(100)',
            'court_availability' => 'varchar(100)',
            'status' => 'varchar(100)',
            'state' => 'varchar(255)',
            'group_origin_state' => 'varchar(255)',
            'group_origin_status' => 'varchar(255)',
            'no_action_reason' => 'text',
            'close_reason' => 'text',
        ],
        'awards_recommendations_states_logs' => [
            'from_state' => 'varchar(255)',
            'to_state' => 'varchar(255)',
            'from_status' => 'varchar(255)',
            'to_status' => 'varchar(255)',
        ],
    ];

    private const UNIQUE_COLUMNS = [
        'awards_approval_processes',
        'awards_bestowal_todo_templates',
    ];

    /**
     * Convert human-facing award and lifecycle text to citext.
     */
    public function up(): void
    {
        if (!$this->isPostgres()) {
            return;
        }

        $this->assertNoCaseInsensitiveCollisions();
        foreach (self::COLUMNS as $table => $columns) {
            foreach (array_keys($columns) as $column) {
                $this->execute(sprintf(
                    'ALTER TABLE "%s" ALTER COLUMN "%s" TYPE citext USING "%s"::citext',
                    $table,
                    $column,
                    $column,
                ));
            }
        }
    }

    /**
     * Restore original award varchar and text types.
     */
    public function down(): void
    {
        if (!$this->isPostgres()) {
            return;
        }

        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column => $type) {
                $this->execute(sprintf(
                    'ALTER TABLE "%s" ALTER COLUMN "%s" TYPE %s USING "%s"::text',
                    $table,
                    $column,
                    $type,
                    $column,
                ));
            }
        }
    }

    /**
     * Stop before DDL when a converted unique name would collide.
     */
    private function assertNoCaseInsensitiveCollisions(): void
    {
        foreach (self::UNIQUE_COLUMNS as $table) {
            $result = $this->fetchRow(sprintf(
                'SELECT COUNT(*) AS conflict_groups FROM (' .
                'SELECT LOWER(name) FROM "%s" GROUP BY LOWER(name) HAVING COUNT(*) > 1' .
                ') conflicts',
                $table,
            ));
            if ((int)($result['conflict_groups'] ?? 0) > 0) {
                throw new RuntimeException(sprintf(
                    'Cannot make %s.name case-insensitive: normalized duplicates exist.',
                    $table,
                ));
            }
        }
    }

    /**
     * Check whether the active migration adapter is PostgreSQL.
     */
    private function isPostgres(): bool
    {
        return $this->getAdapter()->getAdapterType() === 'pgsql';
    }
}
