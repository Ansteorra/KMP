<?php
declare(strict_types=1);

namespace App\Command;

use App\KMP\StaticHelpers;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Datasource\ConnectionManager;
use Exception;
use PDO;

/**
 * RevertDatabase command.
 */
class ResetDatabaseCommand extends Command
{
    /**
     * Hook method for defining this command's option parser.
     *
     * @see https://book.cakephp.org/4/en/console-commands/commands.html#defining-arguments-and-options
     * @param \Cake\Console\ConsoleOptionParser $parser The parser to be defined
     * @return \Cake\Console\ConsoleOptionParser The built parser.
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);

        return $parser;
    }

    /**
     * Implement this method with your command's logic.
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return int|null|void The exit code or null for success
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        //Database reset is only valid in Dev.. SUPER dangerous in production!!!
        $isDebug = StaticHelpers::getAppSetting('debug', 'false');
        if (!$isDebug && $isDebug !== 'false') {
            $io->error('Cannot reset database when not in debug.');

            return null;
        }

        $db = ConnectionManager::get('default');
        $driver = $db->getDriver();
        $driverClass = get_class($driver);
        //Get the string after the last / and turn it all lowercase to get the driver name
        $driverName = strtolower(substr(strrchr($driverClass, '\\'), 1));

        $dbConfig = $db->config();

        try {
            $tables = [];
            $remainingTables = true;
            $maxAttempts = 30; // Prevent infinite loops
            $attempts = 0;

            // Keep trying to drop tables until all are gone
            while ($remainingTables && $attempts < $maxAttempts) {
                $attempts++;

                // Get all tables based on database driver
                if (stripos($driverName, 'mysql') !== false) {
                    $query = $db->execute('SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ?', [$dbConfig['database']]);
                    $tables = $query->fetchAll(PDO::FETCH_COLUMN);
                } elseif (stripos($driverName, 'postgres') !== false) {
                    $query = $db->execute("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
                    $tables = $query->fetchAll(PDO::FETCH_COLUMN);
                } elseif (stripos($driverName, 'sqlite') !== false) {
                    $query = $db->execute("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                    $tables = $query->fetchAll(PDO::FETCH_COLUMN);
                } elseif (stripos($driverName, 'sqlserver') !== false) {
                    $query = $db->execute("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE' AND TABLE_SCHEMA = 'dbo'");
                    $tables = $query->fetchAll(PDO::FETCH_COLUMN);
                } else {
                    // Fallback to original method for unsupported databases
                    $io->warning("Unsupported database driver: $driverName. Falling back to dropping database.");
                    $db->execute('DROP DATABASE IF EXISTS ' . $dbConfig['database'] . ';');
                    $db->execute('CREATE DATABASE ' . $dbConfig['database'] . " DEFAULT CHARACTER SET = 'utf8mb4';");
                    break;
                }

                // If no tables left, we're done
                if (empty($tables)) {
                    $remainingTables = false;
                    continue;
                }

                $io->out("Attempt $attempts - Tables remaining: " . count($tables));

                // Try to drop each table
                foreach ($tables as $table) {
                    try {
                        if (stripos($driverName, 'mysql') !== false) {
                            $db->execute("DROP TABLE IF EXISTS `$table`");
                        } elseif (stripos($driverName, 'postgres') !== false) {
                            $db->execute("DROP TABLE IF EXISTS \"$table\" CASCADE");
                        } elseif (stripos($driverName, 'sqlite') !== false) {
                            $db->execute("DROP TABLE IF EXISTS \"$table\"");
                        } elseif (stripos($driverName, 'sqlserver') !== false) {
                            $db->execute("IF OBJECT_ID('dbo.$table', 'U') IS NOT NULL DROP TABLE [dbo].[$table]");
                        }
                        $io->out("Dropped table: $table");
                    } catch (Exception $e) {
                        $io->warning("Could not drop table $table: " . $e->getMessage());
                    }
                }
            }

            if ($remainingTables) {
                $io->warning("Could not drop all tables after $maxAttempts attempts.");

                return null;
            }

            $io->success('Database reset.');
        } catch (Exception $e) {
            $io->error('Error resetting database: ' . $e->getMessage());
        }

        return Command::CODE_SUCCESS;
    }
}
