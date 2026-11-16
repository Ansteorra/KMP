<?php
declare(strict_types=1);

namespace App\Services;

use Cake\Database\Expression\CaseStatementExpression;
use Cake\Database\Expression\QueryExpression;

/** Shared activity display order for entity lists, selectors and catalogue queries. */
class GatheringActivityOrderingService
{
    /**
     * @param string|null $type Activity type
     * @return int Royal progress, then circles, then all remaining activities
     */
    public static function priority(?string $type): int
    {
        return match ($type) {
            'Royal Progress' => 0,
            'Order Circle' => 1,
            default => 2,
        };
    }

    /**
     * @template T of array|object
     * @param array<T> $activities Activity entities or serialized activity data
     * @return array<T> Sorted copies retaining the original entities and join metadata
     */
    public static function sort(array $activities): array
    {
        $value = static fn($activity, string $field, $default = null) => is_array($activity)
            ? ($activity[$field] ?? $default) : ($activity->$field ?? $default);
        usort($activities, static fn($a, $b): int => self::priority($value($a, 'activity_type'))
            <=> self::priority($value($b, 'activity_type'))
            ?: (int)$value($a, 'display_order', 100) <=> (int)$value($b, 'display_order', 100)
            ?: strcasecmp($value($a, 'name', ''), $value($b, 'name', ''))
            ?: ($value($a, 'id', 0) <=> $value($b, 'id', 0)));

        return $activities;
    }

    /**
     * @param string $alias ORM table alias
     * @return array Shared SQL order; PostgreSQL's catalogue name column is case-insensitive
     */
    public static function queryOrder(string $alias = 'GatheringActivities'): array
    {
        return [
            self::queryPriority($alias),
            $alias . '.display_order' => 'ASC', $alias . '.name' => 'ASC', $alias . '.id' => 'ASC',
        ];
    }

    /**
     * @param string $alias ORM table alias
     * @return \Cake\Database\Expression\CaseStatementExpression
     */
    public static function queryPriority(string $alias = 'GatheringActivities'): CaseStatementExpression
    {
        return (new QueryExpression())->case()
            ->when([$alias . '.activity_type' => 'Royal Progress'])->then(0, 'integer')
            ->when([$alias . '.activity_type' => 'Order Circle'])->then(1, 'integer')
            ->else(2, 'integer');
    }
}
