<?php
declare(strict_types=1);

namespace Awards\Command;

use Awards\Services\BestowalLinkAuditService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

/**
 * Read-only diagnostic command for bestowal link consistency.
 *
 * Runs all audit checks from BestowalLinkAuditService and outputs counts and
 * sample IDs to the console. Does not mutate any data.
 */
class AuditBestowalLinksCommand extends Command
{
    private BestowalLinkAuditService $auditService;

    /**
     * @param \Awards\Services\BestowalLinkAuditService|null $auditService Optional injected audit service.
     */
    public function __construct(?BestowalLinkAuditService $auditService = null)
    {
        parent::__construct();
        $this->auditService = $auditService ?? new BestowalLinkAuditService();
    }

    /**
     * @param \Cake\Console\ConsoleOptionParser $parser Option parser
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);
        $parser
            ->setDescription(
                'Audit bestowal-to-recommendation link consistency without mutating data. '
                . 'Reports counts and sample IDs for each known inconsistency type.',
            )
            ->addOption('sample-limit', [
                'short' => 's',
                'help' => 'Number of sample rows to display per issue type.',
                'default' => '10',
            ]);

        return $parser;
    }

    /**
     * @param \Cake\Console\Arguments $args Console arguments
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return int Exit code
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $sampleLimit = (int)($args->getOption('sample-limit') ?? 10);

        $io->out('<info>Awards Bestowal Link Audit</info>');
        $io->hr();

        $results = $this->auditService->audit($sampleLimit);

        $totalIssues = 0;

        foreach ($results as $checkName => $result) {
            $count = $result['count'] ?? 0;
            $samples = $result['samples'] ?? [];
            $totalIssues += $count;

            $label = $this->humanizeKey($checkName);

            if ($count === 0) {
                $io->out(sprintf('<success>✓</success> %s: <success>0 issues</success>', $label));
                continue;
            }

            $io->out(sprintf('<warning>!</warning> %s: <warning>%d issue(s)</warning>', $label, $count));

            foreach ($samples as $sample) {
                $io->out('    ' . $this->formatSample($sample));
            }

            if ($count > count($samples)) {
                $io->out(sprintf('    ... and %d more.', $count - count($samples)));
            }
        }

        $io->hr();

        if ($totalIssues === 0) {
            $io->out('<success>All checks passed — no link inconsistencies found.</success>');

            return self::CODE_SUCCESS;
        }

        $io->out(sprintf('<warning>Total issues found: %d</warning>', $totalIssues));
        $io->out('Run with --sample-limit to see more sample rows per check.');

        return self::CODE_ERROR;
    }

    /**
     * Convert a camelCase audit key to a readable label.
     *
     * @param string $key Raw audit key
     * @return string Human-readable label
     */
    private function humanizeKey(string $key): string
    {
        // Split on uppercase letters and join with spaces.
        $words = preg_split('/(?=[A-Z])/', $key, -1, PREG_SPLIT_NO_EMPTY);

        return ucfirst(implode(' ', array_map('strtolower', $words ?: [$key])));
    }

    /**
     * Format a single sample row as a compact string.
     *
     * @param array<string, mixed> $sample Sample row from audit
     * @return string Formatted single-line representation
     */
    private function formatSample(array $sample): string
    {
        $parts = [];
        foreach ($sample as $key => $value) {
            $parts[] = $key . '=' . ($value ?? 'NULL');
        }

        return implode(', ', $parts);
    }
}
