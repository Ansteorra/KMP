<?php

declare(strict_types=1);

namespace App\Model\Behavior;

use Cake\ORM\Behavior;
use Cake\Database\Expression\QueryExpression;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;

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
        'step' => 1
    ];

    protected $fields; // Fields for searches
    protected $row; // Entity to modify

    /**
     * Initialize hook
     *
     * If events are specified - do *not* merge them with existing events,
     * overwrite the events to listen on
     *
     * @param array $config The config for this behavior.
     * @return void
     */
    public function initialize(array $config): void
    {
        $this->fields = array_merge(['id', $this->_config['field']], $this->_config['group']);
    }

    /**
     * Moves an element to the top
     *
     * @param \App\Model\Entity $id
     */
    public function toTop($id): bool
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
        } catch (\Throwable $th) {
            // TODO: Log error
            return false;
        }

        return true;
    }

    /**
     * Moves an element to the bottom
     *
     * @param \App\Model\Entity $id
     */
    public function toBottom($id): bool
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
        } catch (\Throwable $th) {
            // TODO: Log error
            return false;
        }

        return true;
    }

    /**
     * @var bool
     */
    private $preventCallOfMoveInEventListener = false;

    /**
     * Moves an element to another position
     *
     * @param \App\Model\Entity $id
     * @param int $newVal
     * @param bool $moveOwn
     */
    public function move($id, $newVal, $moveOwn = true): bool
    {
        try {

            $step = $this->getStep();
            $field = $this->_config['field'];
            $this->row = $this->_table->get($id, ['fields' => $this->fields]);
            $currentVal = $this->row->{$field};

            if ($newVal == $currentVal) {
                return true; // We do nothing
            } else if ($newVal < $currentVal) {
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
        } catch (\Throwable $th) {
            // TODO: Log error
            return false;
        }

        return true;
    }

    public function moveBefore($id, $beforeId): bool
    {
        $before = $this->_table->get($beforeId, ['fields' => $this->fields]);
        return $this->move($id, $before->{$this->_config['field']}, true);
    }

    public function moveAfter($id, $afterId): bool
    {
        $after = $this->_table->get($afterId, ['fields' => $this->fields]);
        return $this->move($id, $after->{$this->_config['field']} + $this->getStep(), true);
    }

    /**
     * Returns the minimum set value
     *
     * @return int|float
     */
    public function getStart()
    {
        return $this->_config['start'];
    }

    /**
     * Returns the next value after the highest one
     * Useful when creating new entries
     *
     * @return int|float
     */
    public function getNew($conditions = [])
    {
        return $this->getLast($conditions) + $this->getStep();
    }

    /**
     * Returns the value to be added or subtracted
     *
     * @return int|float
     */
    public function getStep()
    {
        return $this->_config['step'];
    }

    /**
     * Returns the highest value
     *
     * @param array $conditions
     * @return int|float
     */
    public function getLast($conditions = [])
    {
        $field = $this->_config['field'];
        $query = $this->_table->find(
            'all',
            fields: $this->fields,
            order: ["{$field}" => 'DESC']
        );

        if (!empty($conditions)) {
            $query->where($conditions);
        }

        return $query->first()->{$field} ?? $this->getStart() - $this->getStep();
    }

    /**
     * Subtract or add a step to the value of a field.
     *
     * @param int|array $value the new value or an array with two values
     * @param bool $substract by default subtracts
     * @return void
     */
    private function _change($value, $substract = true)
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
     * @param int|array $value the position where it will be inserted
     * @return void
     */
    private function _insert($value)
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
        } catch (\Throwable $th) {
            // TODO: When saving multiple entities at once, even though isFirst() is used, starting from the second one it starts returning FALSE, but they actually don't exist yet so updateAll returns false
            return false;
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
     * @return int|float
     */
    public function isFirst($conditions = [])
    {
        $field = $this->_config['field'];
        $query = $this->_table->find(
            'all',
            fields: $this->fields,
            order: ["{$field}" => 'DESC']
        );

        if (!empty($conditions)) {
            $query->where($conditions);
        }

        return $query->count() == 0;
    }

    /**
     * Before save listener.
     *
     * @param \Cake\Event\EventInterface $event The beforeSave event that was fired
     * @param \Cake\Datasource\EntityInterface $entity the entity that is going to be saved
     * @return void
     */
    public function beforeSave(EventInterface $event, EntityInterface $entity)
    {
        $this->row = $entity;
        $default = $this->getNew($this->_getConditions());
        $field = $this->_config['field'];
        if ($entity->isNew()) { // If it is a new row
            if ($entity->{$field} != $default || $entity->{$field} != null) { // If there are already other rows and it is not inserted at the end
                $entity->{$field} = $default;
                $this->_insert($entity->{$field});
            }
        } else if (
            false === $this->preventCallOfMoveInEventListener
            &&
            $entity->isDirty($field)
        ) { // Si se ha modificado el orden
            $this->move($entity->id, $entity->{$field}, false);
        }
    }
}