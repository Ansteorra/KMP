<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Create HelloWorldItems table
 * 
 * This migration demonstrates the standard pattern for creating tables
 * in KMP plugins. It includes:
 * - Primary key
 * - Standard fields
 * - Timestamps
 * - Foreign keys (if needed)
 * - Indexes for performance
 * 
 * To run this migration:
 * bin/cake migrations migrate -p Template
 * 
 * To rollback:
 * bin/cake migrations rollback -p Template
 */
class CreateHelloWorldItems extends AbstractMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('hello_world_items');

        // Add columns
        $table->addColumn('title', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
            'comment' => 'Title of the hello world item',
        ]);

        $table->addColumn('description', 'text', [
            'default' => null,
            'null' => false,
            'comment' => 'Description of the item',
        ]);

        $table->addColumn('content', 'text', [
            'default' => null,
            'null' => true,
            'comment' => 'Full content of the item',
        ]);

        $table->addColumn('active', 'boolean', [
            'default' => true,
            'null' => false,
            'comment' => 'Whether the item is active',
        ]);

        // Foreign keys (uncomment if you want to link to members/branches)
        // $table->addColumn('member_id', 'integer', [
        //     'default' => null,
        //     'null' => true,
        //     'signed' => false,
        //     'comment' => 'Member who owns this item',
        // ]);

        // $table->addColumn('branch_id', 'integer', [
        //     'default' => null,
        //     'null' => true,
        //     'signed' => false,
        //     'comment' => 'Branch this item belongs to',
        // ]);

        // Timestamp fields
        $table->addColumn('created', 'datetime', [
            'default' => null,
            'null' => false,
            'comment' => 'When the record was created',
        ]);

        $table->addColumn('modified', 'datetime', [
            'default' => null,
            'null' => false,
            'comment' => 'When the record was last modified',
        ]);

        // Footprint fields (uncomment if using Footprint behavior)
        // $table->addColumn('created_by', 'integer', [
        //     'default' => null,
        //     'null' => true,
        //     'signed' => false,
        //     'comment' => 'Member who created this record',
        // ]);

        // $table->addColumn('modified_by', 'integer', [
        //     'default' => null,
        //     'null' => true,
        //     'signed' => false,
        //     'comment' => 'Member who last modified this record',
        // ]);

        // Indexes for performance
        $table->addIndex(['title'], [
            'name' => 'idx_hello_world_items_title',
        ]);

        $table->addIndex(['active'], [
            'name' => 'idx_hello_world_items_active',
        ]);

        $table->addIndex(['created'], [
            'name' => 'idx_hello_world_items_created',
        ]);

        // Foreign key constraints (uncomment if using foreign keys)
        // $table->addForeignKey(
        //     'member_id',
        //     'members',
        //     'id',
        //     [
        //         'delete' => 'SET_NULL',
        //         'update' => 'CASCADE',
        //         'constraint' => 'fk_hello_world_items_member'
        //     ]
        // );

        // $table->addForeignKey(
        //     'branch_id',
        //     'branches',
        //     'id',
        //     [
        //         'delete' => 'SET_NULL',
        //         'update' => 'CASCADE',
        //         'constraint' => 'fk_hello_world_items_branch'
        //     ]
        // );

        $table->create();
    }
}
