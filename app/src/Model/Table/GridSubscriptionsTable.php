<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\Validation\Validator;

class GridSubscriptionsTable extends BaseTable
{
    /** @inheritDoc */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('grid_subscriptions');
        $this->addBehavior('Timestamp');
        $this->belongsTo('Members');
    }

    /** @inheritDoc */
    public function validationDefault(Validator $validator): Validator
    {
        return $validator->integer('member_id')->requirePresence('member_id', 'create')
            ->scalar('name')->maxLength('name', 150)->notEmptyString('name')
            ->inList('interval_days', [1, 3, 7])
            ->inList('status', ['active', 'stopped']);
    }
}
