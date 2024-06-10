<?php

namespace App\Model\Behavior;

use Cake\ORM\Behavior;
use Cake\ORM\Query\SelectQuery;
use Cake\I18n\Datetime;


class ActiveWindowBehavior extends Behavior
{
    public function findUpcoming(SelectQuery $query, Datetime $effectiveDate = null): SelectQuery
    {
        if ($effectiveDate == null || !$effectiveDate instanceof Datetime) {
            $effectiveDate = Datetime::now();
        }
        return $query->where(['start_on >' => $effectiveDate, 'or' => ['expires_on >' => $effectiveDate, 'expires_on IS' => null]]);
    }

    public function findCurrent(SelectQuery $query, Datetime $effectiveDate = null): SelectQuery
    {
        if ($effectiveDate == null || !$effectiveDate instanceof Datetime) {
            $effectiveDate = Datetime::now();
        }
        return $query->where(['start_on <=' => $effectiveDate, 'or' => ['expires_on >=' => $effectiveDate, 'expires_on IS' => null]]);
    }

    public function findPrevious(SelectQuery $query, Datetime $effectiveDate = null): SelectQuery
    {
        if ($effectiveDate == null || !$effectiveDate instanceof Datetime) {
            $effectiveDate = Datetime::now();
        }
        return $query->where(['expires_on <' => $effectiveDate]);
    }
}