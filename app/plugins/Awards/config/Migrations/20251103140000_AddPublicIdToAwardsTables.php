<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Add public_id to Awards plugin tables
 * 
 * This migration adds public IDs to all Awards plugin tables that represent
 * entities that may be referenced from client-side code.
 * 
 * Tables affected:
 * - awards
 * - recommendations
 * 
 * After migration, run:
 * bin/cake generate_public_ids awards recommendations
 */
class AddPublicIdToAwardsTables extends AbstractMigration
{
    /**
     * Awards plugin tables that need public IDs
     */
    protected const TABLES = [
        'awards',
        'recommendations',
    ];

    /**
     * Add public_id column to all Awards plugin tables
     *
     * @return void
     */
    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            // Check if table exists before trying to modify it
            if (!$this->hasTable($tableName)) {
                $this->io()->warning(sprintf('Table %s does not exist, skipping', $tableName));
                continue;
            }

            $table = $this->table($tableName);

            // Skip if column already exists
            if ($table->hasColumn('public_id')) {
                $this->io()->warning(sprintf('Table %s already has public_id column, skipping', $tableName));
                continue;
            }

            // Add public_id column
            $table->addColumn('public_id', 'string', [
                'limit' => 8,
                'null' => true, // Initially null, will be populated then made NOT NULL
                'default' => null,
                'after' => 'id',
                'comment' => 'Non-sequential public identifier safe for client exposure',
            ]);

            // Add unique index for fast lookups
            $table->addIndex(['public_id'], [
                'unique' => true,
                'name' => sprintf('idx_%s_public_id', $tableName),
            ]);

            $table->update();

            $this->io()->success(sprintf('Added public_id to %s', $tableName));
        }
    }

    /**
     * Remove public_id column from all tables
     *
     * @return void
     */
    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            if (!$this->hasTable($tableName)) {
                continue;
            }

            $table = $this->table($tableName);

            if (!$table->hasColumn('public_id')) {
                continue;
            }

            // Remove index first
            $table->removeIndexByName(sprintf('idx_%s_public_id', $tableName));

            // Remove column
            $table->removeColumn('public_id');

            $table->update();

            $this->io()->success(sprintf('Removed public_id from %s', $tableName));
        }
    }
}
