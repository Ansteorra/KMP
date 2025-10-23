<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class CreateGatherings extends BaseMigration
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
        $table = $this->table('gatherings');
        $table->addColumn("id", "integer", [
            "autoIncrement" => true,
            "default" => null,
            "limit" => 11,
            "null" => false,
        ]);
        // Foreign keys
        $table->addColumn('branch_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => false,
            'comment' => 'Branch hosting this gathering'
        ]);
        $table->addColumn('gathering_type_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => false,
            'comment' => 'Type of gathering'
        ]);

        // Basic information
        $table->addColumn('name', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
            'comment' => 'Name of the gathering'
        ]);
        $table->addColumn('description', 'text', [
            'default' => null,
            'null' => true,
            'comment' => 'Description of the gathering'
        ]);

        // Date and location
        $table->addColumn('start_date', 'date', [
            'default' => null,
            'null' => false,
            'comment' => 'Start date of the gathering'
        ]);
        $table->addColumn('end_date', 'date', [
            'default' => null,
            'null' => false,
            'comment' => 'End date of the gathering'
        ]);
        $table->addColumn('location', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
            'comment' => 'Location of the gathering'
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
        $table->addIndex(['branch_id'], ['name' => 'idx_gatherings_branch']);
        $table->addIndex(['gathering_type_id'], ['name' => 'idx_gatherings_type']);
        $table->addIndex(['created_by'], ['name' => 'idx_gatherings_created_by']);
        $table->addIndex(['start_date'], ['name' => 'idx_gatherings_start_date']);
        $table->addIndex(['end_date'], ['name' => 'idx_gatherings_end_date']);

        // Foreign keys
        $table->addForeignKey('branch_id', 'branches', 'id', [
            'delete' => 'NO_ACTION',
            'update' => 'NO_ACTION',
            'constraint' => 'fk_gatherings_branch'
        ]);
        $table->addForeignKey('gathering_type_id', 'gathering_types', 'id', [
            'delete' => 'NO_ACTION',
            'update' => 'NO_ACTION',
            'constraint' => 'fk_gatherings_type'
        ]);
        $table->addForeignKey('created_by', 'members', 'id', [
            'delete' => 'NO_ACTION',
            'update' => 'NO_ACTION',
            'constraint' => 'fk_gatherings_created_by'
        ]);

        $table->create();
    }
}