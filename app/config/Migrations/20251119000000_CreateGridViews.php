<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Create grid_views table for storing user-configurable grid/table views
 * 
 * This migration creates infrastructure for a Dataverse-style grid view system
 * that allows users to save, manage, and share custom views of data grids across
 * the application. Each view can define filters, sorting, visible columns, and
 * pagination preferences.
 * 
 * ## Purpose
 * 
 * Enable users to:
 * - Save custom filtered and sorted views of data grids
 * - Configure which columns are visible and their order
 * - Set default views for specific grids
 * - Share views system-wide (via system defaults)
 * - Maintain consistent grid preferences across sessions
 * 
 * ## Architecture
 * 
 * - **grid_key**: Unique identifier for each grid instance (e.g., 'Members.index.main')
 * - **member_id**: NULL for system-wide defaults, otherwise owner of the view
 * - **is_system_default**: One system default per grid_key (member_id = NULL)
 * - **is_default**: User's preferred default view (one per member + grid_key)
 * - **config**: JSON containing filters, sort, columns, and pageSize
 * 
 * ## View Resolution Priority
 * 
 * 1. Explicitly requested view (via viewId)
 * 2. User's default view (is_default = true for that member)
 * 3. System default (is_system_default = true, member_id = NULL)
 * 4. Hardcoded application fallback
 * 
 * ## Config JSON Structure
 * 
 * ```json
 * {
 *   "filters": [
 *     {"field": "status", "operator": "eq", "value": "active"},
 *     {"field": "created", "operator": "gte", "value": "2024-01-01"}
 *   ],
 *   "sort": [
 *     {"field": "last_name", "direction": "asc"},
 *     {"field": "first_name", "direction": "asc"}
 *   ],
 *   "columns": [
 *     {"key": "id", "visible": true},
 *     {"key": "sca_name", "visible": true},
 *     {"key": "email_address", "visible": true},
 *     {"key": "branch_id", "visible": false}
 *   ],
 *   "pageSize": 50
 * }
 * ```
 */
class CreateGridViews extends AbstractMigration
{
    /**
     * Create the grid_views table
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('grid_views');

        $table->addColumn('grid_key', 'string', [
            'limit' => 100,
            'null' => false,
            'comment' => 'Unique identifier for grid instance (e.g., Members.index.main)',
        ])
            ->addColumn('member_id', 'integer', [
                'null' => true,
                'default' => null,
                'comment' => 'Owner of view; NULL for system-wide defaults',
            ])
            ->addColumn('name', 'string', [
                'limit' => 100,
                'null' => false,
                'comment' => 'User-friendly name for the view',
            ])
            ->addColumn('is_default', 'boolean', [
                'null' => false,
                'default' => false,
                'comment' => 'Whether this is the user\'s default view for this grid',
            ])
            ->addColumn('is_system_default', 'boolean', [
                'null' => false,
                'default' => false,
                'comment' => 'Whether this is the system-wide default (member_id must be NULL)',
            ])
            ->addColumn('config', 'text', [
                'null' => false,
                'comment' => 'JSON configuration: filters, sort, columns, pageSize',
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
                'comment' => 'Member who created this view',
            ])
            ->addColumn('modified_by', 'integer', [
                'null' => true,
                'default' => null,
                'comment' => 'Member who last modified this view',
            ])
            ->addColumn('deleted', 'datetime', [
                'null' => true,
                'default' => null,
                'comment' => 'Soft delete timestamp',
            ]);

        // Foreign key to members for owner
        $table->addForeignKey('member_id', 'members', 'id', [
            'delete' => 'CASCADE',
            'update' => 'CASCADE',
        ]);

        // Foreign key to members for created_by
        $table->addForeignKey('created_by', 'members', 'id', [
            'delete' => 'SET_NULL',
            'update' => 'CASCADE',
        ]);

        // Foreign key to members for modified_by
        $table->addForeignKey('modified_by', 'members', 'id', [
            'delete' => 'SET_NULL',
            'update' => 'CASCADE',
        ]);

        // Index for fast lookups by grid_key
        $table->addIndex(['grid_key'], [
            'name' => 'idx_grid_views_grid_key',
        ]);

        // Index for finding user's views
        $table->addIndex(['member_id', 'grid_key'], [
            'name' => 'idx_grid_views_member_grid',
        ]);

        // Index for finding default views
        $table->addIndex(['grid_key', 'is_default'], [
            'name' => 'idx_grid_views_grid_default',
        ]);

        // Index for finding system defaults
        $table->addIndex(['grid_key', 'is_system_default'], [
            'name' => 'idx_grid_views_system_default',
        ]);

        // Composite index for soft delete queries
        $table->addIndex(['deleted'], [
            'name' => 'idx_grid_views_deleted',
        ]);

        $table->create();
    }
}
