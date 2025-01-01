<?php

namespace App\Model\Behavior;

use Cake\ORM\Behavior;
use Cake\ORM\Query\SelectQuery;
use Cake\I18n\Datetime;


class ActiveWindowBehavior extends Behavior
{
    public function findUpcoming(SelectQuery $query, Datetime $effectiveDate = null): SelectQuery
    {
        //get the alias of the current table

        $alias = $this->_table->getAlias();
        if ($effectiveDate == null || !$effectiveDate instanceof Datetime) {
            $effectiveDate = Datetime::now();
        }
        return $query->where([$alias . '.start_on >' => $effectiveDate, 'or' => [$alias . '.expires_on >' => $effectiveDate, $alias . '.expires_on IS' => null]]);
    }

    public function findCurrent(SelectQuery $query, Datetime $effectiveDate = null): SelectQuery
    {
        //get the alias of the current table
        $alias = $this->_table->getAlias();
        if ($effectiveDate == null || !$effectiveDate instanceof Datetime) {
            $effectiveDate = Datetime::now();
        }
        return $query->where([$alias . '.start_on <=' => $effectiveDate, 'or' => [$alias . '.expires_on >=' => $effectiveDate, $alias . '.expires_on IS' => null]]);
    }

    public function findPrevious(SelectQuery $query, Datetime $effectiveDate = null): SelectQuery
    {
        //get the alias of the current table

        $alias = $this->_table->getAlias();
        if ($effectiveDate == null || !$effectiveDate instanceof Datetime) {
            $effectiveDate = Datetime::now();
        }
        return $query->where([$alias . '.expires_on <' => $effectiveDate]);
    }
}