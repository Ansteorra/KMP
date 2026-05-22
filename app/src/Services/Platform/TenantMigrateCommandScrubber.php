<?php
declare(strict_types=1);

namespace App\Services\Platform;

/**
 * Scrubs secret-like values from migration metadata before persistence.
 */
final class TenantMigrateCommandScrubber
{
    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    public static function scrubMetadata(array $metadata): array
    {
        $scrubbed = [];
        foreach ($metadata as $key => $value) {
            if (self::isSensitiveKey((string)$key)) {
                $scrubbed[$key] = '[redacted]';
                continue;
            }
            if (is_array($value)) {
                $scrubbed[$key] = self::scrubMetadata($value);
            } elseif (is_string($value)) {
                $scrubbed[$key] = self::scrubString($value);
            } else {
                $scrubbed[$key] = $value;
            }
        }

        return $scrubbed;
    }

    /**
     * Redact common secret patterns in free-form text.
     */
    public static function scrubString(string $message): string
    {
        $message = PlatformScheduleRunner::scrubError($message);
        $message = (string)preg_replace('/PGPASSWORD=[^\s]+/i', 'PGPASSWORD=[redacted]', $message);
        $message = (string)preg_replace(
            '/(postgres(?:ql)?:\/\/[^:\s\/]+:)[^@\s]+(@)/i',
            '$1[redacted]$2',
            $message,
        );

        return mb_substr($message, 0, 2000);
    }

    /**
     * Check whether a metadata key should have its entire value redacted.
     */
    private static function isSensitiveKey(string $key): bool
    {
        return (bool)preg_match('/password|passwd|pwd|secret|token|api[_-]?key|access[_-]?key|wrapped_dek|kek/i', $key);
    }
}
