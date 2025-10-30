<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * CreateGatheringAttendances Migration
 *
 * Creates the gathering_attendances table to track member attendance at gatherings
 * with various sharing permission options.
 */
class CreateGatheringAttendances extends BaseMigration
{
    public bool $autoId = false;

    /**
     * Create the gathering_attendances table with columns, indexes, and foreign key constraints.
     *
     * The table tracks member attendance for gatherings and includes attendance notes,
     * sharing permission flags, audit fields (created, modified, created_by, modified_by, deleted),
     * a unique constraint on (gathering_id, member_id, deleted) to allow soft-deleted records,
     * and foreign keys to gatherings and members with appropriate delete/update behaviors.
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('gathering_attendances');

        // Primary key
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
            'comment' => 'The gathering being attended'
        ]);
        $table->addColumn('member_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => false,
            'comment' => 'The member attending the gathering'
        ]);

        // Attendance information
        $table->addColumn('public_note', 'text', [
            'default' => null,
            'null' => true,
            'comment' => 'Public note the member wants to share about their attendance'
        ]);

        // Sharing permissions (boolean flags)
        $table->addColumn('share_with_kingdom', 'boolean', [
            'default' => false,
            'null' => false,
            'comment' => 'Share attendance with kingdom officers'
        ]);
        $table->addColumn('share_with_hosting_group', 'boolean', [
            'default' => false,
            'null' => false,
            'comment' => 'Share attendance with the hosting group'
        ]);
        $table->addColumn('share_with_crown', 'boolean', [
            'default' => false,
            'null' => false,
            'comment' => 'Share attendance with the crown'
        ]);
        $table->addColumn('is_public', 'boolean', [
            'default' => false,
            'null' => false,
            'comment' => 'Make attendance public (SCA name only)'
        ]);

        // Standard audit fields
        $table->addColumn("created", "datetime", [
            "default" => null,
            "limit" => null,
            "null" => false,
        ]);
        $table->addColumn("modified", "datetime", [
            "default" => null,
            "limit" => null,
            "null" => true,
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
        $table->addIndex(['gathering_id'], ['name' => 'idx_gathering_attendances_gathering']);
        $table->addIndex(['member_id'], ['name' => 'idx_gathering_attendances_member']);
        $table->addIndex(['created_by'], ['name' => 'idx_gathering_attendances_created_by']);

        // Unique constraint - a member can only have one ACTIVE attendance record per gathering
        // Includes 'deleted' field so soft-deleted records don't conflict (NULL values allow duplicates)
        $table->addIndex(['gathering_id', 'member_id', 'deleted'], [
            'name' => 'idx_gathering_attendances_unique',
            'unique' => true
        ]);

        // Foreign keys
        $table->addForeignKey('gathering_id', 'gatherings', 'id', [
            'delete' => 'CASCADE',
            'update' => 'NO_ACTION',
            'constraint' => 'fk_gathering_attendances_gathering'
        ]);
        $table->addForeignKey('member_id', 'members', 'id', [
            'delete' => 'CASCADE',
            'update' => 'NO_ACTION',
            'constraint' => 'fk_gathering_attendances_member'
        ]);
        $table->addForeignKey('created_by', 'members', 'id', [
            'delete' => 'SET_NULL',
            'update' => 'NO_ACTION',
            'constraint' => 'fk_gathering_attendances_created_by'
        ]);
        $table->addForeignKey('modified_by', 'members', 'id', [
            'delete' => 'SET_NULL',
            'update' => 'NO_ACTION',
            'constraint' => 'fk_gathering_attendances_modified_by'
        ]);

        $table->create();
    }
}
