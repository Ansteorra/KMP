<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateGatheringWaiverLosses extends BaseMigration
{
    /** Create immutable loss reports without changing existing waiver records. */
    public function change(): void
    {
        $this->table('waivers_gathering_waiver_losses')
            ->addColumn('gathering_id', 'integer', ['null' => false])
            ->addColumn('waiver_type_id', 'integer', ['null' => false])
            ->addColumn('notes', 'text', ['null' => false])
            ->addColumn('created_by', 'integer', ['null' => false])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addColumn('modified', 'datetime', ['null' => false])
            ->addIndex(['gathering_id', 'waiver_type_id'], ['unique' => true, 'name' => 'uq_waiver_loss_type'])
            ->addIndex(['waiver_type_id'])
            ->addIndex(['created_by'])
            ->addForeignKey('gathering_id', 'gatherings', 'id', ['delete' => 'RESTRICT'])
            ->addForeignKey('waiver_type_id', 'waivers_waiver_types', 'id', ['delete' => 'RESTRICT'])
            ->addForeignKey('created_by', 'members', 'id', ['delete' => 'RESTRICT'])
            ->create();
    }
}
