<?php
declare(strict_types=1);

namespace App\Services\Platform\Audit;

use DateTimeImmutable;
use RuntimeException;

class FileWormAuditSink implements WormAuditSinkInterface
{
    private const REDACTED = '[redacted]';
    private const SENSITIVE_KEY_PATTERN =
        '/(password|secret|recovery[_-]?code|token|verifier|selector|access[_-]?key'
        . '|api[_-]?key|connection[_-]?string)/i';

    /**
     * Constructor.
     */
    public function __construct(private readonly string $path)
    {
        if (trim($this->path) === '') {
            throw new RuntimeException('WORM audit file path must not be empty.');
        }
    }

    /**
     * @inheritDoc
     */
    public function append(array $event): void
    {
        $directory = dirname($this->path);
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create WORM audit directory "%s".', $directory));
        }

        $handle = fopen($this->path, 'c+');
        if ($handle === false) {
            throw new RuntimeException(sprintf('Unable to open WORM audit file "%s".', $this->path));
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Unable to lock WORM audit file for append.');
            }

            $previousHash = $this->lastMirrorHash($handle);
            $sanitizedEvent = self::redact($event);
            $eventDigest = hash('sha256', self::canonicalJson($sanitizedEvent));
            $record = [
                'schema' => 'kmp.platform_audit_worm.v1',
                'written_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
                'event_digest' => $eventDigest,
                'mirror_previous_hash' => $previousHash,
                'event' => $sanitizedEvent,
            ];
            $record['mirror_hash'] = hash('sha256', self::canonicalJson($record));

            if (fseek($handle, 0, SEEK_END) !== 0) {
                throw new RuntimeException('Unable to seek WORM audit file for append.');
            }
            if (fwrite($handle, self::canonicalJson($record) . PHP_EOL) === false) {
                throw new RuntimeException('Unable to append WORM audit record.');
            }
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Redact sensitive fields before mirroring outside the platform database.
     *
     * @param mixed $value Value to redact
     * @return mixed
     */
    public static function redact(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $redacted = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && self::isSensitiveKey($key)) {
                $redacted[$key] = self::REDACTED;
                continue;
            }
            $redacted[$key] = self::redact($item);
        }

        return $redacted;
    }

    /**
     * Encode deterministic JSON for digest calculation.
     *
     * @param mixed $value Value to encode
     * @return string
     */
    public static function canonicalJson(mixed $value): string
    {
        $normalized = self::sortKeys($value);
        $json = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Unable to encode WORM audit JSON.');
        }

        return $json;
    }

    /**
     * Return the last mirror hash while the file lock is held.
     *
     * @param resource $handle
     * @return string|null
     */
    private function lastMirrorHash($handle): ?string
    {
        rewind($handle);
        $contents = stream_get_contents($handle);
        if ($contents === false || trim($contents) === '') {
            return null;
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($contents));
        if ($lines === false) {
            return null;
        }
        for ($index = count($lines) - 1; $index >= 0; $index--) {
            $line = trim($lines[$index]);
            if ($line === '') {
                continue;
            }
            $record = json_decode($line, true);
            if (is_array($record) && isset($record['mirror_hash'])) {
                return (string)$record['mirror_hash'];
            }
        }

        return null;
    }

    /**
     * Detect keys that should never be mirrored in plaintext.
     */
    private static function isSensitiveKey(string $key): bool
    {
        return preg_match(self::SENSITIVE_KEY_PATTERN, $key) === 1;
    }

    /**
     * Recursively sort associative-array keys.
     *
     * @param mixed $value Value to normalize
     * @return mixed
     */
    private static function sortKeys(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $isList = array_is_list($value);
        if (!$isList) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            $value[$key] = self::sortKeys($item);
        }

        return $value;
    }
}
