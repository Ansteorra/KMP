<?php
declare(strict_types=1);

namespace App\Command;

use App\Services\Backups\BackupRecoveryKeyService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use RuntimeException;

/**
 * Decrypts a platform database backup with its portable recovery-key file.
 */
final class PlatformBackupDecryptCommand extends Command
{
    private const CONFIRMATION = 'WRITE-PLAINTEXT-PLATFORM-BACKUP';

    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'platform backup decrypt';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->setDescription('Decrypt a platform database backup for external disaster recovery.')
            ->addOption('archive', [
                'help' => 'Path to the encrypted .pgdump.enc archive.',
                'required' => true,
            ])
            ->addOption('recovery-key', [
                'help' => 'Path to the matching .kmpbackup-key.json file.',
                'required' => true,
            ])
            ->addOption('output', [
                'help' => 'New path for the decrypted PostgreSQL custom-format dump.',
                'required' => true,
            ])
            ->addOption('confirm', [
                'help' => 'Required confirmation: ' . self::CONFIRMATION,
                'required' => true,
            ]);
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        try {
            if (!hash_equals(self::CONFIRMATION, (string)$args->getOption('confirm'))) {
                throw new RuntimeException(sprintf('Pass --confirm %s to decrypt the archive.', self::CONFIRMATION));
            }
            $archivePath = trim((string)$args->getOption('archive'));
            $keyPath = trim((string)$args->getOption('recovery-key'));
            $outputPath = trim((string)$args->getOption('output'));
            if ($archivePath === '' || $keyPath === '' || $outputPath === '') {
                throw new RuntimeException('Archive, recovery-key, and output paths are required.');
            }
            if (!is_file($keyPath) || !is_readable($keyPath)) {
                throw new RuntimeException('The platform backup recovery-key file could not be read.');
            }
            $keySize = filesize($keyPath);
            if ($keySize === false || $keySize <= 0 || $keySize > BackupRecoveryKeyService::MAX_KEY_FILE_BYTES) {
                throw new RuntimeException('The platform backup recovery-key file is empty or too large.');
            }
            $keyJson = file_get_contents($keyPath);
            if ($keyJson === false) {
                throw new RuntimeException('The platform backup recovery-key file could not be read.');
            }

            (new BackupRecoveryKeyService())->decryptPlatformArchiveFile(
                $archivePath,
                $keyJson,
                $outputPath,
            );
            $io->success(sprintf('Platform backup decrypted to %s.', $outputPath));

            return self::CODE_SUCCESS;
        } catch (RuntimeException $exception) {
            $io->err($exception->getMessage());

            return self::CODE_ERROR;
        }
    }
}
