<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Backups;

use App\Services\Backups\BackupStreamCipher;
use App\Services\Backups\TenantBackupEncryptor;
use App\Services\Secrets\SensitiveString;
use Cake\TestSuite\TestCase;
use RuntimeException;

class BackupStreamCipherTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = TMP . 'backup-stream-cipher-' . uniqid('', true);
        mkdir($this->root, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            unlink($path);
        }
        rmdir($this->root);
        parent::tearDown();
    }

    public function testLargeArchiveRoundTripUsesAuthenticatedFrames(): void
    {
        $plaintextPath = $this->root . DIRECTORY_SEPARATOR . 'large.pgdump';
        $encryptedPath = $this->root . DIRECTORY_SEPARATOR . 'large.pgdump.enc';
        $decryptedPath = $this->root . DIRECTORY_SEPARATOR . 'large-restored.pgdump';
        $plaintext = str_repeat('0123456789abcdef', 600_000);
        file_put_contents($plaintextPath, $plaintext);
        $key = random_bytes(SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES);
        $aad = 'tenant-id|example|backup-id';
        $cipher = new BackupStreamCipher();

        $cipher->encryptFile($plaintextPath, $encryptedPath, $key, $aad, [
            'scope' => 'tenant',
            'tenant_id' => 'tenant-id',
            'backup_id' => 'backup-id',
        ]);
        $streamed = $cipher->decryptFile($encryptedPath, $decryptedPath, $key, $aad, [
            'scope' => 'tenant',
            'tenant_id' => 'tenant-id',
            'backup_id' => 'backup-id',
        ]);

        $this->assertTrue($streamed);
        $this->assertSame(hash_file('sha256', $plaintextPath), hash_file('sha256', $decryptedPath));
        $this->assertSame(filesize($plaintextPath), filesize($decryptedPath));
        $this->assertSame(
            BackupStreamCipher::MAGIC,
            file_get_contents($encryptedPath, false, null, 0, strlen(BackupStreamCipher::MAGIC)),
        );
    }

    public function testTamperedArchiveIsRejectedAndPartialPlaintextIsRemoved(): void
    {
        $plaintextPath = $this->root . DIRECTORY_SEPARATOR . 'plain.pgdump';
        $encryptedPath = $this->root . DIRECTORY_SEPARATOR . 'tampered.pgdump.enc';
        $decryptedPath = $this->root . DIRECTORY_SEPARATOR . 'tampered-restored.pgdump';
        file_put_contents($plaintextPath, str_repeat('backup-data', 1000));
        $key = random_bytes(SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES);
        $cipher = new BackupStreamCipher();
        $metadata = ['scope' => 'platform', 'backup_id' => 'backup-id'];
        $cipher->encryptFile($plaintextPath, $encryptedPath, $key, 'platform|metadata|backup-id', $metadata);

        $handle = fopen($encryptedPath, 'r+b');
        $this->assertIsResource($handle);
        fseek($handle, -1, SEEK_END);
        $lastByte = fread($handle, 1);
        fseek($handle, -1, SEEK_END);
        fwrite($handle, chr(ord((string)$lastByte) ^ 0x01));
        fclose($handle);

        try {
            $cipher->decryptFile(
                $encryptedPath,
                $decryptedPath,
                $key,
                'platform|metadata|backup-id',
                $metadata,
            );
            $this->fail('Expected tampered archive authentication to fail.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('authentication failed', $exception->getMessage());
        }
        $this->assertFileDoesNotExist($decryptedPath);
    }

    public function testArchiveWithTrailingDataIsRejectedAndPartialPlaintextIsRemoved(): void
    {
        $plaintextPath = $this->root . DIRECTORY_SEPARATOR . 'plain-with-trailing-data.pgdump';
        $encryptedPath = $this->root . DIRECTORY_SEPARATOR . 'trailing-data.pgdump.enc';
        $decryptedPath = $this->root . DIRECTORY_SEPARATOR . 'trailing-data-restored.pgdump';
        file_put_contents($plaintextPath, 'backup data');
        $key = random_bytes(SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES);
        $cipher = new BackupStreamCipher();
        $metadata = ['scope' => 'platform', 'backup_id' => 'backup-id'];
        $cipher->encryptFile($plaintextPath, $encryptedPath, $key, 'platform|metadata|backup-id', $metadata);
        file_put_contents($encryptedPath, 'unexpected', FILE_APPEND);

        try {
            $cipher->decryptFile(
                $encryptedPath,
                $decryptedPath,
                $key,
                'platform|metadata|backup-id',
                $metadata,
            );
            $this->fail('Expected archive trailing data to be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('data after its final frame', $exception->getMessage());
        }
        $this->assertFileDoesNotExist($decryptedPath);
    }

    public function testTenantDecryptorRetainsLegacyAesGcmCompatibility(): void
    {
        $encryptedPath = $this->root . DIRECTORY_SEPARATOR . 'legacy.pgdump.enc.json';
        $decryptedPath = $this->root . DIRECTORY_SEPARATOR . 'legacy-restored.pgdump';
        $plaintext = 'legacy encrypted tenant backup';
        $backupId = '11111111-1111-4111-8111-111111111111';
        $aad = 'tenant-id|example|' . $backupId;
        $kek = new SensitiveString('legacy-kek');
        $normalizedKek = hash('sha256', $kek->reveal(), true);
        $dek = random_bytes(32);
        $dataIv = random_bytes(12);
        $dataTag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            TenantBackupEncryptor::LEGACY_DATA_ALGORITHM,
            $dek,
            OPENSSL_RAW_DATA,
            $dataIv,
            $dataTag,
            $aad,
        );
        $wrapIv = random_bytes(12);
        $wrapTag = '';
        $wrappedDek = openssl_encrypt(
            $dek,
            'AES-256-GCM',
            $normalizedKek,
            OPENSSL_RAW_DATA,
            $wrapIv,
            $wrapTag,
            $aad,
        );
        file_put_contents($encryptedPath, json_encode([
            'version' => 1,
            'algorithm' => TenantBackupEncryptor::LEGACY_DATA_ALGORITHM,
            'tenant_id' => 'tenant-id',
            'backup_id' => $backupId,
            'iv' => base64_encode($dataIv),
            'tag' => base64_encode($dataTag),
            'ciphertext' => base64_encode((string)$ciphertext),
        ], JSON_THROW_ON_ERROR));

        (new TenantBackupEncryptor())->decryptFile(
            $encryptedPath,
            $decryptedPath,
            base64_encode((string)$wrappedDek),
            [
                'wrap_algorithm' => 'AES-256-GCM',
                'wrap_iv' => base64_encode($wrapIv),
                'wrap_tag' => base64_encode($wrapTag),
                'aad' => base64_encode($aad),
            ],
            $kek,
        );

        $this->assertSame($plaintext, file_get_contents($decryptedPath));
    }
}
