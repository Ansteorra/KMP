<?php

declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Datasource\ConnectionManager;

/**
 * GeneratePublicIds Command
 * 
 * Generates public IDs for existing records in tables that have the public_id column
 * but haven't had IDs generated yet.
 * 
 * Usage:
 *   bin/cake generate_public_ids members
 *   bin/cake generate_public_ids --all
 */
class GeneratePublicIdsCommand extends Command
{
    /**
     * Hook method for defining this command's option parser.
     *
     * @param \Cake\Console\ConsoleOptionParser $parser The parser to be defined
     * @return \Cake\Console\ConsoleOptionParser
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('Generate public IDs for existing records')
            ->addArgument('tables', [
                'help' => 'Table name(s) (e.g., members, gatherings). Can specify multiple tables separated by spaces.',
                'required' => false,
                'multiple' => true,
            ])
            ->addOption('all', [
                'short' => 'a',
                'help' => 'Process all tables with public_id column',
                'boolean' => true,
            ])
            ->addOption('field', [
                'short' => 'f',
                'help' => 'Public ID field name',
                'default' => 'public_id',
            ])
            ->addOption('length', [
                'short' => 'l',
                'help' => 'Length of public ID',
                'default' => '8',
            ])
            ->addOption('dry-run', [
                'short' => 'd',
                'help' => 'Show what would be done without making changes',
                'boolean' => true,
            ]);

        return $parser;
    }

    /**
     * Execute command
     *
     * @param \Cake\Console\Arguments $args Arguments
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return int|null Exit code
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $tableNames = $args->getArgument('tables');
        $processAll = $args->getOption('all');
        $field = $args->getOption('field');
        $length = (int)$args->getOption('length');
        $dryRun = $args->getOption('dry-run');

        if (!$tableNames && !$processAll) {
            $io->error('Either provide table name(s) or use --all flag');
            $io->out('');
            $io->out('Examples:');
            $io->out('  bin/cake generate_public_ids members');
            $io->out('  bin/cake generate_public_ids members branches gatherings');
            $io->out('  bin/cake generate_public_ids --all');
            return static::CODE_ERROR;
        }

        if ($dryRun) {
            $io->warning('DRY RUN MODE - No changes will be made');
        }

        $connection = ConnectionManager::get('default');

        // Determine which tables to process
        if ($processAll) {
            $tables = $this->_findTablesWithPublicId($connection, $field);
        } else {
            // Tables will be an array from the argument parser
            $tables = $tableNames ?: [];

            // Verify each table exists and has public_id column
            $schemaCollection = $connection->getSchemaCollection();
            foreach ($tables as $table) {
                if (!in_array($table, $schemaCollection->listTables())) {
                    $io->error(sprintf('Table "%s" does not exist', $table));
                    return static::CODE_ERROR;
                }

                $schema = $schemaCollection->describe($table);
                if (!$schema->hasColumn($field)) {
                    $io->error(sprintf('Table "%s" does not have a "%s" column', $table, $field));
                    return static::CODE_ERROR;
                }
            }
        }

        $totalProcessed = 0;
        foreach ($tables as $table) {
            $io->out(sprintf('<info>Processing table:</info> %s', $table));
            $processed = $this->_generatePublicIdsForTable($connection, $table, $field, $length, $dryRun, $io);
            $io->success(sprintf('Processed %d records in %s', $processed, $table));
            $totalProcessed += $processed;
        }

        $io->out('');
        $io->success(sprintf('Total: Processed %d records across %d table(s)', $totalProcessed, count($tables)));

        return static::CODE_SUCCESS;
    }

    /**
     * Find all tables that have a public_id column
     *
     * @param \Cake\Database\Connection $connection Database connection
     * @param string $field Field name
     * @return array<string> Table names
     */
    protected function _findTablesWithPublicId($connection, string $field): array
    {
        $schemaCollection = $connection->getSchemaCollection();
        $tables = $schemaCollection->listTables();
        $result = [];

        foreach ($tables as $table) {
            $schema = $schemaCollection->describe($table);
            if ($schema->hasColumn($field)) {
                $result[] = $table;
            }
        }

        return $result;
    }

    /**
     * Generate public IDs for a specific table
     *
     * @param \Cake\Database\Connection $connection Database connection
     * @param string $table Table name
     * @param string $field Field name
     * @param int $length ID length
     * @param bool $dryRun Dry run flag
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return int Number of records processed
     */
    protected function _generatePublicIdsForTable(
        $connection,
        string $table,
        string $field,
        int $length,
        bool $dryRun,
        ConsoleIo $io
    ): int {
        // Find records without public_id
        $query = $connection->selectQuery()
            ->select(['id'])
            ->from($table)
            ->where(function ($exp) use ($field) {
                return $exp->or([
                    $field . ' IS NULL',
                    $field . ' = ""',
                ]);
            });

        $records = $query->execute()->fetchAll('assoc');
        $count = count($records);

        if ($count === 0) {
            $io->out('  No records need public IDs');
            return 0;
        }

        $io->out(sprintf('  Found %d records without public IDs', $count));

        if ($dryRun) {
            return $count;
        }

        $charset = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $charsetLength = strlen($charset);
        $processed = 0;

        foreach ($records as $record) {
            $publicId = $this->_generateUniquePublicId($connection, $table, $field, $length, $charset, $charsetLength);

            $connection->update($table, [$field => $publicId], ['id' => $record['id']]);
            $processed++;

            if ($processed % 100 === 0) {
                $io->out(sprintf('  Generated %d / %d...', $processed, $count));
            }
        }

        return $processed;
    }

    /**
     * Generate unique public ID
     *
     * @param \Cake\Database\Connection $connection Database connection
     * @param string $table Table name
     * @param string $field Field name
     * @param int $length ID length
     * @param string $charset Character set
     * @param int $charsetLength Charset length
     * @return string Generated public ID
     */
    protected function _generateUniquePublicId(
        $connection,
        string $table,
        string $field,
        int $length,
        string $charset,
        int $charsetLength
    ): string {
        $maxAttempts = 10;
        $attempt = 0;

        do {
            $publicId = '';
            for ($i = 0; $i < $length; $i++) {
                $publicId .= $charset[random_int(0, $charsetLength - 1)];
            }

            $exists = $connection->selectQuery()
                ->select(['id'])
                ->from($table)
                ->where([$field => $publicId])
                ->execute()
                ->fetch('assoc');

            $attempt++;

            if ($attempt >= $maxAttempts) {
                throw new \RuntimeException(sprintf(
                    'Failed to generate unique public ID for %s after %d attempts',
                    $table,
                    $maxAttempts
                ));
            }
        } while ($exists);

        return $publicId;
    }
}
