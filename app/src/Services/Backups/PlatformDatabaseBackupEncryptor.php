<?php
declare(strict_types=1);

// phpcs:disable CakePHP.Commenting.FunctionComment.Missing, Generic.PHP.NoSilencedErrors.Discouraged

namespace App\Services\Backups;

use App\Services\Secrets\SensitiveString;
use RuntimeException;

class PlatformDatabaseBackupEncryptor
{
    public const DATA_ALGORITHM = BackupStreamCipher::ALGORITHM;
    private const WRAP_ALGORITHM = 'AES-256-GCM';

    public function __construct(private readonly ?BackupStreamCipher $streamCipher = null)
    {
    }

    public function encryptFile(
        string $inputPath,
        string $outputPath,
        string $backupId,
        SensitiveString $kek,
        string $kekName,
        string $kekVersion,
    ): TenantBackupEncryptionResult {
        $dek = random_bytes(SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES);
        $aad = $this->aad($backupId);
        try {
            ($this->streamCipher ?? new BackupStreamCipher())->encryptFile(
                $inputPath,
                $outputPath,
                $dek,
                $aad,
                [
                    'scope' => 'platform',
                    'backup_id' => $backupId,
                ],
            );
            [$wrappedDek, $metadata] = $this->wrapDek($dek, $aad, $kek, $kekName, $kekVersion);
        } finally {
            sodium_memzero($dek);
        }

        return new TenantBackupEncryptionResult(
            $outputPath,
            self::DATA_ALGORITHM,
            $wrappedDek,
            $metadata,
        );
    }

    /**
     * @param array<string, mixed> $wrappedDekMetadata
     */
    public function decryptFileForTest(
        string $encryptedPath,
        string $wrappedDek,
        array $wrappedDekMetadata,
        SensitiveString $kek,
    ): string {
        [$dek, $aad] = $this->unwrapDek($wrappedDek, $wrappedDekMetadata, $kek);
        $outputPath = tempnam(sys_get_temp_dir(), 'kmp-platform-decrypt-');
        if ($outputPath === false) {
            sodium_memzero($dek);
            throw new RuntimeException('Unable to create platform backup decryption test file.');
        }
        try {
            $aadParts = explode('|', $aad);
            if (count($aadParts) !== 3) {
                throw new RuntimeException('Platform backup authenticated metadata is invalid.');
            }
            $streamed = ($this->streamCipher ?? new BackupStreamCipher())->decryptFile(
                $encryptedPath,
                $outputPath,
                $dek,
                $aad,
                [
                    'scope' => 'platform',
                    'backup_id' => $aadParts[2],
                ],
            );
            if (!$streamed) {
                throw new RuntimeException('Legacy platform backup test decryption is unsupported.');
            }
            $plaintext = file_get_contents($outputPath);
            if ($plaintext === false) {
                throw new RuntimeException('Unable to read decrypted platform backup test file.');
            }

            return $plaintext;
        } finally {
            sodium_memzero($dek);
            if (is_file($outputPath)) {
                @unlink($outputPath);
            }
        }
    }

    /**
     * Unwrap the data key for one backup without exposing the reusable platform KEK.
     *
     * @param array<string, mixed> $wrappedDekMetadata Key wrapping metadata
     */
    public function exportDataEncryptionKey(
        string $wrappedDek,
        array $wrappedDekMetadata,
        SensitiveString $kek,
        string $backupId,
    ): SensitiveString {
        [$dek, $aad] = $this->unwrapDek($wrappedDek, $wrappedDekMetadata, $kek);
        if (!hash_equals($this->aad($backupId), $aad)) {
            sodium_memzero($dek);
            throw new RuntimeException('Platform backup key metadata does not match the selected backup.');
        }

        return new SensitiveString($dek);
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function wrapDek(
        string $dek,
        string $aad,
        SensitiveString $kek,
        string $kekName,
        string $kekVersion,
    ): array {
        $wrapIv = random_bytes(12);
        $wrapTag = '';
        $wrappedDek = openssl_encrypt(
            $dek,
            self::WRAP_ALGORITHM,
            $this->normalizeKek($kek),
            OPENSSL_RAW_DATA,
            $wrapIv,
            $wrapTag,
            $aad,
        );
        if ($wrappedDek === false) {
            throw new RuntimeException('Unable to wrap platform backup data-encryption key.');
        }

        return [
            base64_encode($wrappedDek),
            [
                'wrap_algorithm' => self::WRAP_ALGORITHM,
                'wrap_iv' => base64_encode($wrapIv),
                'wrap_tag' => base64_encode($wrapTag),
                'aad' => base64_encode($aad),
                'kek_name' => $kekName,
                'kek_version' => $kekVersion,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array{0: string, 1: string}
     */
    private function unwrapDek(string $wrappedDek, array $metadata, SensitiveString $kek): array
    {
        $aad = base64_decode((string)($metadata['aad'] ?? ''), true);
        $wrapIv = base64_decode((string)($metadata['wrap_iv'] ?? ''), true);
        $wrapTag = base64_decode((string)($metadata['wrap_tag'] ?? ''), true);
        $wrappedDekBytes = base64_decode($wrappedDek, true);
        if ($aad === false || $wrapIv === false || $wrapTag === false || $wrappedDekBytes === false) {
            throw new RuntimeException('Wrapped platform DEK metadata is invalid.');
        }
        $dek = openssl_decrypt(
            $wrappedDekBytes,
            self::WRAP_ALGORITHM,
            $this->normalizeKek($kek),
            OPENSSL_RAW_DATA,
            $wrapIv,
            $wrapTag,
            $aad,
        );
        if ($dek === false) {
            throw new RuntimeException('Unable to unwrap platform backup data-encryption key.');
        }
        if (strlen($dek) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
            sodium_memzero($dek);
            throw new RuntimeException('Unwrapped platform backup data-encryption key has an invalid length.');
        }

        return [$dek, $aad];
    }

    private function normalizeKek(SensitiveString $kek): string
    {
        $value = $kek->reveal();
        $decoded = base64_decode($value, true);
        if ($decoded !== false && strlen($decoded) === 32) {
            return $decoded;
        }
        if (preg_match('/^[a-f0-9]{64}$/i', $value)) {
            return hex2bin($value) ?: hash('sha256', $value, true);
        }

        return hash('sha256', $value, true);
    }

    private function aad(string $backupId): string
    {
        return 'platform|metadata|' . $backupId;
    }
}
