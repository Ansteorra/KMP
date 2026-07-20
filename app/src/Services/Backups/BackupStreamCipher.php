<?php
declare(strict_types=1);

// phpcs:disable CakePHP.Commenting.FunctionComment.Missing, Generic.PHP.NoSilencedErrors.Discouraged

namespace App\Services\Backups;

use JsonException;
use RuntimeException;
use Throwable;

/**
 * Reads and writes bounded-memory, authenticated backup archive streams.
 */
final class BackupStreamCipher
{
    public const ALGORITHM = 'XCHACHA20-POLY1305-SECRETSTREAM';
    public const MAGIC = "KMPBACKUP\x02";
    private const CHUNK_BYTES = 4 * 1024 * 1024;
    private const MAX_HEADER_BYTES = 64 * 1024;

    /**
     * @param array<string, scalar> $archiveMetadata
     */
    public function encryptFile(
        string $inputPath,
        string $outputPath,
        string $dataEncryptionKey,
        string $aad,
        array $archiveMetadata,
    ): void {
        if (!is_file($inputPath)) {
            throw new RuntimeException('Plaintext backup file is missing.');
        }
        if (strlen($dataEncryptionKey) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
            throw new RuntimeException('Backup data-encryption key has an invalid length.');
        }

        $input = fopen($inputPath, 'rb');
        if (!is_resource($input)) {
            throw new RuntimeException('Unable to open plaintext backup file.');
        }
        $output = fopen($outputPath, 'wb');
        if (!is_resource($output)) {
            fclose($input);
            throw new RuntimeException('Unable to open encrypted backup file.');
        }

        $completed = false;
        try {
            [$state, $streamHeader] = sodium_crypto_secretstream_xchacha20poly1305_init_push($dataEncryptionKey);
            $header = json_encode([
                'version' => 2,
                'algorithm' => self::ALGORITHM,
                'stream_header' => base64_encode($streamHeader),
                'chunk_bytes' => self::CHUNK_BYTES,
            ] + $archiveMetadata, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if (strlen($header) > self::MAX_HEADER_BYTES) {
                throw new RuntimeException('Encrypted backup header exceeds the allowed size.');
            }

            $this->writeAll($output, self::MAGIC);
            $this->writeAll($output, pack('N', strlen($header)));
            $this->writeAll($output, $header);

            while (true) {
                $plaintext = fread($input, self::CHUNK_BYTES);
                if ($plaintext === false) {
                    throw new RuntimeException('Unable to read plaintext backup stream.');
                }
                $final = feof($input);
                $ciphertext = sodium_crypto_secretstream_xchacha20poly1305_push(
                    $state,
                    $plaintext,
                    $aad,
                    $final
                        ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL
                        : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE,
                );
                $this->writeAll($output, pack('N', strlen($ciphertext)));
                $this->writeAll($output, $ciphertext);
                if ($final) {
                    break;
                }
            }
            if (!fflush($output)) {
                throw new RuntimeException('Unable to flush encrypted backup stream.');
            }
            $completed = true;
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode encrypted backup header.', 0, $exception);
        } finally {
            fclose($input);
            fclose($output);
            if (!$completed && is_file($outputPath)) {
                @unlink($outputPath);
            }
        }
        @chmod($outputPath, 0600);
    }

    /**
     * Decrypt a version 2 archive. Returns false when the input uses the legacy JSON format.
     *
     * @param array<string, scalar> $expectedMetadata
     */
    public function decryptFile(
        string $inputPath,
        string $outputPath,
        string $dataEncryptionKey,
        string $aad,
        array $expectedMetadata,
    ): bool {
        $input = fopen($inputPath, 'rb');
        if (!is_resource($input)) {
            throw new RuntimeException('Unable to open encrypted backup file.');
        }
        $magic = fread($input, strlen(self::MAGIC));
        if ($magic !== self::MAGIC) {
            fclose($input);

            return false;
        }

        $output = null;
        $completed = false;
        try {
            $headerLength = $this->unpackLength($this->readExact($input, 4, 'backup header length'));
            if ($headerLength < 2 || $headerLength > self::MAX_HEADER_BYTES) {
                throw new RuntimeException('Encrypted backup header length is invalid.');
            }
            $header = json_decode(
                $this->readExact($input, $headerLength, 'backup header'),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
            if (
                !is_array($header)
                || (int)($header['version'] ?? 0) !== 2
                || (string)($header['algorithm'] ?? '') !== self::ALGORITHM
            ) {
                throw new RuntimeException('Encrypted backup header is unsupported.');
            }
            foreach ($expectedMetadata as $key => $expectedValue) {
                if (!array_key_exists($key, $header) || (string)$header[$key] !== (string)$expectedValue) {
                    throw new RuntimeException(sprintf('Encrypted backup header %s does not match metadata.', $key));
                }
            }
            $streamHeader = base64_decode((string)($header['stream_header'] ?? ''), true);
            if (
                $streamHeader === false
                || strlen($streamHeader) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES
            ) {
                throw new RuntimeException('Encrypted backup stream header is invalid.');
            }
            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull(
                $streamHeader,
                $dataEncryptionKey,
            );
            $output = fopen($outputPath, 'wb');
            if (!is_resource($output)) {
                throw new RuntimeException('Unable to open decrypted backup destination.');
            }

            $sawFinal = false;
            while (!$sawFinal) {
                $lengthBytes = fread($input, 4);
                if ($lengthBytes === false) {
                    throw new RuntimeException('Unable to read encrypted backup frame length.');
                }
                if ($lengthBytes === '') {
                    throw new RuntimeException('Encrypted backup ended before its authenticated final frame.');
                }
                if (strlen($lengthBytes) !== 4) {
                    $lengthBytes .= $this->readExact(
                        $input,
                        4 - strlen($lengthBytes),
                        'backup frame length',
                    );
                }
                $frameLength = $this->unpackLength($lengthBytes);
                $maximumFrameBytes = self::CHUNK_BYTES
                    + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES;
                if (
                    $frameLength < SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES
                    || $frameLength > $maximumFrameBytes
                ) {
                    throw new RuntimeException('Encrypted backup frame length is invalid.');
                }
                $result = sodium_crypto_secretstream_xchacha20poly1305_pull(
                    $state,
                    $this->readExact($input, $frameLength, 'backup frame'),
                    $aad,
                );
                if ($result === false) {
                    throw new RuntimeException('Encrypted backup authentication failed.');
                }
                [$plaintext, $tag] = $result;
                if (
                    $tag !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE
                    && $tag !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL
                ) {
                    throw new RuntimeException('Encrypted backup frame tag is unsupported.');
                }
                $this->writeAll($output, $plaintext);
                $sawFinal = $tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
            }
            $trailingByte = fread($input, 1);
            if ($trailingByte === false) {
                throw new RuntimeException('Unable to check encrypted backup for trailing data.');
            }
            if ($trailingByte !== '') {
                throw new RuntimeException('Encrypted backup contains data after its final frame.');
            }
            if (!fflush($output)) {
                throw new RuntimeException('Unable to flush decrypted backup stream.');
            }
            $completed = true;
        } catch (JsonException $exception) {
            throw new RuntimeException('Encrypted backup header is invalid.', 0, $exception);
        } catch (Throwable $exception) {
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }
            throw new RuntimeException('Unable to decrypt encrypted backup stream.', 0, $exception);
        } finally {
            fclose($input);
            if (is_resource($output)) {
                fclose($output);
            }
            if (!$completed && is_file($outputPath)) {
                @unlink($outputPath);
            }
        }
        @chmod($outputPath, 0600);

        return true;
    }

    /**
     * @param resource $stream
     */
    private function readExact(mixed $stream, int $length, string $label): string
    {
        $bytes = '';
        $bytesLength = 0;
        while ($bytesLength < $length) {
            $chunk = fread($stream, $length - $bytesLength);
            if ($chunk === false) {
                throw new RuntimeException(sprintf('Unable to read encrypted %s.', $label));
            }
            if ($chunk === '') {
                throw new RuntimeException(sprintf('Encrypted %s is truncated.', $label));
            }
            $bytes .= $chunk;
            $bytesLength += strlen($chunk);
        }

        return $bytes;
    }

    /**
     * @param resource $stream
     */
    private function writeAll(mixed $stream, string $bytes): void
    {
        $offset = 0;
        $length = strlen($bytes);
        while ($offset < $length) {
            $written = fwrite($stream, substr($bytes, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Unable to write encrypted backup stream.');
            }
            $offset += $written;
        }
    }

    private function unpackLength(string $bytes): int
    {
        $unpacked = unpack('Nlength', $bytes);
        if (!is_array($unpacked) || !isset($unpacked['length'])) {
            throw new RuntimeException('Encrypted backup frame length is invalid.');
        }

        return (int)$unpacked['length'];
    }
}
