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
 * ## Migration Process
 * 
 * This migration automatically:
 * 1. Adds public_id column to tables
 * 2. Generates public IDs for all existing records
 * 3. Makes the column NOT NULL after population
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
     * Length of generated public IDs
     */
    protected const PUBLIC_ID_LENGTH = 8;

    /**
     * Character set for public IDs (excludes visually similar characters)
     */
    protected const CHARSET = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';

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
                continue;
            }

            $table = $this->table($tableName);

            // Skip if column already exists
            if ($table->hasColumn('public_id')) {
                continue;
            }

            // Add public_id column as nullable initially
            $table->addColumn('public_id', 'string', [
                'limit' => 8,
                'null' => true,
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

            // Generate public IDs for existing records
            $this->_generatePublicIds($tableName);

            // Now make the column NOT NULL since all records have values
            $table->changeColumn('public_id', 'string', [
                'limit' => 8,
                'null' => false,
                'comment' => 'Non-sequential public identifier safe for client exposure',
            ]);

            $table->update();
        }
    }

    /**
     * Generate public IDs for all existing records in a table
     *
     * @param string $tableName Table name
     * @return void
     */
    protected function _generatePublicIds(string $tableName): void
    {
        $adapter = $this->getAdapter();

        // Get all records without public_id
        $rows = $this->fetchAll(sprintf(
            "SELECT id FROM %s WHERE public_id IS NULL OR public_id = ''",
            $tableName
        ));

        if (empty($rows)) {
            return;
        }

        $charsetLength = strlen(self::CHARSET);

        foreach ($rows as $row) {
            $publicId = $this->_generateUniquePublicId($tableName, $charsetLength);

            $this->execute(sprintf(
                "UPDATE %s SET public_id = '%s' WHERE id = %d",
                $tableName,
                $publicId,
                $row['id']
            ));
        }
    }

    /**
     * Generate a unique public ID for a table
     *
     * @param string $tableName Table name
     * @param int $charsetLength Length of character set
     * @return string Generated unique public ID
     */
    protected function _generateUniquePublicId(string $tableName, int $charsetLength): string
    {
        $maxAttempts = 10;
        $attempt = 0;

        do {
            $publicId = '';
            for ($i = 0; $i < self::PUBLIC_ID_LENGTH; $i++) {
                $publicId .= self::CHARSET[random_int(0, $charsetLength - 1)];
            }

            $exists = $this->fetchRow(sprintf(
                "SELECT id FROM %s WHERE public_id = '%s'",
                $tableName,
                $publicId
            ));

            $attempt++;

            if ($attempt >= $maxAttempts) {
                throw new \RuntimeException(sprintf(
                    'Failed to generate unique public ID for %s after %d attempts',
                    $tableName,
                    $maxAttempts
                ));
            }
        } while ($exists);

        return $publicId;
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
        }
    }
}