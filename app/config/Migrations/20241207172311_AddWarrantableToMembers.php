<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class AddWarrantableToMembers extends BaseMigration
{
    public function useTransactions(): bool
    {
        return false;
    }
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     * @return void
     */
    public function up(): void
    {
        $table = $this->table('members');
        $table->addColumn('warrantable', 'boolean', [
            'default' => false,
            'null' => false,
        ]);
        $table->update();

        // Pre-compute warrantable status for existing members.
        // Column defaults to false; this is a best-effort optimization.
        // If it fails (e.g. schema cache issues on Postgres), members
        // will be updated on their next save/login.
        try {
            $connName = $this->getAdapter()->getConnection()->configName();
            $conn = \Cake\Datasource\ConnectionManager::get($connName);
            (new \Cake\Database\SchemaCache($conn))->clear();
            \Cake\ORM\TableRegistry::getTableLocator()->clear();

            $membersTable = \Cake\ORM\TableRegistry::getTableLocator()->get('Members');
            $members = $membersTable->find('all');

            foreach ($members as $member) {
                $member->warrantableReview();
                $membersTable->save($member, ['checkRules' => false, 'callbacks' => false]);
            }
        } catch (\Exception $e) {
            // Non-fatal: members default to warrantable=false
            echo "  [warn] warrantable pre-compute skipped: " . $e->getMessage() . "\n";
        }
    }
    public function down(): void
    {
        $table = $this->table('members');
        $table->removeColumn('warrantable');
        $table->update();
    }
}
