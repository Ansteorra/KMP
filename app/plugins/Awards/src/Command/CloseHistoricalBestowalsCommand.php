<?php
declare(strict_types=1);

namespace Awards\Command;

use Awards\Services\HistoricalBestowalCloseoutService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

/**
 * Safely close a manifest-scoped set of historical bestowals.
 */
class CloseHistoricalBestowalsCommand extends Command
{
    public const APPLY_CONFIRMATION = 'ansteorra-historical-given-249';

    /**
     * @var list<string>
     */
    private const SUMMARY_KEYS = [
        'total',
        'apply',
        'hold',
        'alreadyGiven',
        'separateRepair',
        'actionable',
        'alreadyApplied',
        'changed',
        'drift',
    ];

    private HistoricalBestowalCloseoutService $closeoutService;

    /**
     * @param \Awards\Services\HistoricalBestowalCloseoutService|null $closeoutService Closeout workflow.
     */
    public function __construct(?HistoricalBestowalCloseoutService $closeoutService = null)
    {
        parent::__construct();
        $this->closeoutService = $closeoutService ?? new HistoricalBestowalCloseoutService();
    }

    /**
     * Build the guarded remediation command options.
     *
     * @param \Cake\Console\ConsoleOptionParser $parser Option parser.
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);
        $parser
            ->setDescription(
                'Dry-run or apply the manifest-scoped historical bestowal closeout remediation.',
            )
            ->addOption('tenant', [
                'help' => 'Tenant slug expected by the remediation manifest.',
                'required' => true,
            ])
            ->addOption('manifest', [
                'help' => 'Path to the version-controlled remediation JSON manifest.',
                'required' => true,
            ])
            ->addOption('expect-manifest-sha256', [
                'help' => 'Expected 64-character SHA-256 digest for the manifest.',
                'required' => true,
            ])
            ->addOption('expected-apply-count', [
                'help' => 'Exact number of manifest records expected to be eligible for apply.',
                'required' => true,
            ])
            ->addOption('actor-id', [
                'help' => 'Active or membership-verified adult member ID recorded as the remediation actor.',
                'required' => true,
            ])
            ->addOption('change-reference', [
                'help' => 'Release, ticket, or change-management reference recorded in audit entries.',
                'required' => true,
            ])
            ->addOption('apply', [
                'boolean' => true,
                'default' => false,
                'help' => 'Persist the closeout. Omit this option for the default read-only dry run.',
            ])
            ->addOption('confirm', [
                'help' => 'With --apply, must be exactly: ' . self::APPLY_CONFIRMATION,
            ]);

        return $parser;
    }

    /**
     * Execute the guarded historical closeout.
     *
     * @param \Cake\Console\Arguments $args Command arguments.
     * @param \Cake\Console\ConsoleIo $io Console IO.
     * @return int
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $options = $this->validatedOptions($args, $io);
        if ($options === null) {
            return Command::CODE_ERROR;
        }

        $result = $this->closeoutService->run(
            $options['manifest'],
            $options['expectedHash'],
            $options['expectedApplyCount'],
            $options['actorId'],
            $options['tenant'],
            $options['changeReference'],
            $options['apply'],
        );

        $data = $result->getData();
        if (!is_array($data)) {
            $io->out('Mode: ' . ($options['apply'] ? 'apply' : 'dry-run'));
            $io->out('Manifest SHA-256: missing');
            $io->err((string)($result->getError() ?? 'Closeout service returned an invalid result payload.'));

            return Command::CODE_ERROR;
        }

        $payloadValid = true;
        $manifestHash = $data['manifestHash'] ?? null;
        if ($manifestHash === '' && !$result->isSuccess()) {
            $manifestHash = 'missing';
        } elseif (!is_string($manifestHash) || !preg_match('/\A[a-f0-9]{64}\z/i', $manifestHash)) {
            $manifestHash = 'missing';
            $payloadValid = false;
        }

        $io->out('Mode: ' . ($options['apply'] ? 'apply' : 'dry-run'));
        $io->out('Manifest SHA-256: ' . $manifestHash);
        $io->out('Summary:');

        $summary = $data['summary'] ?? null;
        if (!is_array($summary)) {
            $summary = [];
            $payloadValid = false;
        }

        $summaryValues = [];
        foreach (self::SUMMARY_KEYS as $key) {
            $value = $summary[$key] ?? null;
            if (!is_int($value) || $value < 0) {
                $io->out($key . ': missing');
                $payloadValid = false;
                continue;
            }

            $summaryValues[$key] = $value;
            $io->out(sprintf('%s: %d', $key, $value));
        }

        $records = $data['records'] ?? null;
        if (!is_array($records)) {
            $records = [];
            $payloadValid = false;
        }
        $payloadValid = $this->printRecordErrors($records, $io) && $payloadValid;

        if (!$payloadValid) {
            $io->err('Closeout service returned an invalid result payload.');

            return Command::CODE_ERROR;
        }

        if (!$result->isSuccess()) {
            $io->err((string)($result->getError() ?? 'Historical bestowal closeout failed.'));

            return Command::CODE_ERROR;
        }

        if (($summaryValues['drift'] ?? 0) > 0) {
            $io->err('Historical bestowal closeout found drift; no clean result was produced.');

            return Command::CODE_ERROR;
        }

        $io->success($options['apply']
            ? 'Historical bestowal closeout completed.'
            : 'Historical bestowal closeout dry run completed.');

        return Command::CODE_SUCCESS;
    }

    /**
     * Validate options even when execute() is invoked directly by a test or operator wrapper.
     *
     * @param \Cake\Console\Arguments $args Command arguments.
     * @param \Cake\Console\ConsoleIo $io Console IO.
     * @return array{
     *   tenant: string,
     *   manifest: string,
     *   expectedHash: string,
     *   expectedApplyCount: int,
     *   actorId: int,
     *   changeReference: string,
     *   apply: bool
     * }|null
     */
    private function validatedOptions(Arguments $args, ConsoleIo $io): ?array
    {
        $errors = [];
        $tenant = trim((string)$args->getOption('tenant'));
        if ($tenant === '' || !preg_match('/\A[a-z0-9][a-z0-9-]*\z/', $tenant)) {
            $errors[] = '--tenant must be a non-empty lowercase tenant slug.';
        }

        $manifest = trim((string)$args->getOption('manifest'));
        if ($manifest === '') {
            $errors[] = '--manifest is required.';
        }

        $expectedHash = strtolower(trim((string)$args->getOption('expect-manifest-sha256')));
        if (!preg_match('/\A[a-f0-9]{64}\z/', $expectedHash)) {
            $errors[] = '--expect-manifest-sha256 must be a 64-character hexadecimal digest.';
        }

        $expectedApplyCountOption = $args->getOption('expected-apply-count');
        if (!$this->isPositiveInteger($expectedApplyCountOption)) {
            $errors[] = '--expected-apply-count must be a positive integer.';
        }
        $expectedApplyCount = (int)$expectedApplyCountOption;

        $actorIdOption = $args->getOption('actor-id');
        if (!$this->isPositiveInteger($actorIdOption)) {
            $errors[] = '--actor-id must be a positive integer.';
        }
        $actorId = (int)$actorIdOption;

        $changeReference = trim((string)$args->getOption('change-reference'));
        if ($changeReference === '') {
            $errors[] = '--change-reference is required.';
        }

        $apply = (bool)$args->getOption('apply');
        if ($apply && $args->getOption('confirm') !== self::APPLY_CONFIRMATION) {
            $errors[] = '--apply requires --confirm ' . self::APPLY_CONFIRMATION . '.';
        }

        if ($errors !== []) {
            foreach ($errors as $error) {
                $io->err('Option error: ' . $error);
            }

            return null;
        }

        return [
            'tenant' => $tenant,
            'manifest' => $manifest,
            'expectedHash' => $expectedHash,
            'expectedApplyCount' => $expectedApplyCount,
            'actorId' => $actorId,
            'changeReference' => $changeReference,
            'apply' => $apply,
        ];
    }

    /**
     * @param string|bool|null $value Option value.
     * @return bool
     */
    private function isPositiveInteger(string|bool|null $value): bool
    {
        return is_string($value) && ctype_digit($value) && (int)$value > 0;
    }

    /**
     * Print drift records in recommendation ID order.
     *
     * @param array<array-key, mixed> $records Service result records.
     * @param \Cake\Console\ConsoleIo $io Console IO.
     * @return bool Whether every record had the expected shape.
     */
    private function printRecordErrors(array $records, ConsoleIo $io): bool
    {
        $valid = true;
        $driftRecords = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                $valid = false;
                continue;
            }

            $recommendationId = $record['recommendationId'] ?? null;
            $disposition = $record['disposition'] ?? null;
            $recordResult = $record['result'] ?? null;
            $reason = $record['reason'] ?? null;
            if (
                !is_int($recommendationId)
                || $recommendationId < 1
                || !is_string($disposition)
                || $disposition === ''
                || !is_string($recordResult)
                || !is_string($reason)
            ) {
                $valid = false;
                continue;
            }

            if ($recordResult === 'drift') {
                $driftRecords[] = $record;
            }
        }

        usort(
            $driftRecords,
            fn(array $left, array $right): int => $left['recommendationId'] <=> $right['recommendationId'],
        );
        foreach ($driftRecords as $record) {
            $io->err(sprintf(
                'Record error: recommendation #%d [%s]: %s',
                $record['recommendationId'],
                $record['disposition'],
                $record['reason'],
            ));
        }

        return $valid;
    }
}
