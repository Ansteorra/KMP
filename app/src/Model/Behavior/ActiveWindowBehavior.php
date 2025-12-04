<?php

declare(strict_types=1);

namespace App\Model\Behavior;

use Cake\I18n\Datetime;
use Cake\ORM\Behavior;
use Cake\ORM\Query\SelectQuery;

/**
 * ActiveWindow Behavior
 *
 * Temporal filtering for entities with start_on/expires_on date windows.
 * Provides findUpcoming, findCurrent, and findPrevious finders.
 *
 * @see /docs/3.2-model-behaviors.md#activewindow-behavior
 */
class ActiveWindowBehavior extends Behavior
{
    /**
     * Find records starting in the future or not yet expired.
     *
     * @param SelectQuery $query The query to modify
     * @param Datetime|null $effectiveDate Date to check against (defaults to now)
     * @return SelectQuery Modified query with upcoming conditions
     */
    public function findUpcoming(SelectQuery $query, ?Datetime $effectiveDate = null): SelectQuery
    {
        //get the alias of the current table

        $alias = $this->_table->getAlias();
        if ($effectiveDate == null || !$effectiveDate instanceof Datetime) {
            $effectiveDate = Datetime::now();
        }

        return $query->where([$alias . '.start_on >' => $effectiveDate, 'or' => [$alias . '.expires_on >' => $effectiveDate, $alias . '.expires_on IS' => null]]);
    }

    /**
     * Find records currently active (started and not expired).
     *
     * @param SelectQuery $query The query to modify
     * @param Datetime|null $effectiveDate Date to check against (defaults to now)
     * @return SelectQuery Modified query with current active conditions
     */
    public function findCurrent(SelectQuery $query, ?Datetime $effectiveDate = null): SelectQuery
    {
        //get the alias of the current table
        $alias = $this->_table->getAlias();
        if ($effectiveDate == null || !$effectiveDate instanceof Datetime) {
            $effectiveDate = Datetime::now();
        }

        return $query->where([$alias . '.start_on <=' => $effectiveDate, 'or' => [$alias . '.expires_on >=' => $effectiveDate, $alias . '.expires_on IS' => null]]);
    }

    /**
     * Find records that have expired (expires_on < effective date).
     *
     * @param SelectQuery $query The query to modify
     * @param Datetime|null $effectiveDate Date to check against (defaults to now)
     * @return SelectQuery Modified query with expired conditions
     */
    public function findPrevious(SelectQuery $query, ?Datetime $effectiveDate = null): SelectQuery
    {
        //get the alias of the current table

        $alias = $this->_table->getAlias();
        if ($effectiveDate == null || !$effectiveDate instanceof Datetime) {
            $effectiveDate = Datetime::now();
        }

        return $query->where([$alias . '.expires_on <' => $effectiveDate]);
    }
}
