<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddBeforeReleasePerformanceIndexes extends BaseMigration
{
    /**
     * Add hot-path core performance indexes.
     *
     * @return void
     */
    public function up(): void
    {
        if ($this->isPostgres()) {
            $this->execute(
                'CREATE INDEX IF NOT EXISTS idx_warrants_member_current ' .
                'ON warrants (member_id, status, start_on, expires_on)',
            );
            $this->execute('CREATE INDEX IF NOT EXISTS idx_members_branch_id ON members (branch_id)');
            $this->execute(
                'CREATE INDEX IF NOT EXISTS idx_members_status_live ON members (status) WHERE deleted IS NULL',
            );
            $this->execute('DROP INDEX IF EXISTS notes_topic_model');

            return;
        }

        $this->table('warrants')
            ->addIndex(['member_id', 'status', 'start_on', 'expires_on'], ['name' => 'idx_warrants_member_current'])
            ->update();

        $this->table('members')
            ->addIndex(['branch_id'], ['name' => 'idx_members_branch_id'])
            ->addIndex(['status'], ['name' => 'idx_members_status_live'])
            ->update();
    }

    /**
     * Remove hot-path core performance indexes.
     *
     * @return void
     */
    public function down(): void
    {
        if ($this->isPostgres()) {
            $this->execute('DROP INDEX IF EXISTS idx_warrants_member_current');
            $this->execute('DROP INDEX IF EXISTS idx_members_branch_id');
            $this->execute('DROP INDEX IF EXISTS idx_members_status_live');
            $this->execute('CREATE INDEX IF NOT EXISTS notes_topic_model ON notes (entity_type)');

            return;
        }

        $this->table('members')
            ->removeIndexByName('idx_members_status_live')
            ->removeIndexByName('idx_members_branch_id')
            ->update();

        $this->table('warrants')
            ->removeIndexByName('idx_warrants_member_current')
            ->update();
    }

    /**
     * Check whether the active migration adapter is PostgreSQL.
     *
     * @return bool
     */
    private function isPostgres(): bool
    {
        return $this->getAdapter()->getAdapterType() === 'pgsql';
    }
}
