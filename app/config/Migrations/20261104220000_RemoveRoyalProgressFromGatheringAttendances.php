<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class RemoveRoyalProgressFromGatheringAttendances extends BaseMigration
{
    /** Remove retired RSVP metadata while preserving ordinary attendance records. */
    public function up(): void
    {
        foreach ($this->fetchAll("SELECT id, config FROM grid_views WHERE grid_key LIKE 'Gatherings.%'") as $view) {
            $config = json_decode($view['config'], true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($config)) {
                continue;
            }
            $cleaned = $this->removeRetiredFilters($config);
            if ($cleaned !== $config) {
                $this->execute('UPDATE grid_views SET config = ? WHERE id = ?', [
                    json_encode($cleaned, JSON_THROW_ON_ERROR), $view['id'],
                ]);
            }
        }

        $this->table('gathering_attendances')
            ->removeIndexByName('idx_gathering_attendances_progress')
            ->removeColumn('is_royal_progress')
            ->removeColumn('progress_office_id')
            ->removeColumn('progress_office_name')
            ->removeColumn('progress_branch_name')
            ->update();
    }

    /** Restore empty legacy columns; removed RSVP metadata cannot be recovered. */
    public function down(): void
    {
        $this->table('gathering_attendances')
            ->addColumn('is_royal_progress', 'boolean', ['default' => false, 'null' => false])
            ->addColumn('progress_office_id', 'integer', ['default' => null, 'null' => true])
            ->addColumn('progress_office_name', 'string', ['limit' => 255, 'default' => null, 'null' => true])
            ->addColumn('progress_branch_name', 'string', ['limit' => 255, 'default' => null, 'null' => true])
            ->addIndex(['gathering_id', 'is_royal_progress'], ['name' => 'idx_gathering_attendances_progress'])
            ->update();
        $this->execute('ALTER TABLE gathering_attendances ALTER COLUMN progress_office_name TYPE citext');
        $this->execute('ALTER TABLE gathering_attendances ALTER COLUMN progress_branch_name TYPE citext');
    }

    /** Remove retired fields from saved filters, nested expressions, sort, and columns. */
    private function removeRetiredFilters(array $node): ?array
    {
        if (($node['field'] ?? $node['key'] ?? null) === 'royal_progress') {
            return null;
        }
        $cleaned = [];
        foreach ($node as $key => $value) {
            if ($key === 'royal_progress') {
                continue;
            }
            if (is_array($value)) {
                $value = $this->removeRetiredFilters($value);
                if ($value === null) {
                    continue;
                }
            }
            $cleaned[$key] = $value;
        }
        if (!empty($node['conditions']) && empty($cleaned['conditions'])) {
            return null;
        }

        return array_is_list($node) ? array_values($cleaned) : $cleaned;
    }
}
