<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class CreateDocuments extends BaseMigration
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
        $table = $this->table('documents');
        $table->addColumn("id", "integer", [
            "autoIncrement" => true,
            "default" => null,
            "limit" => 11,
            "null" => false,
        ]);
        // Polymorphic relationship fields
        $table->addColumn('entity_type', 'string', [
            'default' => null,
            'limit' => 100,
            'null' => false,
            'comment' => 'Polymorphic entity type (e.g., Waivers.GatheringWaivers, Members)'
        ]);
        $table->addColumn('entity_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => false,
            'comment' => 'Polymorphic entity ID'
        ]);

        // File information
        $table->addColumn('original_filename', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
            'comment' => 'Original filename from upload'
        ]);
        $table->addColumn('stored_filename', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
            'comment' => 'Sanitized filename for storage'
        ]);
        $table->addColumn('file_path', 'string', [
            'default' => null,
            'limit' => 500,
            'null' => false,
            'comment' => 'Full path to file in storage'
        ]);
        $table->addColumn('mime_type', 'string', [
            'default' => null,
            'limit' => 100,
            'null' => false,
            'comment' => 'File MIME type'
        ]);
        $table->addColumn('file_size', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => false,
            'comment' => 'File size in bytes'
        ]);
        $table->addColumn('checksum', 'string', [
            'default' => null,
            'limit' => 64,
            'null' => false,
            'comment' => 'SHA-256 checksum for integrity verification'
        ]);

        // Storage configuration
        $table->addColumn('storage_adapter', 'string', [
            'default' => 'local',
            'limit' => 50,
            'null' => false,
            'comment' => 'Storage adapter used (local, s3, etc.)'
        ]);
        $table->addColumn('metadata', 'text', [
            'default' => null,
            'null' => true,
            'comment' => 'JSON metadata (conversion info, etc.)'
        ]);

        // Who uploaded this document
        $table->addColumn('uploaded_by', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => false,
            'comment' => 'Member ID who uploaded the document'
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
        $table->addIndex(['entity_type', 'entity_id'], ['name' => 'idx_documents_entity']);
        $table->addIndex(['file_path'], ['name' => 'idx_documents_file_path', 'unique' => true]);
        $table->addIndex(['checksum'], ['name' => 'idx_documents_checksum']);
        $table->addIndex(['uploaded_by'], ['name' => 'idx_documents_uploaded_by']);
        $table->addIndex(['created'], ['name' => 'idx_documents_created']);

        // Foreign keys
        $table->addForeignKey('uploaded_by', 'members', 'id', [
            'delete' => 'NO_ACTION',
            'update' => 'NO_ACTION',
            'constraint' => 'fk_documents_uploaded_by'
        ]);
        $table->addForeignKey('created_by', 'members', 'id', [
            'delete' => 'NO_ACTION',
            'update' => 'NO_ACTION',
            'constraint' => 'fk_documents_created_by'
        ]);
        $table->addForeignKey('modified_by', 'members', 'id', [
            'delete' => 'NO_ACTION',
            'update' => 'NO_ACTION',
            'constraint' => 'fk_documents_modified_by'
        ]);

        $table->create();
    }
}