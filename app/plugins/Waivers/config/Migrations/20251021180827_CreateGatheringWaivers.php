<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class CreateGatheringWaivers extends BaseMigration
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
        $table = $this->table('waivers_gathering_waivers', ['id' => false]);
        $table->addColumn("id", "integer", [
            "autoIncrement" => true,
            "default" => null,
            "limit" => 11,
            "null" => false,
        ]);
        // Foreign keys
        $table->addColumn('gathering_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => false,
            'comment' => 'Gathering this waiver is for'
        ]);
        $table->addColumn('waiver_type_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => false,
            'comment' => 'Type of waiver (declared at upload time)'
        ]);
        $table->addColumn('member_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => true,
            'comment' => 'Member who signed the waiver (nullable for anonymous/unknown participants)'
        ]);
        $table->addColumn('document_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => true,
            'comment' => 'Document entity containing the actual waiver file'
        ]);

        // Retention information
        $table->addColumn('retention_date', 'date', [
            'default' => null,
            'null' => false,
            'comment' => 'Date when this waiver can be deleted (calculated at upload time)'
        ]);

        // Status
        $table->addColumn('status', 'string', [
            'default' => 'active',
            'limit' => 50,
            'null' => false,
            'comment' => 'Status: active, expired, deleted'
        ]);

        // Optional notes
        $table->addColumn('notes', 'text', [
            'default' => null,
            'null' => true,
            'comment' => 'Optional notes about this waiver'
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
        $table->addIndex(['gathering_id'], ['name' => 'idx_gathering_waivers_gathering']);
        $table->addIndex(['waiver_type_id'], ['name' => 'idx_gathering_waivers_type']);
        $table->addIndex(['member_id'], ['name' => 'idx_gathering_waivers_member']);
        $table->addIndex(['document_id'], ['name' => 'idx_gathering_waivers_document', 'unique' => true]);
        $table->addIndex(['retention_date'], ['name' => 'idx_gathering_waivers_retention']);
        $table->addIndex(['status'], ['name' => 'idx_gathering_waivers_status']);
        $table->addIndex(['created'], ['name' => 'idx_gathering_waivers_created']);
        $table->addIndex(['created_by'], ['name' => 'idx_gathering_waivers_created_by']);

        // Foreign keys
        $table->addForeignKey('gathering_id', 'gatherings', 'id', [
            'delete' => 'NO_ACTION',
            'update' => 'NO_ACTION',
            'constraint' => 'fk_gathering_waivers_gathering'
        ]);
        $table->addForeignKey('waiver_type_id', 'waivers_waiver_types', 'id', [
            'delete' => 'NO_ACTION',
            'update' => 'NO_ACTION',
            'constraint' => 'fk_gathering_waivers_type'
        ]);
        $table->addForeignKey('member_id', 'members', 'id', [
            'delete' => 'NO_ACTION',
            'update' => 'NO_ACTION',
            'constraint' => 'fk_gathering_waivers_member'
        ]);
        $table->addForeignKey('document_id', 'documents', 'id', [
            'delete' => 'NO_ACTION',
            'update' => 'NO_ACTION',
            'constraint' => 'fk_gathering_waivers_document'
        ]);
        $table->addForeignKey('created_by', 'members', 'id', [
            'delete' => 'NO_ACTION',
            'update' => 'NO_ACTION',
            'constraint' => 'fk_gathering_waivers_created_by'
        ]);
        $table->addForeignKey('modified_by', 'members', 'id', [
            'delete' => 'NO_ACTION',
            'update' => 'NO_ACTION',
            'constraint' => 'fk_gathering_waivers_modified_by'
        ]);

        $table->create();
    }
}