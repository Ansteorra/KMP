<?php
declare(strict_types=1);

namespace App\Services;

use Cake\ORM\Table;

/** Explicit alternative to updateAll for registered resources that need live invalidation. */
final class UiBulkMutation
{
    /** @param array $fields ORM update values/expressions. @param array $conditions Mutation scope. */
    public static function update(Table $table, array $fields, array $conditions): int
    {
        return self::execute($table, $conditions, $fields);
    }

    /** @param array $conditions Mutation scope. */
    public static function delete(Table $table, array $conditions): int
    {
        return self::execute($table, $conditions, null);
    }

    /** Capture and lock the matched owners; writes and invalidations share the domain transaction. */
    private static function execute(Table $table, array $conditions, ?array $fields): int
    {
        $definition = UiUpdateRegistry::definitions()[$table->getRegistryAlias()] ?? null;
        if (
            !TurboConfig::liveEnabled() || $definition === null
            || filter_var(env('KMP_ADMIN_JOB', false), FILTER_VALIDATE_BOOL)
        ) {
            return $fields === null ? $table->deleteAll($conditions) : $table->updateAll($fields, $conditions);
        }

        return $table->getConnection()->transactional(function () use ($table, $conditions, $fields, $definition): int {
            $primaryKeys = (array)$table->getPrimaryKey();
            $columns = array_unique(array_merge(
                $primaryKeys,
                ['entity_type', 'entity_id'],
                array_keys($definition['parents']),
            ));
            $columns = array_values(array_filter(
                $columns,
                fn(string $column): bool => $table->getSchema()->hasColumn($column),
            ));
            $finder = $table->hasBehavior('Trash') ? 'withTrashed' : 'all';
            $previous = $table->find($finder)->select($columns)->where($conditions)
                ->orderBy(array_fill_keys($primaryKeys, 'ASC'))
                ->epilog('FOR UPDATE')->all()->toList();
            $affected = 0;
            foreach (array_chunk($previous, 256) as $batch) {
                $scope = count($primaryKeys) === 1
                    ? [$primaryKeys[0] . ' IN' => array_map(fn($row) => $row->get($primaryKeys[0]), $batch)]
                    : ['OR' => array_map(fn($row) => array_combine(
                        $primaryKeys,
                        array_map(fn(string $key) => $row->get($key), $primaryKeys),
                    ), $batch)];
                $affected += $fields === null ? $table->deleteAll($scope) : $table->updateAll($fields, $scope);
                $topics = [];
                foreach ($batch as $row) {
                    $topics = self::merge($topics, UiUpdateRegistry::changes($table, $row));
                }
                if ($fields !== null) {
                    foreach ($table->find($finder)->select($columns)->where($scope) as $row) {
                        $topics = self::merge($topics, UiUpdateRegistry::changes($table, $row));
                    }
                }
                (new UiUpdateOutbox())->record($table->getConnection(), $topics);
            }

            return $affected;
        });
    }

    /** @param array<string, list<string>> $left @param array<string, list<string>> $right */
    private static function merge(array $left, array $right): array
    {
        foreach ($right as $topic => $changes) {
            $left[$topic] = array_values(array_unique(array_merge($left[$topic] ?? [], $changes)));
        }

        return $left;
    }
}
