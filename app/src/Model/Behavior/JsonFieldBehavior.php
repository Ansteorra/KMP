<?php

declare(strict_types=1);

namespace App\Model\Behavior;

use Cake\Database\Expression\QueryExpression;
use Cake\ORM\Behavior;
use Cake\ORM\Query\SelectQuery;
use InvalidArgumentException;

/**
 * JsonField Behavior
 *
 * Enables querying JSON fields using database-specific JSON extraction syntax.
 * Provides addJsonWhere() for $.path-based JSON field filtering.
 *
 * @see /docs/3.2-model-behaviors.md#jsonfield-behavior
 */
class JsonFieldBehavior extends Behavior
{
    /**
     * Add JSON path WHERE condition to a query.
     *
     * @param SelectQuery $query The query to modify
     * @param string $field The JSON field name in the table
     * @param string $path JSON path using $.notation (e.g., '$.preferences.email')
     * @param mixed $value Value to compare against
     * @return SelectQuery Modified query with JSON WHERE condition
     */
    public function addJsonWhere(SelectQuery $query, string $field, string $path, mixed $value): SelectQuery
    {
        return $query->where(function (QueryExpression $exp, SelectQuery $q) use ($field, $path, $value) {
            $json = $this->buildJsonExtractExpression($q, $field, $path);

            return $exp->eq($json, $value);
        });
    }

    /**
     * Build driver-specific JSON extraction expression.
     *
     * @param SelectQuery $query Query being modified
     * @param string $field Field name (optionally table-qualified)
     * @param string $path JSON path using $.notation
     * @return mixed SQL expression compatible with QueryExpression::eq()
     */
    protected function buildJsonExtractExpression(SelectQuery $query, string $field, string $path): mixed
    {
        if ($this->getDriverName($query) === 'Postgres') {
            $segments = $this->extractJsonPathSegments($path);
            $quotedField = $this->quoteIdentifierPath($query, $field);

            if ($segments === []) {
                return $query->newExpr(sprintf('CAST(%s AS jsonb)', $quotedField));
            }

            $quotedSegments = array_map(
                static fn(string $segment): string => "'" . str_replace("'", "''", $segment) . "'",
                $segments
            );

            return $query->newExpr(sprintf(
                'jsonb_extract_path_text(CAST(%s AS jsonb), %s)',
                $quotedField,
                implode(', ', $quotedSegments)
            ));
        }

        return $query->func()->json_extract([$field => 'identifier', $path]);
    }

    /**
     * Resolve current database driver short name.
     *
     * @param SelectQuery $query Query being modified
     * @return string Driver short class name (e.g., Mysql, Postgres, Sqlite)
     */
    protected function getDriverName(SelectQuery $query): string
    {
        $driverClass = get_class($query->getConnection()->getDriver());
        $lastSeparator = strrpos($driverClass, '\\');

        return $lastSeparator === false ? $driverClass : substr($driverClass, $lastSeparator + 1);
    }

    /**
     * Convert a JSON path ($.a.b) to path segments.
     *
     * @param string $path JSON path
     * @return array<string> Path segments
     */
    protected function extractJsonPathSegments(string $path): array
    {
        if ($path === '$') {
            return [];
        }

        if (!str_starts_with($path, '$.')) {
            throw new InvalidArgumentException(sprintf('Invalid JSON path `%s`. Expected $.path format.', $path));
        }

        $segments = explode('.', substr($path, 2));
        foreach ($segments as $segment) {
            if ($segment === '' || preg_match('/^[A-Za-z0-9_]+$/', $segment) !== 1) {
                throw new InvalidArgumentException(sprintf('Invalid JSON path segment `%s` in path `%s`.', $segment, $path));
            }
        }

        return $segments;
    }

    /**
     * Quote a possibly qualified identifier path.
     *
     * @param SelectQuery $query Query being modified
     * @param string $identifier Identifier path (table.column)
     * @return string Quoted identifier path
     */
    protected function quoteIdentifierPath(SelectQuery $query, string $identifier): string
    {
        $driver = $query->getConnection()->getDriver();

        return implode('.', array_map(
            static fn(string $part): string => $driver->quoteIdentifier($part),
            explode('.', $identifier)
        ));
    }
}
