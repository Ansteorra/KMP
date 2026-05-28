<?php
declare(strict_types=1);

namespace App\KMP\Telemetry;

/**
 * Redacts user-provided data from SQL strings before they are exported to
 * Application Insights.
 *
 * The intent is to keep query shape (table names, joins, where-clauses,
 * structural literals) recognizable while removing values that might be
 * personally identifiable: bound parameter values, free-form string
 * literals, emails, IP addresses, and long hex/token-like sequences.
 *
 * Order matters: pattern-based redactions (emails, IPs, hex) must run
 * before generic string/number masking, otherwise they will already have
 * been collapsed to "?".
 */
final class SqlRedactor
{
    /**
     * Idempotent redaction. Calling this on already-redacted SQL is a no-op
     * for the value-removal step (a "?" placeholder stays a "?").
     *
     * @param string $sql Raw SQL message (may include bound-param dump)
     * @return string SQL with literal values replaced by typed placeholders
     */
    public static function redact(string $sql): string
    {
        // Strip bound-parameter dump appended by CakePHP/PDO loggers, of the
        // form "duration=...,...,params=[...]" or "params=[ ... ]".
        $sql = preg_replace('/\bparams\s*=\s*\[[^\]]*\]/i', 'params=<redacted>', $sql) ?? $sql;
        $sql = preg_replace('/\bbindings?\s*=\s*\{[^}]*\}/i', 'bindings=<redacted>', $sql) ?? $sql;

        // Email-shaped tokens (before string masking so "<email>" survives).
        $sql = preg_replace(
            '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/',
            '<email>',
            $sql,
        ) ?? $sql;

        // IPv4 addresses.
        $sql = preg_replace(
            '/\b(?:\d{1,3}\.){3}\d{1,3}\b/',
            '<ip>',
            $sql,
        ) ?? $sql;

        // Long hex / token-like sequences (>= 16 hex chars) such as auth
        // tokens, session ids, or UUIDs without dashes.
        $sql = preg_replace('/\b[a-f0-9]{16,}\b/i', '<hex>', $sql) ?? $sql;

        // Single-quoted string literals, handling escaped quotes ('' or \').
        $sql = preg_replace("/'(?:[^'\\\\]|\\\\.|'')*'/", "'?'", $sql) ?? $sql;

        // Double-quoted string literals (used by some drivers for values,
        // not identifiers in this codebase).
        $sql = preg_replace('/"(?:[^"\\\\]|\\\\.)*"/', '"?"', $sql) ?? $sql;

        // Numeric literals that are not part of an identifier (e.g. table
        // names with digits). Keep query structure readable by collapsing
        // values to "?".
        $sql = preg_replace('/(?<![A-Za-z_0-9.])-?\d+(?:\.\d+)?(?![A-Za-z_0-9])/', '?', $sql) ?? $sql;

        return $sql;
    }
}
