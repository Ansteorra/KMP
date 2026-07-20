<?php
declare(strict_types=1);

namespace App\Command;

use App\Services\Platform\PlatformJobAlertService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;

/**
 * Emits platform job/schedule alert diagnostics for external monitors.
 */
class PlatformJobsCheckAlertsCommand extends Command
{
    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'platform jobs check-alerts';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->setDescription('Check platform job and schedule alert conditions.')
            ->addOption('json', [
                'help' => 'Emit diagnostics-safe JSON.',
                'boolean' => true,
            ])
            ->addOption('stale-minutes', [
                'help' => 'Minutes before a running job is stale.',
                'default' => (string)Configure::read('Platform.alerts.staleRunningMinutes', '60'),
            ])
            ->addOption('missing-success-minutes', [
                'help' => 'Default minutes before an enabled schedule missing success alerts.',
                'default' => (string)Configure::read('Platform.alerts.missingSuccessMinutes', '1440'),
            ])
            ->addOption('failure-threshold', [
                'help' => 'Failed jobs needed within the failure window.',
                'default' => (string)Configure::read('Platform.alerts.failureThreshold', '3'),
            ])
            ->addOption('failure-window-minutes', [
                'help' => 'Lookback window for repeated failed jobs.',
                'default' => (string)Configure::read('Platform.alerts.failureWindowMinutes', '60'),
            ]);
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $result = (new PlatformJobAlertService(
            (int)$args->getOption('stale-minutes'),
            (int)$args->getOption('missing-success-minutes'),
            (int)$args->getOption('failure-threshold'),
            (int)$args->getOption('failure-window-minutes'),
        ))->check();

        if ($args->getOption('json')) {
            $io->out((string)json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } elseif ($result['healthy']) {
            $io->out('Platform jobs/schedules: healthy');
        } else {
            $io->err(sprintf('Platform jobs/schedules: %d alert(s)', count($result['alerts'])));
            foreach ($result['alerts'] as $alert) {
                $io->out($this->formatAlert($alert));
            }
        }

        return $result['healthy'] ? self::CODE_SUCCESS : self::CODE_ERROR;
    }

    /**
     * @param array<string, mixed> $alert Alert payload
     * @return string Concise safe text line
     */
    private function formatAlert(array $alert): string
    {
        $name = (string)($alert['schedule_name'] ?? $alert['job_type'] ?? $alert['type']);
        $tenant = array_key_exists('tenant_id', $alert) && $alert['tenant_id'] !== null
            ? sprintf(' tenant=%s', (string)$alert['tenant_id'])
            : '';
        $error = !empty($alert['last_error']) ? sprintf(' error="%s"', (string)$alert['last_error']) : '';

        return sprintf(
            '%s severity=%s target=%s%s%s',
            (string)$alert['type'],
            (string)$alert['severity'],
            $name,
            $tenant,
            $error,
        );
    }
}
