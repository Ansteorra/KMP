<?php
declare(strict_types=1);

namespace App\Model\Behavior;

use Cake\Database\Expression\QueryExpression;
use Cake\ORM\Behavior;
use Cake\ORM\Query\SelectQuery;

class JsonFieldBehavior extends Behavior
{
    public function initialize(array $config): void
    {
        // Some initialization code here
    }

    public function addJsonWhere($query, $field, $path, $value)
    {
        return $query->where(function (QueryExpression $exp, SelectQuery $q) use ($field, $path, $value) {
            $json = $q->func()->json_extract([$field => 'identifier', $path]);

            return $exp->eq($json, $value);
        });
    }
}
