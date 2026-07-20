<?php
declare(strict_types=1);

namespace App\Command;

use App\Services\Secrets\SecretStoreFactory;
use App\Services\Secrets\SensitiveString;
use App\Services\Secrets\WritableSecretStoreInterface;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use RuntimeException;
use Throwable;

/**
 * Idempotently provisions backup KEKs without printing secret values.
 */
final class PlatformBackupKeysEnsureCommand extends Command
{
    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'platform backup-keys ensure';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->setDescription('Ensure platform and tenant backup encryption keys exist.')
            ->addOption('platform-only', [
                'help' => 'Ensure only the platform database backup key.',
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
            $store = SecretStoreFactory::fromConfig();
            if (!$store instanceof WritableSecretStoreInterface) {
                throw new RuntimeException('Configured secret store is not writable.');
            }
            $created = $this->ensure($store, 'platform.backup.kek') ? 1 : 0;
            $existing = $created === 0 ? 1 : 0;

            if (!$args->getOption('platform-only')) {
                $platform = ConnectionManager::get('platform');
                if (!$platform instanceof Connection) {
                    throw new RuntimeException('Platform database connection is unavailable.');
                }
                $rows = $platform->execute(
                    'SELECT slug FROM tenants WHERE status != :archived ORDER BY slug',
                    ['archived' => 'archived'],
                )->fetchAll('assoc');
                foreach ($rows as $row) {
                    $slug = strtolower((string)$row['slug']);
                    if (!preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,78}[a-z0-9])?\z/', $slug)) {
                        throw new RuntimeException('A tenant slug is invalid; backup keys were not fully reconciled.');
                    }
                    if ($this->ensure($store, sprintf('tenant.%s.kek', $slug))) {
                        $created++;
                    } else {
                        $existing++;
                    }
                }
            }
        } catch (Throwable $exception) {
            $io->err($exception->getMessage());

            return self::CODE_ERROR;
        }

        $io->out(sprintf('Backup encryption keys ready: %d created, %d already present.', $created, $existing));

        return self::CODE_SUCCESS;
    }

    /**
     * Create a missing key without exposing its value.
     */
    private function ensure(WritableSecretStoreInterface $store, string $name): bool
    {
        $existing = $store->get($name);
        if ($existing !== null && !$existing->isEmpty()) {
            return false;
        }
        $value = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $store->put($name, new SensitiveString($value));

        return true;
    }
}
