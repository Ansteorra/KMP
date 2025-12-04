<?php

declare(strict_types=1);

namespace App\Model\Behavior;

use Cake\Database\Expression\QueryExpression;
use Cake\ORM\Behavior;
use Cake\ORM\Query\SelectQuery;

/**
 * JsonField Behavior
 *
 * Enables querying JSON fields using database JSON_EXTRACT functions.
 * Provides addJsonWhere() for $.path-based JSON field filtering.
 *
 * @see /docs/3.2-model-behaviors.md#jsonfield-behavior
 */
class JsonFieldBehavior extends Behavior
{
    /**
     * Initialize behavior.
     *
     * @param array $config Configuration options
     * @return void
     */
    public function initialize(array $config): void
    {
        // Some initialization code here
    }

    /**
     * Add JSON path WHERE condition to a query using JSON_EXTRACT.
     *
     * @param SelectQuery $query The query to modify
     * @param string $field The JSON field name in the table
     * @param string $path JSON path using $.notation (e.g., '$.preferences.email')
     * @param mixed $value Value to compare against
     * @return SelectQuery Modified query with JSON WHERE condition
     */
    public function addJsonWhere($query, $field, $path, $value)
    {
        return $query->where(function (QueryExpression $exp, SelectQuery $q) use ($field, $path, $value) {
            $json = $q->func()->json_extract([$field => 'identifier', $path]);

            return $exp->eq($json, $value);
        });
    }
}
