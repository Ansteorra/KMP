<?php

declare(strict_types=1);

namespace App\KMP;

use Migrations\Command\Phinx\Seed;
use Symfony\Component\Console\Input\ArgvInput;
use function Cake\Core\pluginSplit;
use Migrations\AbstractSeed;

/**
 * Role seed.
 */
class KMPMigrationSeedAbstract extends AbstractSeed
{
    /**
     * Calls another seeder from this seeder.
     * It will load the Seed class you are calling and run it.
     *
     * @param string $seeder Name of the seeder to call from the current seed
     * @return void
     */
    protected function runCall(string $seeder): void
    {
        [$pluginName, $seeder] = pluginSplit($seeder);

        $argv = [
            'seed',
            '--seed',
            $seeder,
        ];
        $plugin = $pluginName ?: ($this->tryGetOption('plugin') ?: $this->tryGetParameter('--plugin'));
        if ($plugin !== null) {
            $argv[] = '--plugin';
            $argv[] = $plugin;
        }

        $connection = ($this->tryGetOption('connection') ?: $this->tryGetParameter('--connection'));
        if ($connection !== null) {
            $argv[] = '--connection';
            $argv[] = $connection;
        }
        $source = ($this->tryGetOption('source') ?: $this->tryGetParameter('--source'));
        if (($source !== null) && ($source !== 'Migrations')) {
            $argv[] = '--source';
            $argv[] = $source;
        }
        $seedCommand = new Seed();
        $input = new ArgvInput($argv, $seedCommand->getDefinition());
        $seedCommand->setInput($input);
        $config = $seedCommand->getConfig();

        $seedPaths = $config->getSeedPaths();
        require_once array_pop($seedPaths) . DS . $seeder . '.php';
        /** @var \Phinx\Seed\SeedInterface $seeder */
        $seeder = new $seeder();
        $seeder->setOutput($this->getOutput());
        $seeder->setAdapter($this->getAdapter());
        $seeder->setInput($this->input);
        $seeder->run();
    }
    protected function tryGetParameter($argument, $default = null)
    {
        try {
            return $this->input->getParameterOption($argument);
        } catch (\Exception $e) {
            return $default;
        }
    }
    protected function tryGetOption($argument, $default = null)
    {
        try {
            return $this->input->getOption($argument);
        } catch (\Exception $e) {
            return $default;
        }
    }
}