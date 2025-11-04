<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * CreateGatheringStaff Migration
 *
 * Creates the gathering_staff table to track stewards and other staff for gatherings.
 * Stewards must have contact info (email or phone) and are linked to AMP member accounts.
 * Other staff can be AMP members or generic SCA names with optional contact info.
 */
class CreateGatheringStaff extends BaseMigration
{
    public bool $autoId = false;

    /**
     * Change Method.
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('gathering_staff');

        // Primary key
        $table->addColumn('id', 'integer', [
            'autoIncrement' => true,
            'default' => null,
            'limit' => 11,
            'null' => false,
        ]);

        // Foreign key to gatherings
        $table->addColumn('gathering_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => false,
            'comment' => 'The gathering this staff member is associated with'
        ]);

        // Foreign key to members (nullable for non-AMP staff)
        $table->addColumn('member_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => true,
            'comment' => 'AMP member account (null for non-AMP staff)'
        ]);

        // SCA name for non-AMP staff
        $table->addColumn('sca_name', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
            'comment' => 'SCA name for non-AMP staff members'
        ]);

        // Role information
        $table->addColumn('role', 'string', [
            'default' => null,
            'limit' => 100,
            'null' => false,
            'comment' => 'Role name (e.g., "Steward", "Herald", "List Master")'
        ]);

        $table->addColumn('is_steward', 'boolean', [
            'default' => false,
            'null' => false,
            'comment' => 'Whether this staff member is a steward'
        ]);

        // Contact information (copied from member for stewards, editable)
        $table->addColumn('email', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
            'comment' => 'Contact email (copied from member for stewards, editable)'
        ]);

        $table->addColumn('phone', 'string', [
            'default' => null,
            'limit' => 50,
            'null' => true,
            'comment' => 'Contact phone (copied from member for stewards, editable)'
        ]);

        $table->addColumn('contact_notes', 'text', [
            'default' => null,
            'null' => true,
            'comment' => 'Contact preferences (e.g., "text only", "no calls after 9pm")'
        ]);

        // Sort order for display
        $table->addColumn('sort_order', 'integer', [
            'default' => 0,
            'limit' => 11,
            'null' => false,
            'comment' => 'Display order (stewards first, then others)'
        ]);

        // Standard audit fields
        $table->addColumn('created', 'datetime', [
            'default' => null,
            'null' => false,
        ]);

        $table->addColumn('modified', 'datetime', [
            'default' => null,
            'null' => true,
        ]);

        $table->addColumn('created_by', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => true,
        ]);

        $table->addColumn('modified_by', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => true,
        ]);

        $table->addColumn('deleted', 'datetime', [
            'default' => null,
            'null' => true,
        ]);

        // Primary key
        $table->addPrimaryKey(['id']);

        // Indexes
        $table->addIndex(['gathering_id']);
        $table->addIndex(['member_id']);
        $table->addIndex(['is_steward']);
        $table->addIndex(['sort_order']);
        $table->addIndex(['deleted']);

        // Foreign key constraints
        $table->addForeignKey(
            'gathering_id',
            'gatherings',
            'id',
            [
                'update' => 'NO_ACTION',
                'delete' => 'CASCADE'
            ]
        );

        $table->addForeignKey(
            'member_id',
            'members',
            'id',
            [
                'update' => 'NO_ACTION',
                'delete' => 'NO_ACTION'
            ]
        );

        $table->create();
    }
}
