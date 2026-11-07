<?php
declare(strict_types=1);

namespace App\Command;

use App\Services\Platform\TenantHostResolver;
use App\Services\PublicCalendarDemoService;
use App\Services\Secrets\SecretStoreFactory;
use App\Services\TenantConnectionManager;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;

/** Refreshes sample events locally or in an explicitly enabled staging administrative job. */
class SeedPublicCalendarCommand extends Command
{
    /** @inheritDoc */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->setDescription('Create or refresh current published demo events in a local development tenant.')
            ->addOption('host', ['default' => 'kmp.localhost', 'help' => 'Local tenant hostname.'])
            ->addOption('poc', [
                'boolean' => true, 'default' => false,
                'help' => 'Target a staging tenant; requires KMP_ENV=staging, KMP_ADMIN_JOB '
                    . 'and KMP_ALLOW_POC_CALENDAR_SEED.',
            ])
            ->addOption('refresh-activity-icons', [
                'boolean' => true, 'default' => false,
                'help' => 'Apply the latest icon defaults to normal seeded activities, preserving uploaded artwork.',
            ])
            ->addOption('refresh-activity-colors', [
                'boolean' => true, 'default' => false,
                'help' => 'Apply the latest color defaults to normal seeded activities, preserving uploaded artwork.',
            ]);
    }

    /** @inheritDoc */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $host = (string)$args->getOption('host');
        $poc = (bool)$args->getOption('poc');
        $pocAllowed = $poc && env('KMP_ENV') === 'staging'
            && filter_var(env('KMP_ADMIN_JOB', false), FILTER_VALIDATE_BOOLEAN)
            && filter_var(env('KMP_ALLOW_POC_CALENDAR_SEED', false), FILTER_VALIDATE_BOOLEAN);
        $localAllowed = !$poc && Configure::read('debug') && preg_match('/^[a-z0-9-]+\.localhost$/D', $host);
        if (!$pocAllowed && !$localAllowed) {
            $io->error(
                'Calendar demos require debug mode and a local tenant hostname, '
                . 'or an explicitly enabled staging administrative job.',
            );

            return self::CODE_ERROR;
        }
        $tenant = (new TenantHostResolver())->resolve($host);
        if (
            !$tenant || $tenant->status !== 'active'
            || (!$pocAllowed && !in_array($tenant->dbServer, ['db', 'postgres', '127.0.0.1', 'localhost'], true))
        ) {
            $io->error('Calendar demos require an active tenant on the local PostgreSQL server.');

            return self::CODE_ERROR;
        }
        $manager = new TenantConnectionManager(SecretStoreFactory::fromConfig());
        $counts = $manager->withTenant($tenant, fn() => (new PublicCalendarDemoService())->refresh(
            refreshActivityIcons: (bool)$args->getOption('refresh-activity-icons'),
            refreshActivityColors: (bool)$args->getOption('refresh-activity-colors'),
        ));
        $io->success(sprintf(
            'Refreshed %d published demo events and %d activities.',
            $counts['events'],
            $counts['activities'],
        ));

        return self::CODE_SUCCESS;
    }
}
