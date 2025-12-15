<?php

declare(strict_types=1);

namespace App\Model\Behavior;

use Cake\Database\Expression\QueryExpression;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\ORM\Behavior;
use Throwable;

/**
 * Sortable Behavior
 *
 * Position-based list ordering with group support. Provides toTop, toBottom,
 * move, moveBefore, and moveAfter operations with automatic conflict resolution.
 *
 * @see /docs/3.2-model-behaviors.md#sortable-behavior
 */
class SortableBehavior extends Behavior
{
    /**
     * Default configuration.
     *
     * @var array
     */
    protected array $_defaultConfig = [
        'field' => 'position',
        'group' => [],
        'start' => 1,
        'step' => 1,
    ];

    /** @var array Fields for position queries */
    protected $fields;

    /** @var EntityInterface Current entity being processed */
    protected $row;

    /**
     * Initialize behavior.
     *
     * @param array $config Configuration
     * @return void
     */
    public function initialize(array $config): void
    {
        $this->fields = array_merge(['id', $this->_config['field']], $this->_config['group']);
    }

    /**
     * Move entity to first position in its group.
     *
     * @param int $id Primary key of entity
     * @return bool True on success
     */
    public function toTop(int $id): bool
    {
        try {
            $field = $this->_config['field'];
            $this->row = $this->_table->get($id, ['fields' => $this->fields]);
            $currentVal = $this->row->{$field};
            $newVal = $this->getStart();

            if ($currentVal == $newVal) {
                return true;
            }

            // Add one step to all the ones below
            $this->_change($currentVal, false);

            // Set it as the first one
            $this->row->{$field} = $newVal;
            $this->_table->save($this->row);
        } catch (Throwable $th) {
            // TODO: Log error
            return false;
        }

        return true;
    }

    /**
     * Move entity to last position in its group.
     *
     * @param int $id Primary key of entity
     * @return bool True on success
     */
    public function toBottom(int $id): bool
    {
        try {
            $field = $this->_config['field'];
            $this->row = $this->_table->get($id, ['fields' => $this->fields]);
            $currentVal = $this->row->{$field};
            $newVal = $this->getLast($this->_getConditions());

            if (!$field) {
                return false;
            }

            if ($currentVal == $newVal) {
                return true;
            }

            // Subtract one step from all the ones above
            $this->_change($currentVal);

            // Set it as the last one
            $this->row->{$field} = $newVal;
            $this->_table->save($this->row);
        } catch (Throwable $th) {
            // TODO: Log error
            return false;
        }

        return true;
    }

    /** @var bool Prevents recursion during beforeSave */
    private $preventCallOfMoveInEventListener = false;

    /**
     * Move entity to specific position.
     *
     * @param int $id Primary key of entity
     * @param int $newVal Target position
     * @param bool $moveOwn Whether to move the entity itself (default: true)
     * @return bool True on success
     */
    public function move(int $id, int $newVal, bool $moveOwn = true): bool
    {
        try {
            $step = $this->getStep();
            $field = $this->_config['field'];
            $this->row = $this->_table->get($id, ['fields' => $this->fields]);
            $currentVal = $this->row->{$field};

            if ($newVal == $currentVal) {
                return true; // We do nothing
            } elseif ($newVal < $currentVal) {
                // Create a gap for the movement; do not modify the original
                $this->_change([$newVal, $currentVal - $step], false);
            } else {
                // Create a gap for the movement; do not modify the original
                $this->_change([$currentVal + $step, $newVal]);
            }

            // Assigns the new position
            if ($moveOwn) {
                $this->preventCallOfMoveInEventListener = true;
                $this->row->{$field} = $newVal;
                $this->_table->save($this->row);
                $this->preventCallOfMoveInEventListener = false;
            }
        } catch (Throwable $th) {
            // TODO: Log error
            return false;
        }

        return true;
    }

    /**
     * Move entity to position before another entity.
     *
     * @param int $id Primary key of entity to move
     * @param int $beforeId Primary key of entity to move before
     * @return bool True on success
     */
    public function moveBefore(int $id, int $beforeId): bool
    {
        $before = $this->_table->get($beforeId, ['fields' => $this->fields]);

        return $this->move($id, $before->{$this->_config['field']}, true);
    }

    /**
     * Move entity to position after another entity.
     *
     * @param int $id Primary key of entity to move
     * @param int $afterId Primary key of entity to move after
     * @return bool True on success
     */
    public function moveAfter(int $id, int $afterId): bool
    {
        $after = $this->_table->get($afterId, ['fields' => $this->fields]);

        return $this->move($id, $after->{$this->_config['field']} + $this->getStep(), true);
    }

    /**
     * Get configured starting position.
     *
     * @return int|float Starting position value
     */
    public function getStart(): int|float
    {
        return $this->_config['start'];
    }

    /**
     * Get position for new entity (end of list).
     *
     * @param array $conditions Optional group conditions
     * @return int|float Position for new entity
     */
    public function getNew($conditions = []): int|float
    {
        return $this->getLast($conditions) + $this->getStep();
    }

    /**
     * Get configured step increment.
     *
     * @return int|float Step value
     */
    public function getStep(): int|float
    {
        return $this->_config['step'];
    }

    /**
     * Returns the highest value
     *
     * @param array $conditions
     * @return float|int
     */
    public function getLast(array $conditions = []): int|float
    {
        $field = $this->_config['field'];
        $query = $this->_table->find(
            'all',
            fields: $this->fields,
            order: ["{$field}" => 'DESC'],
        );

        if (!empty($conditions)) {
            $query->where($conditions);
        }

        return $query->first()->{$field} ?? $this->getStart() - $this->getStep();
    }

    /**
     * Subtract or add a step to the value of a field.
     *
     * @param array|int $value the new value or an array with two values
     * @param bool $substract by default subtracts
     * @return void
     */
    private function _change(int|array $value, bool $substract = true): void
    {
        $step = $this->getStep();
        $field = $this->_config['field'];
        $operator = $substract ? '-' : '+'; // Subtract or add
        $expression = new QueryExpression("`{$field}` = `{$field}` {$operator} {$step}");
        $conditions = $this->_getConditions();

        if (!is_array($value)) {
            // Modify all the ones above or below
            $operator = $substract ? '>' : '<';
            $conditions = array_merge($conditions, ["{$field} {$operator}" => $value]);
            $this->_table->updateAll([$expression], $conditions);
        } else {
            // Modify the ones within the range
            $between = ["{$field} >=" => $value[0], "{$field} <=" => $value[1]];
            $conditions = array_merge($between, $conditions);
            $this->_table->updateAll([$expression], $conditions);
        }
    }

    /**
     * Moves all values to insert a new one in the middle of the list
     *
     * @param array|int $value the position where it will be inserted
     * @return void
     */
    private function _insert(int|array $value): void
    {
        if ($this->isFirst($this->_getConditions())) {
            return; // If there are no values to modify, do nothing
        }

        $step = $this->getStep();
        $field = $this->_config['field'];
        $expression = new QueryExpression("`{$field}` = `{$field}` + {$step}");
        try {
            $conditions = array_merge($this->_getConditions(), ["{$field} >=" => $value]);
            $this->_table->updateAll([$expression], $conditions);
        } catch (Throwable $th) {
            // TODO: When saving multiple entities at once, even though isFirst() is used, starting from the second one it starts returning FALSE, but they actually don't exist yet so updateAll returns false
        }
    }

    /**
     * Returns conditions for the WHERE clause
     *
     * @param \App\Model\Entity $entity
     * @return array
     */
    private function _getConditions(): array
    {
        $group = $this->_config['group'];
        $conditions = [];
        foreach ($group as $column) {
            if (is_null($this->row->{$column})) {
                $conditions[] = "{$column} IS NULL";
            } else {
                $conditions[$column] = $this->row->{$column};
            }
        }

        return $conditions;
    }

    /**
     * Checks if it is the first row of its group
     *
     * @param array $conditions
     * @return bool
     */
    public function isFirst(array $conditions = []): bool
    {
        $field = $this->_config['field'];
        $query = $this->_table->find(
            'all',
            fields: $this->fields,
            order: ["{$field}" => 'DESC'],
        );

        if (!empty($conditions)) {
            $query->where($conditions);
        }

        return $query->count() == 0;
    }

    /**
     * Auto-assign position on save for new entities or handle position changes.
     *
     * @param EventInterface $event The beforeSave event
     * @param EntityInterface $entity The entity being saved
     * @return void
     */
    public function beforeSave(EventInterface $event, EntityInterface $entity): void
    {
        $this->row = $entity;
        $default = $this->getNew($this->_getConditions());
        $field = $this->_config['field'];
        if ($entity->isNew()) { // If it is a new row
            if ($entity->{$field} != $default || $entity->{$field} != null) { // If there are already other rows and it is not inserted at the end
                $entity->{$field} = $default;
                $this->_insert($entity->{$field});
            }
        } elseif (
            $this->preventCallOfMoveInEventListener === false
            &&
            $entity->isDirty($field)
        ) { // If position has been modified
            $this->move($entity->id, $entity->{$field}, false);
        }
    }
}
