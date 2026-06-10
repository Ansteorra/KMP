<?php
declare(strict_types=1);

namespace App\Command;

use App\Services\Platform\TenantProvisioningRequest;
use App\Services\Platform\TenantProvisioningService;
use App\Services\Secrets\SecretStoreFactory;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use RuntimeException;

/**
 * Provisions a tenant registry row and tenant database resources.
 */
class TenantProvisionCommand extends Command
{
    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'tenant provision';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->setDescription('Provision a tenant database and platform metadata.')
            ->addArgument('slug', [
                'help' => 'Tenant slug. Lowercase letters, numbers, and hyphens only.',
                'required' => true,
            ])
            ->addOption('display-name', [
                'help' => 'Human-readable tenant name.',
            ])
            ->addOption('name', [
                'help' => 'Alias for --display-name.',
            ])
            ->addOption('host', [
                'help' => 'Primary hostname for the tenant.',
                'required' => true,
            ])
            ->addOption('db-server', [
                'help' => 'Tenant database server/host. Defaults to platform DB host.',
            ])
            ->addOption('db-name', [
                'help' => 'Tenant database name. Defaults to kmp_tenant_<slug>.',
            ])
            ->addOption('db-role', [
                'help' => 'Tenant database role/user. Defaults to kmp_tenant_<slug>_role.',
            ])
            ->addOption('blob-container', [
                'help' => 'Blob container name to record for tenant documents. Defaults to tenant-<slug>.',
            ])
            ->addOption('status', [
                'help' => 'Final status after provisioning.',
                'default' => TenantProvisioningRequest::STATUS_ACTIVE,
                'choices' => [
                    TenantProvisioningRequest::STATUS_ACTIVE,
                    TenantProvisioningRequest::STATUS_PROVISIONING,
                ],
            ])
            ->addOption('create-database', [
                'help' => 'Create/update the PostgreSQL database and role.',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('skip-create-database', [
                'help' => 'Only write metadata/secrets and assume database resources already exist.',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('skip-migrations', [
                'help' => 'Skip tenant app migrations. Final status cannot be active when this is set.',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('smoke-table', [
                'help' => 'Table expected to exist after migrations.',
                'default' => 'members',
            ])
            ->addOption('initial-super-user-email', [
                'help' => 'Email address for the initial tenant super-user account.',
            ])
            ->addOption('show-password', [
                'help' => 'Print the generated/reused DB password. Intended for local/dev use only.',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('rotate-password', [
                'help' => 'Generate and store a new database password before provisioning.',
                'boolean' => true,
                'default' => false,
            ]);
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $request = new TenantProvisioningRequest(
            slug: (string)$args->getArgument('slug'),
            displayName: (string)(
                $args->getOption('display-name') ?: $args->getOption('name') ?: $args->getArgument('slug')
            ),
            host: (string)$args->getOption('host'),
            dbServer: $this->nullableOption($args, 'db-server'),
            dbName: $this->nullableOption($args, 'db-name'),
            dbRole: $this->nullableOption($args, 'db-role'),
            blobContainer: $this->nullableOption($args, 'blob-container'),
            finalStatus: (string)$args->getOption('status'),
            createDatabase: (bool)$args->getOption('create-database'),
            skipCreateDatabase: (bool)$args->getOption('skip-create-database'),
            runMigrations: !(bool)$args->getOption('skip-migrations'),
            smokeTable: (string)$args->getOption('smoke-table'),
            rotatePassword: (bool)$args->getOption('rotate-password'),
            initialSuperUserEmail: $this->nullableOption($args, 'initial-super-user-email'),
        );

        try {
            $result = (new TenantProvisioningService())->provision(
                $request,
                fn(object|string $command, array $commandArgs): ?int => $this->executeCommand(
                    $command,
                    $commandArgs,
                    $io,
                ),
                function (string $level, string $message) use ($io): void {
                    match ($level) {
                        'warning' => $io->warning($message),
                        'success' => $io->success($message),
                        default => $io->out($message),
                    };
                },
            );

            if ((bool)$args->getOption('show-password')) {
                $password = SecretStoreFactory::fromConfig()->get($result->secretName);
                if ($password === null || $password->isEmpty()) {
                    throw new RuntimeException('Generated database password was not available for display.');
                }
                $io->out('Generated database password: ' . $password->reveal());
            }

            return self::CODE_SUCCESS;
        } catch (RuntimeException $e) {
            $io->err($e->getMessage());

            return self::CODE_ERROR;
        }
    }

    /**
     * Return a trimmed nullable option.
     */
    private function nullableOption(Arguments $args, string $name): ?string
    {
        $value = trim((string)$args->getOption($name));

        return $value === '' ? null : $value;
    }
}
