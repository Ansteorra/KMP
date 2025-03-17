<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class AddWarrantableToMembers extends BaseMigration
{
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
        // Load the MembersTable
        $membersTable = \Cake\ORM\TableRegistry::getTableLocator()->get('Members');

        // Fetch all members
        $members = $membersTable->find('all');

        foreach ($members as $member) {
            // Compute warrantable status
            $member->warrantableReview();
            // Save without triggering beforeSave to avoid recursion
            $membersTable->save($member, ['checkRules' => false, 'callbacks' => false]);
        }
    }
    public function down(): void
    {
        $table = $this->table('members');
        $table->removeColumn('warrantable');
        $table->update();
    }
}
