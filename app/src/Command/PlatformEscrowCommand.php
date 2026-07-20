<?php
declare(strict_types=1);

namespace App\Command;

use App\Services\Escrow\EscrowVerificationRequest;
use App\Services\Escrow\PlatformEscrowVerificationRecorder;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use JsonException;
use Throwable;

class PlatformEscrowCommand extends Command
{
    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'platform escrow';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->setDescription('Record platform KEK escrow ceremony metadata.')
            ->addArgument('action', [
                'help' => 'Escrow action to run.',
                'choices' => ['record-verification'],
            ])
            ->addOption('ceremony-id', [
                'help' => 'Existing escrow_ceremonies UUID, when known.',
            ])
            ->addOption('tenant-id', [
                'help' => 'Tenant UUID. Omit for platform/global secrets such as the secrets DB-driver KEK.',
            ])
            ->addOption('key-name', [
                'help' => 'Escrowed key name, e.g. tenant slug KEK or secrets-db-driver-kek.',
                'required' => true,
            ])
            ->addOption('key-version', [
                'help' => 'Escrowed key version identifier.',
                'required' => true,
            ])
            ->addOption('threshold', [
                'help' => 'Minimum shares required to reassemble the KEK.',
                'default' => '3',
            ])
            ->addOption('share-count', [
                'help' => 'Total number of sealed shares.',
                'default' => '5',
            ])
            ->addOption('verified-at', [
                'help' => 'Verification timestamp. Defaults to now in UTC.',
            ])
            ->addOption('verified-by-platform-user-id', [
                'help' => 'Platform user UUID that recorded or led the verification.',
            ])
            ->addOption('status', [
                'help' => 'Verification status, e.g. verified, failed, partial, deferred.',
                'default' => 'verified',
            ])
            ->addOption('metadata', [
                'help' => 'Non-sensitive JSON metadata. Sensitive keys are redacted before persistence.',
                'default' => '{}',
            ])
            ->addOption('notes', [
                'help' => 'Non-sensitive ceremony notes. Do not include KEKs or share plaintext.',
            ]);
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        if ($args->getArgument('action') !== 'record-verification') {
            $io->err('Unsupported escrow action.');

            return self::CODE_ERROR;
        }

        try {
            $metadata = $this->decodeMetadata((string)$args->getOption('metadata'));
            $request = new EscrowVerificationRequest(
                $this->nullableString($args->getOption('ceremony-id')),
                $this->nullableString($args->getOption('tenant-id')),
                (string)$args->getOption('key-name'),
                (string)$args->getOption('key-version'),
                (int)$args->getOption('threshold'),
                (int)$args->getOption('share-count'),
                $this->verifiedAt($this->nullableString($args->getOption('verified-at'))),
                $this->nullableString($args->getOption('verified-by-platform-user-id')),
                (string)$args->getOption('status'),
                $metadata,
                $this->nullableString($args->getOption('notes')),
            );

            $record = (new PlatformEscrowVerificationRecorder())->recordVerification($request);
        } catch (Throwable $exception) {
            $io->err('Failed to record escrow verification: ' . $exception->getMessage());

            return self::CODE_ERROR;
        }

        $io->success(sprintf(
            'Recorded escrow verification %s for %s version %s.',
            $record['id'],
            $record['key_name'],
            $record['key_version'],
        ));

        return self::CODE_SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeMetadata(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Metadata must be valid JSON.', 0, $exception);
        }
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Metadata JSON must decode to an object.');
        }

        return $decoded;
    }

    /**
     * Parse a verification timestamp into UTC.
     *
     * @param string|null $value Timestamp string
     * @return \DateTimeImmutable
     */
    private function verifiedAt(?string $value): DateTimeImmutable
    {
        $dateTime = $value === null ? new DateTimeImmutable('now') : new DateTimeImmutable($value);

        return $dateTime->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * Normalize empty CLI option values to null.
     *
     * @param mixed $value CLI option value
     * @return string|null
     */
    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string)$value;
    }
}
