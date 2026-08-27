<?php
declare(strict_types=1);

namespace App\KMP;

use Cake\Database\Driver\Postgres;
use Cake\Database\Expression\FunctionExpression;
use Cake\Database\ExpressionInterface;
use Cake\Datasource\ConnectionManager;

/**
 * Builds portable case-insensitive conditions for trusted database fields.
 *
 * SCA-name fields also ignore diacritics. PostgreSQL uses its unaccent
 * dictionary while MySQL relies on the configured accent-insensitive
 * collation.
 */
final class CaseInsensitiveQuery
{
    /**
     * @return array<string, string|\Cake\Database\ExpressionInterface>
     */
    public static function equals(string $field, string $value): array
    {
        return [self::fieldExpression($field) => self::valueExpression($field, trim($value))];
    }

    /**
     * @return array<string, string|\Cake\Database\ExpressionInterface>
     */
    public static function notEquals(string $field, string $value): array
    {
        return [self::fieldExpression($field) . ' !=' => self::valueExpression($field, trim($value))];
    }

    /**
     * @return array<string, string|\Cake\Database\ExpressionInterface>
     */
    public static function contains(string $field, string $value): array
    {
        return [
            self::fieldExpression($field) . ' LIKE' => self::valueExpression($field, '%' . trim($value) . '%'),
        ];
    }

    /**
     * @return array<string, string|\Cake\Database\ExpressionInterface>
     */
    public static function startsWith(string $field, string $value): array
    {
        return [
            self::fieldExpression($field) . ' LIKE' => self::valueExpression($field, trim($value) . '%'),
        ];
    }

    /**
     * @return array<string, string|\Cake\Database\ExpressionInterface>
     */
    public static function endsWith(string $field, string $value): array
    {
        return [
            self::fieldExpression($field) . ' LIKE' => self::valueExpression($field, '%' . trim($value)),
        ];
    }

    /**
     * Normalize a query value without changing the stored value.
     */
    public static function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    /**
     * Whether the trusted field identifier represents an SCA name.
     */
    public static function isScaNameField(string $field): bool
    {
        $fieldName = str_contains($field, '.')
            ? substr($field, (int)strrpos($field, '.') + 1)
            : $field;

        return $fieldName === 'sca_name' || str_ends_with($fieldName, '_sca_name');
    }

    /**
     * Build the database-side comparison expression.
     */
    private static function fieldExpression(string $field): string
    {
        $expression = 'LOWER(' . $field . ')';
        if (self::isScaNameField($field) && self::usesPostgres()) {
            return 'UNACCENT(' . $expression . ')';
        }

        return $expression;
    }

    /**
     * Normalize a value according to the field's comparison behavior.
     */
    private static function valueExpression(string $field, string $value): string|ExpressionInterface
    {
        if (self::isScaNameField($field) && self::usesPostgres()) {
            return new FunctionExpression('UNACCENT', [
                new FunctionExpression('LOWER', [$value], ['string']),
            ]);
        }

        return self::normalize($value);
    }

    /**
     * Whether the active tenant connection needs explicit unaccent support.
     */
    private static function usesPostgres(): bool
    {
        return ConnectionManager::get('default')->getDriver() instanceof Postgres;
    }
}
