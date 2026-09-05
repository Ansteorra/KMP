<?php
declare(strict_types=1);

namespace App\Command;

use App\Services\Platform\AdministrativeDatabase;
use App\Services\Platform\PostgresRuntimePrivileges;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Datasource\ConnectionManager;
use RuntimeException;
use Throwable;

/**
 * Reconcile platform, default, and registered tenant runtime database grants.
 */
class PlatformDatabasePrivilegesCommand extends Command
{
    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'platform database privileges';
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        try {
            AdministrativeDatabase::requireJob();
            /** @var \Cake\Database\Connection $platform */
            $platform = ConnectionManager::get('platform');
            $targets = [];
            foreach (['DATABASE_URL', 'PLATFORM_DATABASE_URL'] as $variable) {
                $url = parse_url((string)env($variable));
                if (
                    !is_array($url) || !isset($url['host'], $url['user'], $url['pass'], $url['path'])
                    || !in_array($url['scheme'] ?? '', ['postgres', 'postgresql'], true)
                    || strtolower($url['host']) !== strtolower((string)$platform->config()['host'])
                ) {
                    throw new RuntimeException(
                        'Explicit runtime PostgreSQL URLs on the administrative server are required.',
                    );
                }
                $database = ltrim($url['path'], '/');
                $credentials = [rawurldecode($url['user']), rawurldecode($url['pass'])];
                if (isset($targets[$database]) && $targets[$database] !== $credentials) {
                    throw new RuntimeException('Database URLs conflict on the runtime role or password.');
                }
                $targets[$database] = $credentials;
            }
            if (in_array('tenants', $platform->getSchemaCollection()->listTables(), true)) {
                $tenants = $platform->execute('SELECT db_server, db_name, db_role FROM tenants')->fetchAll('assoc');
                foreach ($tenants as $tenant) {
                    if (strtolower($tenant['db_server']) !== strtolower((string)$platform->config()['host'])) {
                        throw new RuntimeException(
                            'Run reconciliation separately for every registered database server.',
                        );
                    }
                    if (isset($targets[$tenant['db_name']]) && $targets[$tenant['db_name']][0] !== $tenant['db_role']) {
                        throw new RuntimeException('Default and registered tenant database roles must agree.');
                    }
                    $targets[$tenant['db_name']] ??= [$tenant['db_role'], null];
                }
            }
            $roles = array_column($targets, 0);
            if (count(array_unique($roles)) !== count($roles)) {
                throw new RuntimeException('Each managed database requires a distinct runtime role.');
            }
            $reconciler = new PostgresRuntimePrivileges($platform);
            // Create/reconcile roles before removing stale grants to every other role.
            foreach ($targets as $database => [$role, $password]) {
                $reconciler->reconcile($database, $role, $password);
            }
            foreach ($targets as $database => [$role, $password]) {
                $reconciler->reconcile($database, $role, $password, array_values(array_diff($roles, [$role])));
                $io->out('Reconciled runtime database privileges.');
            }
        } catch (Throwable $exception) {
            // Do not print SQL/DSNs/passwords contained in driver exceptions.
            $io->err('Database privilege reconciliation failed; inspect the private administrative job diagnostics.');

            return self::CODE_ERROR;
        }

        return self::CODE_SUCCESS;
    }
}
