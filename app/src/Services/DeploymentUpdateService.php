<?php

declare(strict_types=1);

namespace App\Services;

use App\KMP\StaticHelpers;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Log\Log;

/**
 * Coordinates app updates, maintenance mode, deployment command execution,
 * smoke tests, and rollback actions.
 */
class DeploymentUpdateService
{
    public const STATUS_FILE_PATH = '/tmp/kmp-update-state.json';

    public const PROFILE_ENV_SUFFIXES = [
        'auto' => null,
        'vpc' => 'VPC',
        'azure' => 'AZURE',
        'aws' => 'AWS',
        'fly' => 'FLY',
        'railway' => 'RAILWAY',
    ];

    public function __construct(
        private DeploymentSmokeTestService $smokeTestService,
        private DeploymentReleaseService $releaseService,
    ) {
    }

    /**
     * Return available release channels.
     *
     * @return array<string, string>
     */
    public function getChannels(): array
    {
        return $this->releaseService->getChannels();
    }

    /**
     * Get latest candidate by channel.
     */
    public function getLatestByChannel(string $channel): ?array
    {
        return $this->releaseService->getLatestByChannel($channel);
    }

    /**
     * Read the current and recent update history.
     *
     * @return array<string, mixed>
     */
    public function getLastRunState(): array
    {
        $default = [
            'status' => 'never-run',
            'started_at' => null,
            'completed_at' => null,
            'channel' => null,
            'version' => null,
            'message' => null,
        ];

        try {
            $payload = StaticHelpers::getAppSetting('KMP.Update.LastRunState', null);
            if (is_string($payload)) {
                $decoded = json_decode($payload, true);
                if (is_array($decoded)) {
                    return array_merge($default, $decoded);
                }
            }
        } catch (\Throwable) {
            // Intentionally ignored.
        }

        return $default;
    }

    /**
     * Return normalized deployment profile from environment.
     */
    public function getProfile(): string
    {
        $configured = strtolower(trim((string)env('KMP_DEPLOY_PROVIDER', 'auto')));
        if (!array_key_exists($configured, self::PROFILE_ENV_SUFFIXES)) {
            return 'auto';
        }

        return $configured;
    }

    /**
     * Return supported profiles for UI/help.
     *
     * @return array<string, string>
     */
    public function getProfileChoices(): array
    {
        return [
            'auto' => 'Auto/Generic',
            'vpc' => 'VPC / Self-hosted container',
            'azure' => 'Azure App Service / Container Apps',
            'aws' => 'AWS ECS / Elastic Beanstalk / EC2',
            'fly' => 'Fly.io',
            'railway' => 'Railway',
        ];
    }

    /**
     * Trigger an update using configured strategy.
     *
     * @param string $channel Release channel.
     * @param string $version Target version identifier.
     * @param bool $confirmed User-confirmed flag.
     * @param bool $dryRun Validate plan without mutating environment.
     */
    public function runUpdate(string $channel, string $version, bool $confirmed, bool $dryRun = false): array
    {
        if (!$confirmed) {
            return [
                'status' => 'blocked',
                'message' => 'Update requires confirmation before execution.',
            ];
        }

        $channel = strtolower($channel);
        if (!array_key_exists($channel, $this->releaseService->getChannels())) {
            return ['status' => 'blocked', 'message' => 'Invalid release channel: ' . $channel];
        }

        $version = trim($version);
        if (!$this->isAllowedVersion($version)) {
            return ['status' => 'blocked', 'message' => 'Invalid version format: ' . $version];
        }

        $currentVersion = (string)StaticHelpers::getAppSetting('App.version', '0.0.0');
        $profile = $this->getProfile();

        $started = (new \DateTimeImmutable())->format(\DateTime::ATOM);
        $state = [
            'status' => 'starting',
            'started_at' => $started,
            'completed_at' => null,
            'version' => $version,
            'previous_version' => $currentVersion,
            'channel' => $channel,
            'profile' => $profile,
            'message' => 'Starting update',
        ];
        $this->writeState($state);

        if ($dryRun) {
            $state['status'] = 'success';
            $state['message'] = 'Dry-run mode accepted. No changes applied.';
            $state['completed_at'] = (new \DateTimeImmutable())->format(\DateTime::ATOM);
            $this->writeState($state);

            return $state;
        }

        $snapshot = null;
        try {
            $this->setMaintenanceMode(true);

            $state['status'] = 'precheck';
            $state['message'] = 'Running pre-update smoke checks';
            $this->writeState($state);

            $preSmoke = $this->smokeTestService->runTests(['database' => true, 'cache' => true]);
            if (!$preSmoke['success']) {
                throw new \RuntimeException('Pre-update smoke test failed: ' . $this->flattenFailureMessages($preSmoke['tests']));
            }

            $state['pre_smoke_test'] = $preSmoke;
            $state['status'] = 'snapshot';
            $state['message'] = 'Taking optional DB snapshot';
            $this->writeState($state);

            $snapshot = $this->takeDatabaseSnapshot();

            $state['snapshot'] = $snapshot;
            $state['status'] = 'deploying';
            $state['message'] = 'Running deploy command';
            $this->writeState($state);

            $deployResult = $this->runDeploymentCommand($channel, $version);
            if (!$deployResult['success']) {
                throw new \RuntimeException('Deployment command failed: ' . $deployResult['message']);
            }
            $state['deploy_output'] = $deployResult;

            $state['status'] = 'smoke_testing';
            $state['message'] = 'Running smoke checks';
            $this->writeState($state);

            $postSmoke = $this->smokeTestService->runTests();
            if (!$postSmoke['success']) {
                throw new \RuntimeException('Smoke tests failed: ' . $this->flattenFailureMessages($postSmoke['tests']));
            }

            StaticHelpers::setAppSetting('App.version', $version);
            $this->setMaintenanceMode(false);

            $state['status'] = 'success';
            $state['message'] = 'Update completed successfully';
            $state['completed_at'] = (new \DateTimeImmutable())->format(\DateTime::ATOM);
            $state['smoke_tests'] = $postSmoke;
            if ($snapshot !== null) {
                $state['database_snapshot'] = $snapshot;
            }
            $this->writeState($state);

            Log::info(sprintf('Deployment to %s completed for profile=%s', $version, $profile));

            return $state;
        } catch (\Throwable $e) {
            $state['status'] = 'rolling_back';
            $state['message'] = 'Update failed, attempting rollback: ' . $e->getMessage();
            $this->writeState($state);

            Log::error('Update failed, attempting rollback: ' . $e->getMessage());

            try {
                $rollbackResult = $this->runRollbackCommand($channel, $version, $snapshot, $state['previous_version'] ?? null);
                $state['rollback'] = $rollbackResult;
                if (!$rollbackResult['success']) {
                    $state['message'] .= ' Rollback command failed: ' . ($rollbackResult['message'] ?? 'unknown');
                }
            } catch (\Throwable $rollbackError) {
                $state['rollback'] = [
                    'success' => false,
                    'message' => $rollbackError->getMessage(),
                ];
                $state['message'] .= ' Rollback command exception: ' . $rollbackError->getMessage();
            } finally {
                StaticHelpers::setAppSetting('App.version', $currentVersion);
                $this->setMaintenanceMode(false);
            }

            $state['status'] = 'failed';
            $state['completed_at'] = (new \DateTimeImmutable())->format(\DateTime::ATOM);
            $this->writeState($state);

            return $state;
        }
    }

    /**
     * Run deployment command.
     */
    private function runDeploymentCommand(string $channel, string $version): array
    {
        $command = $this->resolveCommand('KMP_UPDATE_COMMAND', $channel, $version);
        if (trim($command) === '') {
            return [
                'success' => true,
                'message' => 'No deploy command configured',
            ];
        }

        return $this->runShellCommand($command);
    }

    /**
     * Run rollback command.
     */
    private function runRollbackCommand(string $channel, string $version, ?string $snapshot, ?string $previousVersion): array
    {
        $rollbackTarget = $previousVersion ?? $snapshot;
        if (empty($rollbackTarget) && trim((string)$version) === '') {
            return ['success' => false, 'message' => 'No rollback target available'];
        }

        $command = $this->resolveCommand('KMP_ROLLBACK_COMMAND', $channel, $version, $rollbackTarget);
        if (trim($command) === '') {
            $command = $this->resolveCommand('KMP_UPDATE_COMMAND', $channel, $rollbackTarget ?? $version);
        }

        if (trim($command) === '') {
            return ['success' => true, 'message' => 'No rollback command configured'];
        }

        return $this->runShellCommand($command);
    }

    /**
     * Create DB snapshot marker command (optional).
     */
    private function takeDatabaseSnapshot(): ?string
    {
        $command = trim((string)env('KMP_DB_SNAPSHOT_COMMAND'));
        if ($command === '') {
            return null;
        }

        $result = $this->runShellCommand($command);
        if (!$result['success']) {
            return null;
        }

        return (string)($result['output'] ?? null);
    }

    /**
     * Set maintenance mode app setting.
     */
    private function setMaintenanceMode(bool $enable): void
    {
        StaticHelpers::setAppSetting('KMP.MaintenanceMode', $enable ? 'yes' : 'no');

        try {
            $connection = ConnectionManager::get('default');
            if (method_exists($connection->getDriver(), 'disconnect')) {
                $connection->getDriver()->disconnect();
            }
        } catch (\Throwable) {
            // Intentionally ignored.
        }
    }

    /**
     * Resolve shell command from profile and action.
     */
    private function resolveCommand(
        string $envName,
        string $channel,
        string $version,
        ?string $snapshot = null,
    ): string {
        $profile = strtoupper($this->getProfile());
        $template = null;

        if (isset(self::PROFILE_ENV_SUFFIXES[strtolower($profile)])) {
            $suffix = self::PROFILE_ENV_SUFFIXES[strtolower($profile)];
            if ($suffix !== null) {
                $candidate = env($envName . '_' . $suffix, null);
                if ($candidate !== null && trim($candidate) !== '') {
                    $template = $candidate;
                }
            }
        }

        if ($template === null || trim($template) === '') {
            $template = (string)env($envName, '');
        }

        if ($template === '') {
            return '';
        }

        $project = env('KMP_PROJECT_NAME', env('COMPOSE_PROJECT_NAME', 'kmp'));
        $image = env('KMP_IMAGE_NAME', Configure::read('App.title', 'kmp'));
        $imageTag = trim(env('KMP_IMAGE_TAG', env('IMAGE_TAG', 'latest')));
        $composeFile = env('KMP_COMPOSE_FILE', env('COMPOSE_FILE', 'docker-compose.yml'));
        $workDir = ROOT;
        $envFile = env('KMP_ENV_FILE', ROOT . DS . 'app' . DS . 'config' . DS . '.env');

        $replacement = [
            '{{CHANNEL}}' => $this->shellEscape($channel),
            '{{VERSION}}' => $this->shellEscape($version),
            '{{IMAGE}}' => $this->shellEscape($image),
            '{{PROJECT}}' => $this->shellEscape($project),
            '{{IMAGE_TAG}}' => $this->shellEscape($imageTag),
            '{{SNAPSHOT}}' => $snapshot !== null ? $this->shellEscape($snapshot) : '',
            '{{ENV_FILE}}' => $this->shellEscape($envFile),
            '{{COMPOSE_FILE}}' => $this->shellEscape($composeFile),
            '{{WORKDIR}}' => $this->shellEscape($workDir),
            '{{PROFILE}}' => strtolower($this->getProfile()),
        ];

        return strtr($template, $replacement);
    }

    /**
     * Run command in shell with status/output.
     */
    private function runShellCommand(string $command): array
    {
        if (trim($command) === '') {
            return [
                'success' => false,
                'message' => 'No command configured',
                'output' => '',
                'exit_code' => 127,
            ];
        }

        $timeout = (int)env('KMP_UPDATE_COMMAND_TIMEOUT', 1200);
        $start = microtime(true);
        $descriptors = [
            ['pipe', 'r'],
            ['pipe', 'w'],
            ['pipe', 'w'],
        ];

        $process = proc_open(
            ['sh', '-lc', $command],
            $descriptors,
            $pipes,
            ROOT,
            array_merge($_ENV, $_SERVER),
        );

        if (!is_resource($process)) {
            return [
                'success' => false,
                'message' => 'Unable to spawn shell command',
                'output' => '',
                'exit_code' => 1,
            ];
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $output = '';
        $timedOut = false;

        while (true) {
            $status = proc_get_status($process);
            $output .= $this->readPipe($pipes[1]);
            $output .= $this->readPipe($pipes[2]);

            if (!$status['running']) {
                break;
            }

            if (microtime(true) - $start > $timeout) {
                proc_terminate($process, 15);
                $timedOut = true;
                break;
            }

            usleep(100000);
        }

        if (!feof($pipes[1])) {
            $output .= stream_get_contents($pipes[1]);
        }
        if (!feof($pipes[2])) {
            $output .= stream_get_contents($pipes[2]);
        }

        $exitCode = proc_close($process);
        if ($timedOut) {
            $exitCode = 124;
        }

        return [
            'success' => $exitCode === 0,
            'message' => $exitCode === 0 ? 'Command completed' : 'Command failed',
            'output' => trim((string)$output),
            'exit_code' => $exitCode,
            'timed_out' => $timedOut,
            'duration_seconds' => (int)round(microtime(true) - $start),
        ];
    }

    private function readPipe(mixed $pipe): string
    {
        if (!is_resource($pipe)) {
            return '';
        }

        $chunk = stream_get_contents($pipe);
        if ($chunk === false) {
            return '';
        }

        return $chunk;
    }

    private function shellEscape(string $value): string
    {
        return "'" . str_replace("'", "'\\''", $value) . "'";
    }

    private function isAllowedVersion(string $version): bool
    {
        return $version !== '' && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $version) === 1;
    }

    /**
     * Store current update state for UI/status reads.
     */
    private function writeState(array $state): void
    {
        $payload = json_encode($state, JSON_PRETTY_PRINT);
        if ($payload === false) {
            return;
        }

        StaticHelpers::setAppSetting('KMP.Update.LastRunState', $payload);
        try {
            file_put_contents(self::STATUS_FILE_PATH, $payload . "\n");
        } catch (\Throwable) {
            // Optional local file is best-effort only.
        }
    }

    /**
     * Build failure summary from smoke tests.
     */
    private function flattenFailureMessages(array $tests): string
    {
        $failed = array_map(
            fn(array $test) => $test['message'] ?? 'unknown failure',
            array_filter($tests, fn(array $test) => ($test['passed'] ?? false) === false),
        );

        if ($failed === []) {
            return 'unknown failure';
        }

        return implode(' | ', $failed);
    }
}
