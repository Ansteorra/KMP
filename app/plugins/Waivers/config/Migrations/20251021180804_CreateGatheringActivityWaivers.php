<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class CreateGatheringActivityWaivers extends BaseMigration
{
    public bool $autoId = false;
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/4/en/migrations.html#the-change-method
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('waivers_gathering_activity_waivers', ['id' => false]);
        $table->addColumn("id", "integer", [
            "autoIncrement" => true,
            "default" => null,
            "limit" => 11,
            "null" => false,
        ]);
        // Foreign keys
        $table->addColumn('gathering_activity_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => false,
            'comment' => 'Gathering activity this waiver requirement applies to'
        ]);
        $table->addColumn('waiver_type_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => false,
            'comment' => 'Type of waiver required for this activity'
        ]);

        $table->addColumn("modified", "datetime", [
            "default" => null,
            "limit" => null,
            "null" => true,
        ]);
        $table->addColumn("created", "datetime", [
            "default" => null,
            "limit" => null,
            "null" => false,
        ]);
        $table->addColumn("created_by", "integer", [
            "default" => null,
            "limit" => null,
            "null" => true,
        ]);
        $table->addColumn("modified_by", "integer", [
            "default" => null,
            "limit" => null,
            "null" => true,
        ]);
        $table->addColumn("deleted", "datetime", [
            "default" => null,
            "limit" => null,
            "null" => true,
        ]);

        // Indexes
        $table->addPrimaryKey(["id"]);
        $table->addIndex(['gathering_activity_id'], ['name' => 'idx_gathering_activity_waivers_activity']);
        $table->addIndex(['waiver_type_id'], ['name' => 'idx_gathering_activity_waivers_type']);
        $table->addIndex(['gathering_activity_id', 'waiver_type_id'], [
            'name' => 'idx_gathering_activity_waivers_unique',
            'unique' => true
        ]);

        // Foreign keys
        $table->addForeignKey('gathering_activity_id', 'gathering_activities', 'id', [
            'delete' => 'NO_ACTION',
            'update' => 'NO_ACTION',
            'constraint' => 'fk_gathering_activity_waivers_activity'
        ]);
        $table->addForeignKey('waiver_type_id', 'waivers_waiver_types', 'id', [
            'delete' => 'NO_ACTION',
            'update' => 'NO_ACTION',
            'constraint' => 'fk_gathering_activity_waivers_type'
        ]);

        $table->create();
    }
}