<?php

declare(strict_types=1);

namespace App\Command;

use App\KMP\StaticHelpers;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

/**
 * KMP installer wizard for first-time setup and targeted upgrades.
 */
class KmpInstallCommand extends Command
{
    /**
     * @inheritDoc
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);

        return $parser
            ->setDescription('Run interactive first-time installation helper for KMP deployment.')
            ->addOption('profile', [
                'short' => 'p',
                'help' => 'Deployment profile: auto, vpc, azure, aws, fly, railway',
            ])
            ->addOption('database-driver', [
                'help' => 'Database family: mysql or postgres',
            ])
            ->addOption('database-url', [
                'help' => 'Full database URL (e.g. mysql://... or postgres://...)',
            ])
            ->addOption('db-host', [
                'help' => 'Database host (if DATABASE_URL not provided)',
            ])
            ->addOption('db-port', [
                'help' => 'Database port (if DATABASE_URL not provided)',
            ])
            ->addOption('db-name', [
                'help' => 'Database name (if DATABASE_URL not provided)',
            ])
            ->addOption('db-username', [
                'help' => 'Database username (if DATABASE_URL not provided)',
            ])
            ->addOption('db-password', [
                'help' => 'Database password (if DATABASE_URL not provided)',
            ])
            ->addOption('storage', [
                'help' => 'Document storage adapter: local, azure, s3',
            ])
            ->addOption('redis-url', [
                'help' => 'Redis/Cache service URL for sessions and cache',
            ])
            ->addOption('azure-connection-string', [
                'help' => 'Azure Blob connection string',
            ])
            ->addOption('s3-bucket', [
                'help' => 'S3 bucket name',
            ])
            ->addOption('s3-region', [
                'help' => 'S3 region',
            ])
            ->addOption('s3-endpoint', [
                'help' => 'S3-compatible endpoint (optional)',
            ])
            ->addOption('run-bootstrap', [
                'boolean' => true,
                'help' => 'Run composer install and migrations after generating config',
                'default' => false,
            ])
            ->addOption('skip-bootstrap', [
                'boolean' => true,
                'help' => 'Skip composer install and migrations',
                'default' => false,
            ])
            ->addOption('yes', [
                'short' => 'y',
                'boolean' => true,
                'help' => 'Run non-interactively after generating config',
                'default' => false,
            ])
            ->addOption('env-file', [
                'help' => 'Path to environment file to write',
                'default' => ROOT . DS . 'app' . DS . 'config' . DS . '.env',
            ])
            ->addOption('json', [
                'boolean' => true,
                'help' => 'Output generated configuration as JSON',
                'default' => false,
            ]);
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $io->out('KMP Deployment Installer');
        $io->out('This command gathers deployment settings and writes app/config/.env for first-time setup.');

        $profile = $this->normalizeChoice(
            $args->getOption('profile') ?? '',
            ['auto', 'vpc', 'azure', 'aws', 'fly', 'railway'],
            'auto',
        );
        $profile = $io->askChoice('Deployment profile', ['auto', 'vpc', 'azure', 'aws', 'fly', 'railway'], $profile);

        $dbDriver = $this->normalizeChoice(
            $args->getOption('database-driver') ?? '',
            ['mysql', 'postgres'],
            $this->askOrDefault($io, 'Database family', ['mysql', 'postgres'], 'mysql', $args->getOption('database-driver')),
        );

        $databaseUrl = trim((string)$args->getOption('database-url'));
        if ($databaseUrl === '') {
            $databaseUrl = $this->buildDatabaseUrl(
                $io,
                $dbDriver,
                (string)($args->getOption('db-host') ?? ''),
                (string)($args->getOption('db-port') ?? ''),
                (string)($args->getOption('db-name') ?? ''),
                (string)($args->getOption('db-username') ?? ''),
                (string)($args->getOption('db-password') ?? ''),
            );
        }

        $storage = $this->normalizeChoice(
            $args->getOption('storage') ?? '',
            ['local', 'azure', 's3'],
            $this->askOrDefault($io, 'Document storage', ['local', 'azure', 's3'], 'local', $args->getOption('storage')),
        );

        $redisUrl = trim((string)$args->getOption('redis-url'));
        if ($redisUrl === '') {
            $io->ask('Redis/Cache URL (blank to keep local) [blank]', $redisUrl);
        }

        $azureConnection = trim((string)$args->getOption('azure-connection-string'));
        $s3Bucket = trim((string)$args->getOption('s3-bucket'));
        $s3Region = trim((string)$args->getOption('s3-region'));
        $s3Endpoint = trim((string)$args->getOption('s3-endpoint'));

        if ($storage === 'azure') {
            if ($azureConnection === '') {
                $azureConnection = $io->ask('Azure Blob storage connection string');
            }
        }

        if ($storage === 's3') {
            if ($s3Bucket === '') {
                $s3Bucket = $io->ask('S3 bucket name');
            }
            if ($s3Region === '') {
                $s3Region = $io->ask('S3 region', 'us-east-1');
            }
            if ($s3Endpoint === '') {
                $s3Endpoint = $io->ask('S3-compatible endpoint (optional)', '');
            }
        }

        $envFile = $args->getOption('env-file');
        $payload = $this->buildEnvPayload($profile, $dbDriver, $databaseUrl, $redisUrl, $storage, $azureConnection, $s3Bucket, $s3Region, $s3Endpoint);

        if ($args->getOption('json')) {
            $io->out(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return Command::CODE_SUCCESS;
        }

        if (!$this->confirmWrite($io, $payload, (bool)$args->getOption('yes'))) {
            $io->warning('Install cancelled.');

            return Command::CODE_ERROR;
        }

        $this->writeEnvFile($envFile, $payload);
        StaticHelpers::setAppSetting('KMP.Deploy.Profile', $profile);

        $io->success(sprintf('Wrote environment file: %s', $envFile));

        $runBootstrap = (bool)$args->getOption('run-bootstrap')
            || ((bool)$args->getOption('skip-bootstrap') === false && $io->askChoice(
                'Run bootstrap now (composer install + migrations)?',
                ['yes', 'no'],
                'yes',
            ) === 'yes');

        if (!$runBootstrap) {
            $io->warning('Skipping bootstrap. Run manually: composer install, migrations, updateDatabase.');

            return Command::CODE_SUCCESS;
        }

        return $this->runBootstrap($io);
    }

    private function normalizeChoice(string $value, array $valid, string $default): string
    {
        if ($value === '' || !in_array($value, $valid, true)) {
            return $default;
        }

        return strtolower($value);
    }

    private function askOrDefault(ConsoleIo $io, string $label, array $options, string $default, mixed $optionValue): string
    {
        if ($optionValue !== null && $optionValue !== '') {
            return $this->normalizeChoice((string)$optionValue, $options, $default);
        }

        return $io->askChoice($label, $options, $default);
    }

    private function buildDatabaseUrl(
        ConsoleIo $io,
        string $driver,
        string $dbHost,
        string $dbPort,
        string $dbName,
        string $dbUser,
        string $dbPass,
    ): string {
        $dbHost = $dbHost ?: $io->ask('Database host', 'localhost');
        $dbPort = $dbPort ?: ($driver === 'postgres' ? '5432' : '3306');
        $dbName = $dbName ?: $io->ask('Database name', 'kmp');
        $dbUser = $dbUser ?: $io->ask('Database username', 'kmp_user');
        $dbPass = $dbPass ?: $io->askHidden('Database password (input hidden)');

        if ($driver === 'postgres') {
            return sprintf(
                'postgres://%s:%s@%s:%s/%s',
                rawurlencode($dbUser),
                rawurlencode($dbPass),
                rawurlencode($dbHost),
                rawurlencode($dbPort),
                rawurlencode($dbName),
            );
        }

        return sprintf(
            'mysql://%s:%s@%s:%s/%s',
            rawurlencode($dbUser),
            rawurlencode($dbPass),
            rawurlencode($dbHost),
            rawurlencode($dbPort),
            rawurlencode($dbName),
        );
    }

    private function buildEnvPayload(
        string $profile,
        string $databaseDriver,
        string $databaseUrl,
        string $redisUrl,
        string $storage,
        string $azureConnection,
        string $s3Bucket,
        string $s3Region,
        string $s3Endpoint,
    ): array {
        $payload = [
            'KMP_DEPLOY_PROVIDER' => $profile,
            'DATABASE_URL' => $databaseUrl,
            'DB_DRIVER' => $databaseDriver,
            'KMP_UPDATE_COMMAND_TIMEOUT' => '1200',
            'KMP_RESTORE_FROM_SNAPSHOT' => 'false',
            'KMP_SNAPSHOTS' => './data/snapshots',
            'KMP_DEPLOY_PROVIDER' => $profile,
            'Documents.storage.adapter' => $storage,
        ];

        if ($redisUrl !== '') {
            $payload['KMP_CACHE_URL'] = $redisUrl;
        }

        if ($storage === 'azure') {
            $payload['AZURE_STORAGE_CONNECTION_STRING'] = $azureConnection;
        }

        if ($storage === 's3') {
            $payload['AWS_REGION'] = $s3Region;
            $payload['AWS_BUCKET'] = $s3Bucket;
            if ($s3Endpoint !== '') {
                $payload['AWS_ENDPOINT'] = $s3Endpoint;
            }
        }

        return $payload;
    }

    private function writeEnvFile(string $path, array $payload): void
    {
        ksort($payload);
        $lines = [];
        foreach ($payload as $key => $value) {
            if ($value === '') {
                continue;
            }
            $sanitized = preg_replace('/"/', '\\"', $value);
            $lines[] = sprintf('%s="%s"', $key, (string)$sanitized);
        }

        file_put_contents($path, implode(PHP_EOL, $lines) . PHP_EOL);
    }

    private function confirmWrite(ConsoleIo $io, array $payload, bool $nonInteractive): bool
    {
        $io->out('Configuration summary:');
        foreach ($payload as $key => $value) {
            if ($value === '') {
                continue;
            }
            $io->out(sprintf('  %s=%s', $key, $value));
        }

        if ($nonInteractive) {
            return true;
        }

        return strtolower((string)$io->askChoice('Apply this configuration?', ['yes', 'no'], 'yes')) === 'yes';
    }

    private function runBootstrap(ConsoleIo $io): ?int
    {
        $commands = [
            'cd app && composer install --no-interaction --prefer-dist',
            'cd app && vendor/bin/phpunit --version',
            'cd app && bin/cake update_database',
        ];

        foreach ($commands as $command) {
            $io->out('Running: ' . $command);
            $result = $this->runShellCommand($command, $io);
            if (!$result) {
                $io->error('Bootstrap command failed: ' . $command);

                return Command::CODE_ERROR;
            }
        }

        $io->success('Bootstrap completed.');

        return Command::CODE_SUCCESS;
    }

    private function runShellCommand(string $command, ConsoleIo $io): bool
    {
        $descriptors = [
            ['pipe', 'r'],
            ['pipe', 'w'],
            ['pipe', 'w'],
        ];

        $process = proc_open(['sh', '-lc', $command], $descriptors, $pipes);
        if (!is_resource($process)) {
            $io->error('Unable to run command: ' . $command);

            return false;
        }

        while (!feof($pipes[1]) || !feof($pipes[2])) {
            $out = stream_get_contents($pipes[1]);
            $err = stream_get_contents($pipes[2]);
            if ($out !== false && $out !== '') {
                $io->out(trim($out));
            }
            if ($err !== false && $err !== '') {
                $io->error(trim($err));
            }
            if (feof($pipes[1]) && feof($pipes[2])) {
                break;
            }
            usleep(100000);
        }

        $exitCode = proc_close($process);
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        return $exitCode === 0;
    }
}
