<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * GridViewPreferences Table - Member-specific default view selections
 *
 * This table stores the preferred view for each member + grid combination. It
 * decouples default selections from the `grid_views` table so that system views
 * can be adopted as personal defaults without mutating system configuration.
 */
class GridViewPreferencesTable extends BaseTable
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('grid_view_preferences');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
        $this->addBehavior('Muffin/Footprint.Footprint', [
            'events' => [
                'Model.beforeSave' => [
                    'created_by' => 'new',
                    'modified_by' => 'always',
                ],
            ],
        ]);

        $this->belongsTo('Members', [
            'foreignKey' => 'member_id',
            'joinType' => 'INNER',
        ]);

        $this->belongsTo('GridViews', [
            'foreignKey' => 'grid_view_id',
            'joinType' => 'LEFT',
        ]);
        // grid_view_key is a string reference for system views; no FK needed

        $this->belongsTo('Creators', [
            'className' => 'Members',
            'foreignKey' => 'created_by',
            'joinType' => 'LEFT',
        ]);

        $this->belongsTo('Modifiers', [
            'className' => 'Members',
            'foreignKey' => 'modified_by',
            'joinType' => 'LEFT',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->integer('member_id')
            ->requirePresence('member_id', 'create')
            ->notEmptyString('member_id');

        $validator
            ->scalar('grid_key')
            ->maxLength('grid_key', 100)
            ->requirePresence('grid_key', 'create')
            ->notEmptyString('grid_key');

        $validator
            ->integer('grid_view_id')
            ->allowEmptyString('grid_view_id');

        $validator
            ->scalar('grid_view_key')
            ->maxLength('grid_view_key', 100)
            ->allowEmptyString('grid_view_key');

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(
            ['member_id', 'grid_key'],
            'Each grid may only have one preference per member.'
        ));

        $rules->add($rules->existsIn(['member_id'], 'Members'));
        $rules->add($rules->existsIn(['grid_view_id'], 'GridViews'));

        $rules->add(function ($entity) {
            $hasId = !empty($entity->grid_view_id);
            $hasKey = !empty($entity->grid_view_key);
            return $hasId xor $hasKey;
        }, 'viewIdOrKeyRequired', [
            'errorField' => 'grid_view_id',
            'message' => 'A preference must reference either a view ID or a view key.',
        ]);

        return $rules;
    }
}
