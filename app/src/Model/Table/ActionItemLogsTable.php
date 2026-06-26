<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\Validation\Validator;

/**
 * ActionItemLogs Table - append-only audit of action item status changes.
 *
 * @property \App\Model\Table\ActionItemsTable&\Cake\ORM\Association\BelongsTo $ActionItems
 * @method \App\Model\Entity\ActionItemLog newEmptyEntity()
 * @method \App\Model\Entity\ActionItemLog newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\ActionItemLog saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class ActionItemLogsTable extends BaseTable
{
    /**
     * Initialize table associations and behaviors.
     *
     * @param array<string, mixed> $config Table configuration
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('action_item_logs');
        $this->setDisplayField('to_status');
        $this->setPrimaryKey('id');

        $this->belongsTo('ActionItems', [
            'className' => 'ActionItems',
            'foreignKey' => 'action_item_id',
            'joinType' => 'INNER',
        ]);

        $this->addBehavior('Timestamp');
        $this->addBehavior('Muffin/Footprint.Footprint');
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('action_item_id')
            ->requirePresence('action_item_id', 'create')
            ->notEmptyString('action_item_id');

        $validator
            ->scalar('from_status')
            ->maxLength('from_status', 20)
            ->requirePresence('from_status', 'create')
            ->notEmptyString('from_status');

        $validator
            ->scalar('to_status')
            ->maxLength('to_status', 20)
            ->requirePresence('to_status', 'create')
            ->notEmptyString('to_status');

        $validator
            ->scalar('note')
            ->allowEmptyString('note');

        return $validator;
    }
}
