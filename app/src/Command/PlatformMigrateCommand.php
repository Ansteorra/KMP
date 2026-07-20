<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Command\SchemacacheClearCommand;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Database\Driver\Postgres;
use Cake\Datasource\ConnectionManager;
use Migrations\Command\MigrateCommand;
use Migrations\Command\RollbackCommand;
use Migrations\Command\StatusCommand;

/**
 * Runs the dedicated platform metadata migration track.
 */
class PlatformMigrateCommand extends Command
{
    private const CONNECTION = 'platform';
    private const SOURCE = 'PlatformMigrations';

    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'platform_migrate';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->setDescription('Manage platform metadata database migrations.')
            ->addArgument('action', [
                'help' => 'Migration action to run.',
                'choices' => ['migrate', 'status', 'rollback'],
                'default' => 'migrate',
            ])
            ->addOption('target', [
                'short' => 't',
                'help' => 'Target migration version.',
            ])
            ->addOption('date', [
                'short' => 'd',
                'help' => 'Target migration date.',
            ])
            ->addOption('fake', [
                'help' => 'Mark migrations as run without executing them.',
                'boolean' => true,
            ])
            ->addOption('dry-run', [
                'short' => 'x',
                'help' => 'Print SQL without executing it.',
                'boolean' => true,
            ]);
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $platformConfig = ConnectionManager::getConfig(self::CONNECTION);
        if (($platformConfig['driver'] ?? null) !== Postgres::class) {
            $io->err('Platform migrations require PostgreSQL. Set KMP_DB_DRIVER=postgres for platform mode.');

            return self::CODE_ERROR;
        }

        $action = (string)$args->getArgument('action');
        $command = match ($action) {
            'migrate' => new MigrateCommand(),
            'status' => new StatusCommand(),
            'rollback' => new RollbackCommand(),
        };

        $commandArgs = [
            $action,
            '--connection',
            self::CONNECTION,
            '--source',
            self::SOURCE,
        ];
        foreach (['target', 'date'] as $option) {
            $value = $args->getOption($option);
            if ($value !== null && $value !== '') {
                $commandArgs[] = '--' . $option;
                $commandArgs[] = (string)$value;
            }
        }
        foreach (['fake', 'dry-run'] as $option) {
            if ($args->getOption($option)) {
                $commandArgs[] = '--' . $option;
            }
        }

        $this->executeCommand(SchemacacheClearCommand::class, ['--connection', self::CONNECTION], $io);

        return (int)$this->executeCommand($command, $commandArgs, $io);
    }
}
