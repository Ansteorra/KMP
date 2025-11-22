<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Create grid_view_preferences table to store per-user default selections
 *
 * The existing grid view infrastructure keeps `is_default` flags on individual
 * `grid_views` rows for user-specific defaults. This worked while defaults were
 * restricted to user-owned custom views, but it prevents marking system views as
 * personal defaults without mutating the system definition. This migration
 * introduces a separate preferences table so defaults become lightweight
 * pointers while leaving system views immutable.
 *
 * ## Design
 * - One row per member + grid key combination
 * - `grid_view_id` references either a system or user-defined view
 * - Unique constraint ensures a single active preference per grid per member
 * - Standard timestamp/audit columns maintain parity with other tables
 */
class CreateGridViewPreferences extends AbstractMigration
{
    /**
     * Create the grid_view_preferences table
     */
    public function change(): void
    {
        $table = $this->table('grid_view_preferences');

        $table
            ->addColumn('member_id', 'integer', [
                'null' => false,
                'comment' => 'Member owning the preference record',
            ])
            ->addColumn('grid_key', 'string', [
                'limit' => 100,
                'null' => false,
                'comment' => 'Unique identifier for the grid instance (e.g., Members.index.main)',
            ])
            ->addColumn('grid_view_id', 'integer', [
                'null' => true,
                'default' => null,
                'comment' => 'Preferred view ID; supports user views',
            ])
            ->addColumn('grid_view_key', 'string', [
                'limit' => 100,
                'null' => true,
                'default' => null,
                'comment' => 'Preferred system view key (string); supports system views by name',
            ])
            ->addColumn('created', 'datetime', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
            ])
            ->addColumn('modified', 'datetime', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
            ])
            ->addColumn('created_by', 'integer', [
                'null' => true,
                'default' => null,
                'comment' => 'Audit: member who created the record',
            ])
            ->addColumn('modified_by', 'integer', [
                'null' => true,
                'default' => null,
                'comment' => 'Audit: member who last modified the record',
            ]);

        // Foreign key to members (owner)
        $table->addForeignKey('member_id', 'members', 'id', [
            'delete' => 'CASCADE',
            'update' => 'CASCADE',
        ]);

        // Foreign key to members for created_by/modified_by audit columns
        $table->addForeignKey('created_by', 'members', 'id', [
            'delete' => 'SET_NULL',
            'update' => 'CASCADE',
        ]);

        $table->addForeignKey('modified_by', 'members', 'id', [
            'delete' => 'SET_NULL',
            'update' => 'CASCADE',
        ]);

        // Foreign key to grid_views (nullable to allow future fallbacks)
        $table->addForeignKey('grid_view_id', 'grid_views', 'id', [
            'delete' => 'CASCADE',
            'update' => 'CASCADE',
        ]);

        // Uniqueness per member/grid
        $table->addIndex(['member_id', 'grid_key'], [
            'name' => 'uq_grid_view_preferences_member_grid',
            'unique' => true,
        ]);

        // Index for resolving preferences by view
        $table->addIndex(['grid_view_id'], [
            'name' => 'idx_grid_view_preferences_view',
        ]);

        // Index for grid_key lookups (reporting/debugging)
        $table->addIndex(['grid_key'], [
            'name' => 'idx_grid_view_preferences_grid',
        ]);

        $table->create();
    }
}
