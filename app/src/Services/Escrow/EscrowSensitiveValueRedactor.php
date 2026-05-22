<?php
declare(strict_types=1);

namespace App\Services\Escrow;

use App\Services\Secrets\SensitiveString;

final class EscrowSensitiveValueRedactor
{
    private const REDACTED = '[REDACTED]';

    /**
     * @param array<string, mixed> $metadata Metadata to sanitize
     * @return array<string, mixed>
     */
    public function redactMetadata(array $metadata): array
    {
        $redacted = [];
        foreach ($metadata as $key => $value) {
            $redacted[$key] = $this->isSensitiveKey((string)$key)
                ? self::REDACTED
                : $this->redactValue($value);
        }

        return $redacted;
    }

    /**
     * Redact sensitive key/value material from free-form ceremony notes.
     *
     * @param string|null $notes Notes to sanitize
     * @return string|null
     */
    public function redactNotes(?string $notes): ?string
    {
        if ($notes === null) {
            return null;
        }

        return preg_replace(
            '/\b(secret|kek|key|share|password|token|private)(\s*[:=]\s*)([^\s,;]+)/i',
            '$1$2' . self::REDACTED,
            $notes,
        );
    }

    /**
     * @param mixed $value Value to sanitize
     * @return mixed
     */
    private function redactValue(mixed $value): mixed
    {
        if ($value instanceof SensitiveString) {
            return self::REDACTED;
        }
        if (is_array($value)) {
            return $this->redactMetadata($value);
        }

        return $value;
    }

    /**
     * Detect metadata keys likely to contain KEK/share/plaintext material.
     *
     * @param string $key Metadata key
     * @return bool
     */
    private function isSensitiveKey(string $key): bool
    {
        return (bool)preg_match('/(secret|kek|key_material|plaintext|share|password|token|private)/i', $key);
    }
}
