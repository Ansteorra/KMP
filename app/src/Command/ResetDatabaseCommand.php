<?php

declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Migrations\Migrations;
use Cake\I18n\DateTime;
use Cake\Core\Plugin;
use App\KMP\KMPPluginInterface;
use Migrations\Command\MigrateCommand as Migrate;
use Cake\Datasource\ConnectionManager;
use App\KMP\StaticHelpers;

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
    public function execute(Arguments $args, ConsoleIo $io)
    {
        //Database reset is only valid in Dev.. SUPER dangerous in production!!!
        $isDebug = StaticHelpers::getAppSetting("debug", false);
        if (!$isDebug) {
            $io->error("Cannot reset database when not in debug.");
            return;
        }
        $db = ConnectionManager::get("default");
        $db->execute("DROP DATABASE IF EXISTS " . $db->config()["database"] . ";");
        $db->execute("CREATE DATABASE " . $db->config()["database"] . " DEFAULT CHARACTER SET = 'utf8mb4';");
        $io->success("Database reset.");
        ////create a date at 0000-00-00 00:00:00
        //$date = '0000-01-01 00:00:00';
        //$items = Plugin::getCollection();
        //$pluginsToMigrate = [];
        //foreach ($items as $name => $plugin) {
        //    if ($plugin instanceof KMPPluginInterface) {
        //        $pluginsToMigrate[$name] = $plugin->getMigrationOrder();
        //    }
        // }
        //sort decending
        //arsort($pluginsToMigrate);
        //foreach ($pluginsToMigrate as $name => $order) {
        //    $this->executeCommand(Migrate::class, ['rollback', '-t', $date, '-p', $name]);
        //}

        //$this->executeCommand(Migrate::class, ['rollback', '-t', $date]);
    }
}