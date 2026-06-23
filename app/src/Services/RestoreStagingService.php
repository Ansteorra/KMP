<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Stores web-initiated restore payloads outside the database for CLI pickup.
 */
class RestoreStagingService
{
    private string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?? sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kmp_restore_staging';
        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new RuntimeException("Cannot create restore staging directory: {$this->directory}");
        }
        @chmod($this->directory, 0700);
    }

    public function stage(string $encryptedData, string $encryptionKey, array $context = []): string
    {
        $token = bin2hex(random_bytes(24));
        $payload = json_encode([
            'encrypted_data' => base64_encode($encryptedData),
            'encryption_key' => $encryptionKey,
            'context' => $context,
            'created_at' => date('c'),
        ], JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            throw new RuntimeException('Failed to encode restore staging payload.');
        }

        $path = $this->path($token);
        if (file_put_contents($path, $payload, LOCK_EX) === false) {
            throw new RuntimeException('Failed to write restore staging payload.');
        }
        @chmod($path, 0600);

        return $token;
    }

    /**
     * @return array{encrypted_data: string, encryption_key: string, context: array<string, mixed>}
     */
    public function consume(string $token): array
    {
        $path = $this->path($token);
        $json = is_file($path) ? file_get_contents($path) : false;
        if ($json === false) {
            throw new RuntimeException('Restore staging payload not found.');
        }
        @unlink($path);

        $payload = json_decode($json, true);
        if (!is_array($payload) || !isset($payload['encrypted_data'], $payload['encryption_key'])) {
            throw new RuntimeException('Restore staging payload is invalid.');
        }

        $encryptedData = base64_decode((string)$payload['encrypted_data'], true);
        if ($encryptedData === false) {
            throw new RuntimeException('Restore staging payload data is invalid.');
        }

        return [
            'encrypted_data' => $encryptedData,
            'encryption_key' => (string)$payload['encryption_key'],
            'context' => is_array($payload['context'] ?? null) ? $payload['context'] : [],
        ];
    }

    private function path(string $token): string
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
            throw new RuntimeException('Invalid restore staging token.');
        }

        return $this->directory . DIRECTORY_SEPARATOR . $token . '.json';
    }
}
