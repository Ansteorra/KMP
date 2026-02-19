<?php

declare(strict_types=1);

namespace App\Services;

use App\KMP\StaticHelpers;
use Cake\Cache\Cache;
use Cake\Datasource\ConnectionManager;

/**
 * Runs targeted runtime checks before and after a deployment update.
 */
class DeploymentSmokeTestService
{
    /**
     * Run all smoke tests for a deployment window.
     *
     * @param array<string, string> $checks Optional test selector map.
     * @return array{success: bool, tests: array<string, array<string, mixed>>}
     */
    public function runTests(array $checks = []): array
    {
        $results = [];

        $runAll = $checks === [];

        $results['database'] = $runAll || isset($checks['database'])
            ? $this->checkDatabase()
            : ['passed' => true, 'message' => 'Skipped'];

        $results['cache'] = $runAll || isset($checks['cache'])
            ? $this->checkCache()
            : ['passed' => true, 'message' => 'Skipped'];

        $results['app_settings'] = $runAll || isset($checks['app_settings'])
            ? $this->checkAppSettings()
            : ['passed' => true, 'message' => 'Skipped'];

        $results['storage'] = $runAll || isset($checks['storage'])
            ? $this->checkStorage()
            : ['passed' => true, 'message' => 'Skipped'];

        $success = true;
        foreach ($results as $result) {
            if (($result['passed'] ?? false) === false) {
                $success = false;
                break;
            }
        }

        return [
            'success' => $success,
            'tests' => $results,
        ];
    }

    /**
     * Ensure DB connectivity is healthy.
     *
     * @return array<string, mixed>
     */
    private function checkDatabase(): array
    {
        try {
            $conn = ConnectionManager::get('default');
            $row = $conn->query('SELECT 1')->fetchAll('assoc');
            if (!is_array($row) || $row === []) {
                throw new \RuntimeException('No rows returned from SELECT 1');
            }

            return ['passed' => true, 'message' => 'Database connection and query are healthy'];
        } catch (\Throwable $e) {
            return ['passed' => false, 'message' => 'Database check failed: ' . $e->getMessage()];
        }
    }

    /**
     * Validate cache read/write.
     *
     * @return array<string, mixed>
     */
    private function checkCache(): array
    {
        try {
            $key = 'kmp_update_smoketest';
            $value = bin2hex(random_bytes(8));
            Cache::write($key, $value, 'default');
            $read = Cache::read($key, 'default');
            if ($read !== $value) {
                throw new \RuntimeException('Cache write/read mismatch');
            }
            Cache::delete($key, 'default');

            return ['passed' => true, 'message' => 'Cache service is healthy'];
        } catch (\Throwable $e) {
            return ['passed' => false, 'message' => 'Cache check failed: ' . $e->getMessage()];
        }
    }

    /**
     * Validate settings and maintenance flags are readable.
     *
     * @return array<string, mixed>
     */
    private function checkAppSettings(): array
    {
        try {
            $version = StaticHelpers::getAppSetting('App.version', 'unknown');
            return ['passed' => true, 'message' => 'App settings are readable', 'value' => $version];
        } catch (\Throwable $e) {
            return ['passed' => false, 'message' => 'App settings check failed: ' . $e->getMessage()];
        }
    }

    /**
     * Validate configured document storage mode.
     *
     * @return array<string, mixed>
     */
    private function checkStorage(): array
    {
        try {
            $storageConfig = StaticHelpers::getAppSetting('Documents.storage', []);
            if (!is_array($storageConfig)) {
                throw new \RuntimeException('Storage config missing or invalid');
            }

            if (empty($storageConfig['adapter'])) {
                throw new \RuntimeException('Storage adapter is not configured');
            }

            return ['passed' => true, 'message' => 'Storage is configured as ' . $storageConfig['adapter']];
        } catch (\Throwable $e) {
            return ['passed' => false, 'message' => 'Storage check failed: ' . $e->getMessage()];
        }
    }
}
