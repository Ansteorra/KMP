<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

class RefactorAgnosticJoinFields extends AbstractMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     * @return void
     */
    public function change(): void
    {
        $this->table('notes')
            ->renameColumn('topic_model', 'entity_type')
            ->renameColumn('topic_id', 'entity_id')
            ->update();

        $this->table('member_roles')
            ->renameColumn('granting_model', 'entity_type')
            ->renameColumn('granting_id', 'entity_id')
            ->update();
    }
}
