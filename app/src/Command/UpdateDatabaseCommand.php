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
        $exitCode = $this->executeCommand(SchemacacheClearCommand::class, ['--connection', 'default'], $io);
        if ($exitCode !== null && $exitCode !== Command::CODE_SUCCESS) {
            $io->err('Schema cache clear failed.');

            return Command::CODE_ERROR;
        }

        $frameworkMigration = new Migrate();
        $exitCode = $this->executeCommand($frameworkMigration, ['migrate'], $io);
        if ($exitCode !== null && $exitCode !== Command::CODE_SUCCESS) {
            $io->err('Application migrations failed.');

            return Command::CODE_ERROR;
        }

        foreach ($pluginsToMigrate as $name => $order) {
            $pluginMigration = new Migrate();
            $exitCode = $this->executeCommand($pluginMigration, ['migrate', '-p', $name], $io);
            if ($exitCode !== null && $exitCode !== Command::CODE_SUCCESS) {
                $io->err(sprintf('Plugin migrations failed for %s.', $name));

                return Command::CODE_ERROR;
            }
        }
        $io->out('Platform migrations are managed separately with: bin/cake platform_migrate');

        return Command::CODE_SUCCESS;
    }
}
