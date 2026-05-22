<?php
declare(strict_types=1);

// phpcs:disable CakePHP.Commenting.FunctionComment.Missing, Generic.PHP.NoSilencedErrors.Discouraged

namespace App\Services\Backups;

use App\KMP\TenantMetadata;
use App\Services\Secrets\SensitiveString;
use RuntimeException;

class TenantBackupEncryptor
{
    public const DATA_ALGORITHM = 'AES-256-GCM';
    private const WRAP_ALGORITHM = 'AES-256-GCM';

    public function encryptFile(
        string $inputPath,
        string $outputPath,
        TenantMetadata $tenant,
        string $backupId,
        SensitiveString $kek,
        string $kekName,
        string $kekVersion,
    ): TenantBackupEncryptionResult {
        if (!is_file($inputPath)) {
            throw new RuntimeException('Plaintext backup file is missing.');
        }
        $plaintext = file_get_contents($inputPath);
        if ($plaintext === false) {
            throw new RuntimeException('Unable to read plaintext backup file.');
        }

        $dek = random_bytes(32);
        $dataIv = random_bytes(12);
        $dataTag = '';
        $aad = $this->aad($tenant, $backupId);
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::DATA_ALGORITHM,
            $dek,
            OPENSSL_RAW_DATA,
            $dataIv,
            $dataTag,
            $aad,
        );
        if ($ciphertext === false) {
            throw new RuntimeException('Unable to encrypt backup bytes.');
        }

        $wrapIv = random_bytes(12);
        $wrapTag = '';
        $wrappedDekBytes = openssl_encrypt(
            $dek,
            self::WRAP_ALGORITHM,
            $this->normalizeKek($kek),
            OPENSSL_RAW_DATA,
            $wrapIv,
            $wrapTag,
            $aad,
        );
        if ($wrappedDekBytes === false) {
            throw new RuntimeException('Unable to wrap backup data-encryption key.');
        }

        $payload = [
            'version' => 1,
            'algorithm' => self::DATA_ALGORITHM,
            'tenant_id' => $tenant->id,
            'backup_id' => $backupId,
            'iv' => base64_encode($dataIv),
            'tag' => base64_encode($dataTag),
            'ciphertext' => base64_encode($ciphertext),
        ];
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (file_put_contents($outputPath, $json, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write encrypted backup file.');
        }
        @chmod($outputPath, 0660);

        return new TenantBackupEncryptionResult(
            $outputPath,
            self::DATA_ALGORITHM,
            base64_encode($wrappedDekBytes),
            [
                'wrap_algorithm' => self::WRAP_ALGORITHM,
                'wrap_iv' => base64_encode($wrapIv),
                'wrap_tag' => base64_encode($wrapTag),
                'aad' => base64_encode($aad),
                'kek_name' => $kekName,
                'kek_version' => $kekVersion,
            ],
        );
    }

    /**
     * Test/support helper to verify a stored encrypted backup can be decrypted.
     *
     * @param array<string, mixed> $wrappedDekMetadata
     */
    public function decryptFileForTest(
        string $encryptedPath,
        string $wrappedDek,
        array $wrappedDekMetadata,
        SensitiveString $kek,
    ): string {
        $payload = json_decode((string)file_get_contents($encryptedPath), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload)) {
            throw new RuntimeException('Encrypted backup payload is invalid.');
        }
        $aad = base64_decode((string)$wrappedDekMetadata['aad'], true);
        $wrapIv = base64_decode((string)$wrappedDekMetadata['wrap_iv'], true);
        $wrapTag = base64_decode((string)$wrappedDekMetadata['wrap_tag'], true);
        $wrappedDekBytes = base64_decode($wrappedDek, true);
        if ($aad === false || $wrapIv === false || $wrapTag === false || $wrappedDekBytes === false) {
            throw new RuntimeException('Wrapped DEK metadata is invalid.');
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
            throw new RuntimeException('Unable to unwrap backup data-encryption key.');
        }

        $iv = base64_decode((string)$payload['iv'], true);
        $tag = base64_decode((string)$payload['tag'], true);
        $ciphertext = base64_decode((string)$payload['ciphertext'], true);
        if ($iv === false || $tag === false || $ciphertext === false) {
            throw new RuntimeException('Encrypted backup payload is malformed.');
        }
        $plaintext = openssl_decrypt(
            $ciphertext,
            self::DATA_ALGORITHM,
            $dek,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad,
        );
        if ($plaintext === false) {
            throw new RuntimeException('Unable to decrypt backup bytes.');
        }

        return $plaintext;
    }

    /**
     * Decrypt an encrypted backup payload into a plaintext pg_dump file.
     *
     * @param array<string, mixed> $wrappedDekMetadata Wrapped DEK metadata
     */
    public function decryptFile(
        string $encryptedPath,
        string $outputPath,
        string $wrappedDek,
        array $wrappedDekMetadata,
        SensitiveString $kek,
    ): void {
        if ($outputPath === '' || str_contains($outputPath, "\0")) {
            throw new RuntimeException('Unsafe decrypted backup output path.');
        }
        $plaintext = $this->decryptFileForTest($encryptedPath, $wrappedDek, $wrappedDekMetadata, $kek);
        if (file_put_contents($outputPath, $plaintext, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write decrypted backup file.');
        }
        @chmod($outputPath, 0660);
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

    private function aad(TenantMetadata $tenant, string $backupId): string
    {
        return $tenant->id . '|' . $tenant->slug . '|' . $backupId;
    }
}
