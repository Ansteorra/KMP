<?php
declare(strict_types=1);

namespace App\KMP;

/**
 * Builds portable case-insensitive conditions for trusted database fields.
 */
final class CaseInsensitiveQuery
{
    /**
     * @return array<string, string>
     */
    public static function equals(string $field, string $value): array
    {
        return ['LOWER(' . $field . ')' => self::normalize($value)];
    }

    /**
     * @return array<string, string>
     */
    public static function notEquals(string $field, string $value): array
    {
        return ['LOWER(' . $field . ') !=' => self::normalize($value)];
    }

    /**
     * @return array<string, string>
     */
    public static function contains(string $field, string $value): array
    {
        return ['LOWER(' . $field . ') LIKE' => '%' . self::normalize($value) . '%'];
    }

    /**
     * @return array<string, string>
     */
    public static function startsWith(string $field, string $value): array
    {
        return ['LOWER(' . $field . ') LIKE' => self::normalize($value) . '%'];
    }

    /**
     * @return array<string, string>
     */
    public static function endsWith(string $field, string $value): array
    {
        return ['LOWER(' . $field . ') LIKE' => '%' . self::normalize($value)];
    }

    /**
     * Normalize a query value without changing the stored value.
     */
    public static function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
