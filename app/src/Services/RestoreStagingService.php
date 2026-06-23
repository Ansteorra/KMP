<?php
declare(strict_types=1);

namespace App\Services;

use Cake\Utility\Security;
use RuntimeException;

/**
 * Stores web-initiated restore payloads outside the database for CLI pickup.
 */
class RestoreStagingService
{
    private string $directory;

    /**
     * Initialize the restore staging directory.
     */
    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?? $this->defaultDirectory();
        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new RuntimeException("Cannot create restore staging directory: {$this->directory}");
        }
        @chmod($this->directory, 0700);
    }

    /**
     * Stage encrypted restore payload bytes and return a single-use token.
     *
     * @param array<string, mixed> $context
     */
    public function stage(string $encryptedData, string $encryptionKey, array $context = []): string
    {
        $token = bin2hex(random_bytes(24));
        $payload = json_encode([
            'encrypted_data' => base64_encode($encryptedData),
            'encrypted_key' => base64_encode(Security::encrypt($encryptionKey, $this->stagingEncryptionKey())),
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
        $claimedPath = $this->claimPath($path);
        if ($claimedPath === null) {
            throw new RuntimeException('Restore staging payload not found.');
        }

        $json = file_get_contents($claimedPath);
        if ($json === false) {
            throw new RuntimeException('Restore staging payload not found.');
        }

        $payload = json_decode($json, true);
        if (!is_array($payload) || !isset($payload['encrypted_data'], $payload['encrypted_key'])) {
            throw new RuntimeException('Restore staging payload is invalid.');
        }

        $encryptedData = base64_decode((string)$payload['encrypted_data'], true);
        if ($encryptedData === false) {
            throw new RuntimeException('Restore staging payload data is invalid.');
        }
        $encryptedKey = base64_decode((string)$payload['encrypted_key'], true);
        if ($encryptedKey === false) {
            throw new RuntimeException('Restore staging payload key is invalid.');
        }
        $encryptionKey = Security::decrypt($encryptedKey, $this->stagingEncryptionKey());
        if ($encryptionKey === null) {
            throw new RuntimeException('Restore staging payload key is invalid.');
        }

        return [
            'encrypted_data' => $encryptedData,
            'encryption_key' => $encryptionKey,
            'context' => is_array($payload['context'] ?? null) ? $payload['context'] : [],
        ];
    }

    /**
     * Delete a staged payload after the restore completes successfully.
     */
    public function discard(string $token): void
    {
        $path = $this->path($token);
        @unlink($path);
        foreach (glob($path . '.*.claimed') ?: [] as $claimedPath) {
            @unlink($claimedPath);
        }
    }

    /**
     * Build the staged payload file path for a token.
     */
    private function path(string $token): string
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
            throw new RuntimeException('Invalid restore staging token.');
        }

        return $this->directory . DIRECTORY_SEPARATOR . $token . '.json';
    }

    /**
     * Claim or reuse a previously claimed staging payload.
     */
    private function claimPath(string $path): ?string
    {
        $claimedPath = $path . '.' . bin2hex(random_bytes(4)) . '.claimed';
        if (@rename($path, $claimedPath)) {
            return $claimedPath;
        }

        $claimedPaths = glob($path . '.*.claimed') ?: [];

        return $claimedPaths[0] ?? null;
    }

    /**
     * Resolve the shared staging directory used by web and worker containers.
     */
    private function defaultDirectory(): string
    {
        $configuredDirectory = getenv('RESTORE_STAGING_DIR');
        if (is_string($configuredDirectory) && $configuredDirectory !== '') {
            return $configuredDirectory;
        }

        return TMP . 'restore_staging';
    }

    /**
     * Resolve the local staging encryption key.
     */
    private function stagingEncryptionKey(): string
    {
        $configuredKey = getenv('RESTORE_STAGING_KEY');
        $key = $configuredKey === false ? Security::getSalt() : (string)$configuredKey;
        if (strlen($key) < 32) {
            $key = str_pad($key, 32, '0');
        }

        return $key;
    }
}
