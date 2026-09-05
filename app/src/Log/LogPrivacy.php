<?php
declare(strict_types=1);

namespace App\Log;

use Stringable;

/** Shared, bounded sanitation before interpolation or serialization at any log sink. */
final class LogPrivacy
{
    private const SAFE_FIELDS = [
        'scope', 'event', 'channel', 'level', 'request_id', 'correlation_id', 'trace_id',
        'method', 'request_method', 'host', 'request_host', 'route_template', 'status',
        'duration_ms', 'memory_peak_mb', 'cpu_user_ms', 'cpu_sys_ms', 'query_count', 'db_total_ms',
        'response_bytes', 'kingdom', 'php_version', 'php_sapi', 'php_os', 'app_version',
        'file_size', 'count', 'skipped_count', 'processed_pages', 'waiver_images_count',
        'gathering_waiver_id', 'waiver_type_id', 'tenant_id', 'member_id', 'job_id',
        'request_query_number', 'query_number', 'turbo_frame', 'is_ajax', 'success',
        'connection', 'role', 'took', 'numRows',
    ];

    /** @return array<string, mixed> */
    public static function context(array $context, int $depth = 0): array
    {
        if ($depth > 4) {
            return ['redacted' => '[redacted]'];
        }
        $result = [];
        foreach (array_slice($context, 0, 64, true) as $key => $value) {
            $key = (string)$key;
            if (!preg_match('/^[a-zA-Z0-9_.-]{1,64}$/D', $key)) {
                continue;
            }
            if (in_array($key, ['path', 'request_path', 'request_target', 'url', 'referer'], true)) {
                $result[$key] = is_string($value) ? self::path($value) : '[redacted]';
            } elseif ($key === 'scope' && is_array($value)) {
                $result[$key] = array_map(
                    static fn($v): string => is_string($v) ? self::message($v) : '[redacted]',
                    array_slice($value, 0, 8),
                );
            } elseif (is_array($value)) {
                $result[$key] = self::context($value, $depth + 1);
            } elseif (in_array($key, self::SAFE_FIELDS, true) && (is_scalar($value) || $value === null)) {
                $result[$key] = is_string($value) ? self::message($value) : $value;
            } else {
                $result[$key] = '[redacted]';
            }
        }

        return $result;
    }

    /** Preserve route prefixes, excluding query strings and record/token values. */
    public static function path(string $target): string
    {
        $path = (string)(parse_url($target, PHP_URL_PATH) ?? '');
        $parts = explode('/', trim($path, '/'));
        $safe = [];
        foreach ($parts as $index => $part) {
            $safe[] = $index < 2 && preg_match('/^[a-z][a-z-]{0,40}$/D', $part) ? $part : ':value';
        }

        return '/' . implode('/', array_slice($safe, 0, 6));
    }

    /** Redact common credential and contact representations without serializing objects. */
    public static function message(Stringable|string $message): string
    {
        // Do not invoke user-controlled object stringification or entity JSON serializers.
        if (!is_string($message)) {
            return '[object omitted]';
        }
        $message = substr($message, 0, 32768);
        $message = preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', '', $message) ?? '[redacted]';
        $message = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[email]', $message) ?? '[redacted]';
        $message = preg_replace('/\bBearer\s+[^\s,;]+/i', 'Bearer [redacted]', $message) ?? '[redacted]';
        $message = preg_replace(
            '/\b(password|token|api[_-]?key|authorization|cookie|secret|notes?|file_header_(?:hex|text))'
            . '\s*[:=]\s*[^\r\n,;]*/i',
            '$1=[redacted]',
            $message,
        ) ?? '[redacted]';
        $message = preg_replace_callback(
            '~https?://[^\s<>"\']+~i',
            static fn(array $m): string => '[url]' . self::path($m[0]),
            $message,
        ) ?? '[redacted]';
        $message = preg_replace(
            '~(/(?:members/)?(?:reset-password|resetPassword|password-reset)/)[^\s?\'"<>]+~i',
            '$1[redacted]',
            $message,
        ) ?? '[redacted]';

        return $message;
    }
}
