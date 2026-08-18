<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Command;

use App\Services\ServiceResult;
use Awards\Command\CloseHistoricalBestowalsCommand;
use Awards\Services\HistoricalBestowalCloseoutService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\TestSuite\TestCase;

class CloseHistoricalBestowalsCommandTest extends TestCase
{
    private const MANIFEST_HASH = '7ae9fa60c9ea59dbc3ea24575ed9cd43cd93a3fb6264595f28883c82b6e4bc4b';

    public function testDryRunDelegatesAndPrintsDeterministicSummary(): void
    {
        $service = $this->createMock(HistoricalBestowalCloseoutService::class);
        $service->expects($this->once())
            ->method('run')
            ->with(
                '/app/plugins/Awards/config/remediations/manifest.json',
                self::MANIFEST_HASH,
                249,
                42,
                'ansteorra',
                'CHANGE-123',
                false,
            )
            ->willReturn(new ServiceResult(true, null, $this->resultData()));
        $command = new CloseHistoricalBestowalsCommand($service);
        ['io' => $io, 'out' => $out] = $this->consoleIo();

        $status = $command->execute($this->arguments(), $io);

        $this->assertSame(Command::CODE_SUCCESS, $status);
        $output = implode("\n", $out->messages());
        $this->assertStringContainsString('Mode: dry-run', $output);
        $this->assertStringContainsString('Manifest SHA-256: ' . self::MANIFEST_HASH, $output);
        $this->assertSummaryOrder($output);
        $this->assertStringContainsString('Historical bestowal closeout dry run completed.', $output);
    }

    public function testApplyDelegatesOnlyWithExactConfirmation(): void
    {
        $service = $this->createMock(HistoricalBestowalCloseoutService::class);
        $service->expects($this->once())
            ->method('run')
            ->with(
                '/app/plugins/Awards/config/remediations/manifest.json',
                self::MANIFEST_HASH,
                249,
                42,
                'ansteorra',
                'CHANGE-123',
                true,
            )
            ->willReturn(new ServiceResult(true, null, $this->resultData([
                'actionable' => 0,
                'changed' => 249,
            ])));
        $command = new CloseHistoricalBestowalsCommand($service);
        ['io' => $io, 'out' => $out] = $this->consoleIo();

        $status = $command->execute($this->arguments([
            'apply' => true,
            'confirm' => CloseHistoricalBestowalsCommand::APPLY_CONFIRMATION,
        ]), $io);

        $this->assertSame(Command::CODE_SUCCESS, $status);
        $this->assertStringContainsString('Mode: apply', implode("\n", $out->messages()));
    }

    public function testApplyRejectsMissingOrIncorrectConfirmation(): void
    {
        $service = $this->createMock(HistoricalBestowalCloseoutService::class);
        $service->expects($this->never())->method('run');
        $command = new CloseHistoricalBestowalsCommand($service);
        ['io' => $io, 'err' => $err] = $this->consoleIo();

        $status = $command->execute($this->arguments([
            'apply' => true,
            'confirm' => 'wrong-batch',
        ]), $io);

        $this->assertSame(Command::CODE_ERROR, $status);
        $this->assertStringContainsString(
            '--apply requires --confirm ' . CloseHistoricalBestowalsCommand::APPLY_CONFIRMATION,
            implode("\n", $err->messages()),
        );
    }

    public function testMissingRequiredOptionsFailBeforeDelegation(): void
    {
        $service = $this->createMock(HistoricalBestowalCloseoutService::class);
        $service->expects($this->never())->method('run');
        $command = new CloseHistoricalBestowalsCommand($service);
        ['io' => $io, 'err' => $err] = $this->consoleIo();

        $status = $command->execute($this->arguments([
            'tenant' => null,
            'manifest' => null,
            'expect-manifest-sha256' => null,
            'expected-apply-count' => null,
            'actor-id' => null,
            'change-reference' => null,
        ]), $io);

        $this->assertSame(Command::CODE_ERROR, $status);
        $errors = implode("\n", $err->messages());
        $this->assertStringContainsString('--tenant', $errors);
        $this->assertStringContainsString('--manifest is required', $errors);
        $this->assertStringContainsString('--expect-manifest-sha256', $errors);
        $this->assertStringContainsString('--expected-apply-count', $errors);
        $this->assertStringContainsString('--actor-id', $errors);
        $this->assertStringContainsString('--change-reference is required', $errors);
    }

    public function testInvalidRequiredOptionsFailBeforeDelegation(): void
    {
        $service = $this->createMock(HistoricalBestowalCloseoutService::class);
        $service->expects($this->never())->method('run');
        $command = new CloseHistoricalBestowalsCommand($service);
        ['io' => $io, 'err' => $err] = $this->consoleIo();

        $status = $command->execute($this->arguments([
            'tenant' => 'Ansteorra Production',
            'expect-manifest-sha256' => 'not-a-hash',
            'expected-apply-count' => '249records',
            'actor-id' => '-1',
        ]), $io);

        $this->assertSame(Command::CODE_ERROR, $status);
        $errors = implode("\n", $err->messages());
        $this->assertStringContainsString('lowercase tenant slug', $errors);
        $this->assertStringContainsString('64-character hexadecimal digest', $errors);
        $this->assertStringContainsString('apply-count must be a positive integer', $errors);
        $this->assertStringContainsString('actor-id must be a positive integer', $errors);
    }

    public function testServiceFailurePrintsSortedRecordErrorsAndReturnsError(): void
    {
        $service = $this->createMock(HistoricalBestowalCloseoutService::class);
        $service->method('run')->willReturn(new ServiceResult(false, 'Preflight drift detected.', $this->resultData([
            'actionable' => 247,
            'drift' => 2,
        ], [
            $this->record(9, 'apply', 'drift', 'Bestowal state changed.'),
            $this->record(3, 'apply', 'drift', 'Recommendation date changed.'),
        ])));
        $command = new CloseHistoricalBestowalsCommand($service);
        ['io' => $io, 'err' => $err] = $this->consoleIo();

        $status = $command->execute($this->arguments(), $io);

        $this->assertSame(Command::CODE_ERROR, $status);
        $errors = implode("\n", $err->messages());
        $firstPosition = strpos($errors, 'recommendation #3');
        $secondPosition = strpos($errors, 'recommendation #9');
        $this->assertNotFalse($firstPosition);
        $this->assertNotFalse($secondPosition);
        $this->assertLessThan($secondPosition, $firstPosition);
        $this->assertStringContainsString('Preflight drift detected.', $errors);
    }

    public function testSuccessfulServiceResultWithDriftReturnsError(): void
    {
        $service = $this->createMock(HistoricalBestowalCloseoutService::class);
        $service->method('run')->willReturn(new ServiceResult(true, null, $this->resultData([
            'actionable' => 248,
            'drift' => 1,
        ], [
            $this->record(7, 'apply', 'drift', 'Unexpected action item.'),
        ])));
        $command = new CloseHistoricalBestowalsCommand($service);
        ['io' => $io, 'err' => $err] = $this->consoleIo();

        $status = $command->execute($this->arguments(), $io);

        $this->assertSame(Command::CODE_ERROR, $status);
        $this->assertStringContainsString('found drift', implode("\n", $err->messages()));
    }

    public function testMissingSummaryKeyReturnsError(): void
    {
        $data = $this->resultData();
        unset($data['summary']['drift']);
        $service = $this->createMock(HistoricalBestowalCloseoutService::class);
        $service->method('run')->willReturn(new ServiceResult(true, null, $data));
        $command = new CloseHistoricalBestowalsCommand($service);
        ['io' => $io, 'out' => $out, 'err' => $err] = $this->consoleIo();

        $status = $command->execute($this->arguments(), $io);

        $this->assertSame(Command::CODE_ERROR, $status);
        $this->assertStringContainsString('drift: missing', implode("\n", $out->messages()));
        $this->assertStringContainsString('invalid result payload', implode("\n", $err->messages()));
    }

    /**
     * @param array<string, string|bool|null> $overrides Option overrides.
     * @return \Cake\Console\Arguments
     */
    private function arguments(array $overrides = []): Arguments
    {
        return new Arguments([], array_merge([
            'tenant' => 'ansteorra',
            'manifest' => '/app/plugins/Awards/config/remediations/manifest.json',
            'expect-manifest-sha256' => self::MANIFEST_HASH,
            'expected-apply-count' => '249',
            'actor-id' => '42',
            'change-reference' => 'CHANGE-123',
            'apply' => false,
            'confirm' => null,
        ], $overrides), []);
    }

    /**
     * @param array<string, int> $summaryOverrides Summary overrides.
     * @param list<array<string, int|string>> $records Record results.
     * @return array<string, mixed>
     */
    private function resultData(array $summaryOverrides = [], array $records = []): array
    {
        return [
            'manifestHash' => self::MANIFEST_HASH,
            'summary' => array_merge([
                'total' => 273,
                'apply' => 249,
                'hold' => 6,
                'alreadyGiven' => 17,
                'separateRepair' => 1,
                'actionable' => 249,
                'alreadyApplied' => 0,
                'changed' => 0,
                'drift' => 0,
            ], $summaryOverrides),
            'records' => $records,
        ];
    }

    /**
     * @return array{recommendationId: int, disposition: string, result: string, reason: string}
     */
    private function record(int $recommendationId, string $disposition, string $result, string $reason): array
    {
        return compact('recommendationId', 'disposition', 'result', 'reason');
    }

    /**
     * @return array{io: \Cake\Console\ConsoleIo, out: \Cake\Console\TestSuite\StubConsoleOutput,
     *   err: \Cake\Console\TestSuite\StubConsoleOutput}
     */
    private function consoleIo(): array
    {
        $out = new StubConsoleOutput();
        $err = new StubConsoleOutput();

        return [
            'io' => new ConsoleIo($out, $err),
            'out' => $out,
            'err' => $err,
        ];
    }

    private function assertSummaryOrder(string $output): void
    {
        $lastPosition = -1;
        foreach (
            [
                'total: 273',
                'apply: 249',
                'hold: 6',
                'alreadyGiven: 17',
                'separateRepair: 1',
                'actionable: 249',
                'alreadyApplied: 0',
                'changed: 0',
                'drift: 0',
            ] as $line
        ) {
            $position = strpos($output, $line);
            $this->assertNotFalse($position, 'Missing summary line: ' . $line);
            $this->assertGreaterThan($lastPosition, $position, 'Summary line is out of order: ' . $line);
            $lastPosition = $position;
        }
    }
}
