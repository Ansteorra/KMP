<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class CreateGatheringWaiverClosures extends BaseMigration
{
    public bool $autoId = false;

    /**
     * Change Method.
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('waivers_gathering_waiver_closures');
        $table->addColumn('id', 'integer', [
            'autoIncrement' => true,
            'default' => null,
            'limit' => 11,
            'null' => false,
        ]);
        $table->addColumn('gathering_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => false,
            'comment' => 'Gathering with waivers closed to new uploads',
        ]);
        $table->addColumn('closed_at', 'datetime', [
            'default' => null,
            'null' => false,
            'comment' => 'Timestamp when waiver collection was closed',
        ]);
        $table->addColumn('closed_by', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => false,
            'comment' => 'Member who closed waiver collection',
        ]);
        $table->addColumn('modified', 'datetime', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('created', 'datetime', [
            'default' => null,
            'null' => true,
        ]);

        $table->addIndex(['gathering_id'], [
            'name' => 'idx_gathering_waiver_closures_gathering',
            'unique' => true,
        ]);
        $table->addIndex(['closed_by'], [
            'name' => 'idx_gathering_waiver_closures_closed_by',
        ]);
        $table->addIndex(['closed_at'], [
            'name' => 'idx_gathering_waiver_closures_closed_at',
        ]);

        $table->addPrimaryKey(['id']);

        $table->addForeignKey('gathering_id', 'gatherings', 'id', [
            'delete' => 'CASCADE',
            'update' => 'NO_ACTION',
            'constraint' => 'fk_gathering_waiver_closures_gathering',
        ]);
        $table->addForeignKey('closed_by', 'members', 'id', [
            'delete' => 'RESTRICT',
            'update' => 'NO_ACTION',
            'constraint' => 'fk_gathering_waiver_closures_closed_by',
        ]);

        $table->create();
    }
}
