<?php

declare(strict_types=1);

namespace App\Command;

use App\KMP\KMPPluginInterface;
use Cake\Command\Command;
use Cake\Command\SchemacacheClearCommand;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Plugin;
use Migrations\Command\MigrateCommand as Migrate;

/**
 * RevertDatabase command.
 */
class UpdateDatabaseCommand extends Command
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
        $parser = parent::buildOptionParser($parser)
            ->addOption('plugin', [
                'short' => 'p',
                'help' => 'The plugin to run migrations for',
            ]);

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
        //create a date at 0000-00-00 00:00:00
        $date = '0000-01-01 00:00:00';
        $items = Plugin::getCollection();
        $pluginsToMigrate = [];

        foreach ($items as $name => $plugin) {
            if ($plugin instanceof KMPPluginInterface) {
                $pluginsToMigrate[$name] = $plugin->getMigrationOrder();
            } else {
                //check if the path of the plugin includes a config/migrations folder
                if (is_dir($plugin->getPath() . 'config' . DS . 'Migrations')) {
                    $pluginsToMigrate[$name] = 100;
                }
            }
        }
        //sort
        asort($pluginsToMigrate);
        $this->executeCommand(SchemacacheClearCommand::class, ['--connection', 'default'], $io);
        $frameworkMigration = new Migrate();
        $this->executeCommand($frameworkMigration, ['migrate']);
        foreach ($pluginsToMigrate as $name => $order) {
            $pluginMigration = new Migrate();
            $this->executeCommand($pluginMigration, ['migrate', '-p', $name]);
        }

        return Command::CODE_SUCCESS;
    }
}
