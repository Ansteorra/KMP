<?php
declare(strict_types=1);

namespace App\Command;

use App\KMP\TenantMetadata;
use App\Services\Platform\AdministrativeDatabase;
use App\Services\Storage\TenantDocumentProvisioner;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Throwable;

/**
 * Plan or reconcile private document containers for the complete tenant registry.
 */
class PlatformDocumentStorageCommand extends Command
{
    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'platform storage documents';
    }

    /**
     * @inheritDoc
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)->addOption('dry-run', [
            'boolean' => true,
            'help' => 'Print the reviewed container inventory without creating containers or grants.',
        ]);
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        try {
            AdministrativeDatabase::requireJob();
            if (Configure::read('Documents.storage.adapter', 'local') !== 'azure') {
                $io->out('Azure document reconciliation is not configured.');

                return self::CODE_SUCCESS;
            }
            $tenants = [null];
            $platform = ConnectionManager::get('platform');
            foreach ($platform->execute('SELECT * FROM tenants ORDER BY slug')->fetchAll('assoc') as $row) {
                $tenants[] = TenantMetadata::fromPlatformRow($row);
            }
            $provisioner = new TenantDocumentProvisioner();
            $containers = [];
            // Validate the full registry before the first external write.
            foreach ($tenants as $tenant) {
                $plan = $provisioner->plan($tenant);
                $containers[] = $plan['azure']['container'];
            }
            $io->out(json_encode(array_values(array_unique($containers)), JSON_THROW_ON_ERROR));
            if (!$args->getOption('dry-run')) {
                foreach ($tenants as $tenant) {
                    $provisioner->ensure($tenant);
                }
                $io->out('Document containers and restricted runtime grants reconciled.');
            }
        } catch (Throwable) {
            $io->err('Document storage reconciliation failed. Runtime cutover must remain stopped.');

            return self::CODE_ERROR;
        }

        return self::CODE_SUCCESS;
    }
}
