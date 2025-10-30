<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * CreateDocuments Migration
 * 
 * Creates the documents table for storing file metadata and polymorphic associations.
 * 
 * IMPORTANT AUDIT FIELD SEMANTICS:
 * - uploaded_by: NULLABLE - The member who physically uploaded the file. Set to NULL if
 *   the member is deleted (via SET_NULL foreign key). This allows member deletion without
 *   cascading to documents while preserving upload history where possible.
 * - created_by: NULLABLE - The member who created the database record. May be NULL if
 *   the record was created by a system process (e.g., automated migrations, imports).
 * - modified_by: NULLABLE - The member who last modified the record. May be NULL if
 *   modified by a system process.
 * 
 * All audit fields use SET_NULL on member deletion to preserve document records while
 * allowing member management flexibility.
 */
class CreateDocuments extends BaseMigration
{
    public bool $autoId = false;
    /**
     * Create the `documents` table including its columns, indexes, and foreign keys.
     *
     * Defines a schema for storing file metadata and polymorphic associations:
     * - Primary key `id`.
     * - Polymorphic fields `entity_type` and `entity_id`.
     * - File metadata: `original_filename`, `stored_filename`, `file_path`, `mime_type`, `file_size`, `checksum`.
     * - Storage and metadata: `storage_adapter`, `metadata`.
     * - Uploader and audit fields: `uploaded_by`, `created`, `modified`, `created_by`, `modified_by`, `deleted`.
     * - Indexes (composite index on entity, unique on `file_path`, and additional indexes on `checksum`, `uploaded_by`, `created`).
     * - Foreign key constraints to `members.id` for `uploaded_by`, `created_by`, and `modified_by` with named constraints and NO_ACTION on update/delete.
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

        // Audit fields - see class docblock for important semantic distinctions
        // uploaded_by: NULLABLE - Physical file uploader (NULL if member deleted)
        $table->addColumn('uploaded_by', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => true,
            'comment' => 'Member ID who uploaded the file (nullable - NULL if member deleted)'
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
        // created_by: NULLABLE - Database record creator (may be NULL for system processes)
        $table->addColumn("created_by", "integer", [
            "default" => null,
            "limit" => null,
            "null" => true,
            'comment' => 'Member ID who created the record (nullable - may be system process)'
        ]);
        // modified_by: NULLABLE - Database record modifier (may be NULL for system processes)
        $table->addColumn("modified_by", "integer", [
            "default" => null,
            "limit" => null,
            "null" => true,
            'comment' => 'Member ID who last modified the record (nullable - may be system process)'
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

        // Foreign keys - all use SET_NULL to allow member deletion without cascading to documents
        $table->addForeignKey('uploaded_by', 'members', 'id', [
            'delete' => 'SET_NULL',
            'update' => 'NO_ACTION',
            'constraint' => 'fk_documents_uploaded_by'
        ]);
        $table->addForeignKey('created_by', 'members', 'id', [
            'delete' => 'SET_NULL',
            'update' => 'NO_ACTION',
            'constraint' => 'fk_documents_created_by'
        ]);
        $table->addForeignKey('modified_by', 'members', 'id', [
            'delete' => 'SET_NULL',
            'update' => 'NO_ACTION',
            'constraint' => 'fk_documents_modified_by'
        ]);

        $table->create();
    }
}
