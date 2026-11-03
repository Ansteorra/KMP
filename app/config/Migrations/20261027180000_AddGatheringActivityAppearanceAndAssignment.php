<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddGatheringActivityAppearanceAndAssignment extends BaseMigration
{
    /** @return void */
    public function change(): void
    {
        $this->table('gathering_activities')
            ->addColumn('icon_source', 'string', ['limit' => 20, 'default' => 'bootstrap', 'null' => false])
            ->addColumn('icon_name', 'string', ['limit' => 100, 'default' => 'bi-activity', 'null' => false])
            ->addColumn('icon_document_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('foreground_color', 'string', ['limit' => 7, 'default' => '#212529', 'null' => false])
            ->addColumn('background_color', 'string', ['limit' => 7, 'default' => '#e9ecef', 'null' => false])
            ->addIndex(['icon_document_id'])
            ->addForeignKey('icon_document_id', 'documents', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->update();

        $this->table('gathering_activity_assignment_permissions')
            ->addColumn('gathering_activity_id', 'integer', ['null' => false])
            ->addColumn('permission_id', 'integer', ['null' => false])
            ->addColumn('created', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('modified', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('created_by', 'integer', ['null' => true, 'default' => null])
            ->addColumn('modified_by', 'integer', ['null' => true, 'default' => null])
            ->addIndex(
                ['gathering_activity_id', 'permission_id'],
                ['unique' => true, 'name' => 'activity_assignment_unique'],
            )
            ->addIndex(['permission_id'])
            ->addForeignKey('gathering_activity_id', 'gathering_activities', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('permission_id', 'permissions', 'id', ['delete' => 'RESTRICT'])
            ->create();
    }
}
