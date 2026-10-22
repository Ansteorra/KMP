<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Database\Driver\Postgres;
use Cake\Datasource\ConnectionManager;
use Exception;

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
        $isDebug = (bool)Configure::read('debug');
        if (!$isDebug) {
            $io->error('Cannot reset database when not in debug.');

            return Command::CODE_ERROR;
        }

        $db = ConnectionManager::get('default');
        $driver = $db->getDriver();
        if (!$driver instanceof Postgres) {
            $io->error('Database reset requires PostgreSQL.');

            return Command::CODE_ERROR;
        }

        try {
            $io->out('Dropping and recreating public schema.');
            $db->execute('DROP SCHEMA IF EXISTS public CASCADE');
            $db->execute('CREATE SCHEMA public');
            $db->execute('GRANT ALL ON SCHEMA public TO CURRENT_USER');
            $db->execute('GRANT ALL ON SCHEMA public TO public');
            $io->success('Database reset.');
        } catch (Exception $e) {
            $io->error('Error resetting database: ' . $e->getMessage());

            return Command::CODE_ERROR;
        }

        return Command::CODE_SUCCESS;
    }
}
