<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Add public_id columns to Members and Gatherings tables
 * 
 * This migration adds non-sequential public identifiers to the members and gatherings
 * tables to prevent exposing internal database IDs in the gathering staff feature.
 * 
 * ## Why Public IDs?
 * 
 * Exposing internal database IDs to clients is a security anti-pattern:
 * - Sequential IDs leak information (record count, creation order, deletion patterns)
 * - Enable enumeration attacks (iterate through all IDs)
 * - May violate privacy by revealing usage patterns
 * 
 * Public IDs provide:
 * - Non-sequential, unpredictable identifiers safe for client exposure
 * - No information leakage about database state
 * - Prevention of enumeration attacks
 * - Same performance as regular IDs (indexed)
 * 
 * ## Tables Affected (Initial Implementation)
 * 
 * - members (user accounts referenced in gathering staff)
 * - gatherings (events that have staff)
 * 
 * Other tables will be added in future migrations as features require them.
 * 
 * ## After Migration
 * 
 * Run: bin/cake generate_public_ids members gatherings
 * 
 * This will populate public_id values for all existing records.
 */
class AddPublicIdToMembersAndGatherings extends AbstractMigration
{
    /**
     * Tables that need public IDs for the gathering staff feature
     */
    protected const TABLES = [
        'members',
        'gatherings',
    ];

    /**
     * Add public_id column to members and gatherings tables
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
