<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

/**
 * Retirement notice for the former standalone installer.
 */
class KmpInstallCommand extends Command
{
    /**
     * @inheritDoc
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);

        return $parser->setDescription('Retired deployment helper; KMP uses managed PostgreSQL hosting.');
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $io->error('kmp_install has been retired for new deployments.');
        $io->out('KMP is moving to a managed multi-tenant hosting model.');
        $io->out('The legacy self-hosted deployment notes remain in docs/deployment/README.md.');

        return Command::CODE_ERROR;
    }
}
