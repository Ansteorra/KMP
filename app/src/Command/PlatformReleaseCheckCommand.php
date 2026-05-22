<?php
declare(strict_types=1);

namespace App\Command;

use App\Services\Platform\ReleaseCompatibilityChecker;
use App\Services\Platform\ReleaseManifest;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use RuntimeException;

/**
 * Validates a release manifest and tenant schema compatibility for deploy gates.
 */
class PlatformReleaseCheckCommand extends Command
{
    private const PLATFORM_CONNECTION = 'platform';

    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'platform release_check';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->setDescription('Validate release manifest schema compatibility before deploy or tenant migration.')
            ->addOption('manifest', [
                'short' => 'm',
                'help' => 'Path to release manifest JSON.',
                'default' => ROOT . DS . 'config' . DS . 'release_manifest.json',
            ])
            ->addOption('tenant', [
                'short' => 't',
                'help' => 'Tenant slug to validate.',
            ])
            ->addOption('all', [
                'help' => 'Validate all active tenants.',
                'boolean' => true,
                'default' => false,
            ]);
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        try {
            $manifest = ReleaseManifest::fromFile((string)$args->getOption('manifest'));
            $rows = $this->tenantRows($args);
            if ($rows !== []) {
                $errors = (new ReleaseCompatibilityChecker())->incompatibleTenants($rows, $manifest);
                if ($errors !== []) {
                    foreach ($errors as $error) {
                        $io->err($error);
                    }

                    return self::CODE_ERROR;
                }
            }

            $io->success(sprintf(
                'Release manifest %s is compatible with %d tenant(s).',
                $manifest->appVersion,
                count($rows),
            ));

            return self::CODE_SUCCESS;
        } catch (RuntimeException $e) {
            $io->err($e->getMessage());

            return self::CODE_ERROR;
        }
    }

    /**
     * @return list<array{slug: string, schema_version: string|null}>
     */
    private function tenantRows(Arguments $args): array
    {
        $tenant = trim((string)$args->getOption('tenant'));
        $all = (bool)$args->getOption('all');
        if ($tenant !== '' && $all) {
            throw new RuntimeException('Use either --tenant or --all, not both.');
        }
        if ($tenant === '' && !$all) {
            return [];
        }

        if ($all) {
            return $this->platform()->execute(
                'SELECT slug, schema_version FROM tenants WHERE status = :status ORDER BY slug',
                ['status' => 'active'],
            )->fetchAll('assoc');
        }

        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,78}[a-z0-9])?$/', $tenant)) {
            throw new RuntimeException('Invalid tenant slug.');
        }

        $row = $this->platform()->execute(
            'SELECT slug, schema_version FROM tenants WHERE slug = :slug LIMIT 1',
            ['slug' => $tenant],
        )->fetch('assoc');
        if (!is_array($row)) {
            throw new RuntimeException(sprintf('Tenant "%s" was not found.', $tenant));
        }

        return [$row];
    }

    /**
     * Get the platform metadata connection.
     */
    private function platform(): Connection
    {
        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get(self::PLATFORM_CONNECTION);

        return $connection;
    }
}
