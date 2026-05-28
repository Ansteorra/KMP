<?php
declare(strict_types=1);

namespace App\Command;

use App\Log\Engine\ApplicationInsightsLog;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Log\Log;
use Throwable;

/**
 * Deploy-time validation for the KMP performance instrumentation stack.
 *
 * Prints the effective (non-secret) telemetry configuration, verifies the
 * LOGS directory is writable under the current uid, reports the PHP
 * runtime fingerprint that will be emitted on every performance trace,
 * and (with --send) writes a smoke trace through the Log facade so the
 * operator can confirm end-to-end delivery to Application Insights.
 */
class TelemetryCheckCommand extends Command
{
    /**
     * @return string Default command name as invoked from `bin/cake`.
     */
    public static function defaultName(): string
    {
        return 'telemetry_check';
    }

    /**
     * Configures CLI options for this command.
     *
     * @param \Cake\Console\ConsoleOptionParser $parser Parser to configure
     * @return \Cake\Console\ConsoleOptionParser
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser->setDescription('Validate KMP telemetry configuration and optionally send a smoke trace.');
        $parser->addOption('send', [
            'help' => 'Write a single info-level smoke trace through the Log facade.',
            'boolean' => true,
            'default' => false,
        ]);

        return $parser;
    }

    /**
     * Prints effective telemetry config, storage health, and (with --send)
     * writes a smoke trace.
     *
     * @param \Cake\Console\Arguments $args Parsed arguments
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return int|null CODE_SUCCESS on healthy config; CODE_ERROR on issues.
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $io->out('<info>KMP telemetry configuration</info>');
        $io->hr();

        $rows = $this->effectiveConfig();
        foreach ($rows as $label => $value) {
            $io->out(sprintf('  %-32s %s', $label, $value));
        }

        $io->out('');
        $io->out('<info>Runtime fingerprint</info>');
        $io->hr();
        $io->out(sprintf('  %-32s %s', 'PHP_VERSION', PHP_VERSION));
        $io->out(sprintf('  %-32s %s', 'PHP_SAPI', PHP_SAPI));
        $io->out(sprintf('  %-32s %s', 'PHP_OS_FAMILY', PHP_OS_FAMILY));
        $io->out(sprintf('  %-32s %s', 'hostname', (string)gethostname()));
        $io->out(sprintf('  %-32s %s', 'telemetry_schema_version', ApplicationInsightsLog::TELEMETRY_SCHEMA_VERSION));

        $io->out('');
        $io->out('<info>Storage</info>');
        $io->hr();
        $logsWritable = is_dir(LOGS) && is_writable(LOGS);
        $tmpWritable = is_dir(TMP) && is_writable(TMP);
        $io->out(sprintf('  %-32s %s', 'LOGS directory', LOGS));
        $io->out(sprintf('  %-32s %s', 'LOGS writable?', $logsWritable ? 'yes' : 'NO'));
        $io->out(sprintf('  %-32s %s', 'TMP directory', TMP));
        $io->out(sprintf('  %-32s %s', 'TMP writable?', $tmpWritable ? 'yes' : 'NO'));

        $exit = self::CODE_SUCCESS;
        if (!$logsWritable) {
            $io->err('<error>LOGS directory is not writable. File log channels will silently drop events.</error>');
            $exit = self::CODE_ERROR;
        }

        if ((bool)$args->getOption('send')) {
            $io->out('');
            $io->out('<info>Sending smoke trace</info>');
            try {
                Log::info(
                    '[telemetry_check] smoke trace from ' . (string)gethostname(),
                    ['scope' => ['app.performance']],
                );
                $io->out('  smoke trace written via Log::info (channel resolution depends on config)');
            } catch (Throwable $e) {
                $io->err('  failed to write smoke trace: ' . $e->getMessage());
                $exit = self::CODE_ERROR;
            }
        }

        return $exit;
    }

    /**
     * @return array<string, string>
     */
    private function effectiveConfig(): array
    {
        $boolEnv = static function (string $name, bool $default): string {
            $raw = (string)env($name, $default ? 'true' : 'false');

            return filter_var($raw, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
        };

        $connectionConfigured = trim((string)env('APPINSIGHTS_CONNECTION_STRING', '')) !== '';

        return [
            'PERF_REQUEST_LOG_ENABLED' => $boolEnv('PERF_REQUEST_LOG_ENABLED', false),
            'PERF_LOG_ALL_REQUESTS' => $boolEnv('PERF_LOG_ALL_REQUESTS', false),
            'PERF_SLOW_REQUEST_MS' => (string)env('PERF_SLOW_REQUEST_MS', '750'),
            'PERF_KINGDOM_TAG' => (string)env('PERF_KINGDOM_TAG', ''),
            'PERF_DB_QUERY_LOG_ENABLED' => $boolEnv('PERF_DB_QUERY_LOG_ENABLED', false),
            'APPINSIGHTS_CONNECTION_STRING' => $connectionConfigured ? '<configured>' : '<empty>',
            'APPINSIGHTS_LOG_ENABLED' => $boolEnv('APPINSIGHTS_LOG_ENABLED', false),
            'APPINSIGHTS_ERROR_LOG_ENABLED' => $boolEnv('APPINSIGHTS_ERROR_LOG_ENABLED', true),
            'APPINSIGHTS_QUERY_LOG_ENABLED' => $boolEnv('APPINSIGHTS_QUERY_LOG_ENABLED', false),
            'APPINSIGHTS_QUERY_SAMPLE_RATE' => (string)env('APPINSIGHTS_QUERY_SAMPLE_RATE', '10'),
            'APPINSIGHTS_LOG_BATCH_SIZE' => (string)env('APPINSIGHTS_LOG_BATCH_SIZE', '25'),
            'APPINSIGHTS_LOG_TIMEOUT' => (string)env('APPINSIGHTS_LOG_TIMEOUT', '2.0'),
            'APPINSIGHTS_CLOUD_ROLE' => (string)env('APPINSIGHTS_CLOUD_ROLE', 'kmp'),
            'LOG_QUERIES_FILE_SIZE' => (string)env('LOG_QUERIES_FILE_SIZE', '10MB'),
            'LOG_QUERIES_FILE_ROTATE' => (string)env('LOG_QUERIES_FILE_ROTATE', '5'),
            'LOG_PERFORMANCE_FILE_SIZE' => (string)env('LOG_PERFORMANCE_FILE_SIZE', '10MB'),
            'LOG_PERFORMANCE_FILE_ROTATE' => (string)env('LOG_PERFORMANCE_FILE_ROTATE', '5'),
        ];
    }
}
