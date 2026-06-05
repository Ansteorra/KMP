<?php
declare(strict_types=1);

namespace Awards\Model\Table;

use App\Model\Table\BaseTable;
use Awards\Model\Entity\Recommendation;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * BestowalStates Table - Manages bestowal workflow states.
 *
 * @property \Awards\Model\Table\BestowalStatusesTable&\Cake\ORM\Association\BelongsTo $BestowalStatuses
 * @property \Awards\Model\Table\BestowalStateFieldRulesTable&\Cake\ORM\Association\HasMany $BestowalStateFieldRules
 * @property \Awards\Model\Table\BestowalStateTransitionsTable&\Cake\ORM\Association\HasMany $OutgoingTransitions
 * @property \Awards\Model\Table\BestowalStateTransitionsTable&\Cake\ORM\Association\HasMany $IncomingTransitions
 * @method \Awards\Model\Entity\BestowalState newEmptyEntity()
 * @method \Awards\Model\Entity\BestowalState newEntity(array $data, array $options = [])
 * @method \Awards\Model\Entity\BestowalState get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Awards\Model\Entity\BestowalState patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \Awards\Model\Entity\BestowalState|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \Awards\Model\Entity\BestowalState saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 * @mixin \Muffin\Footprint\Model\Behavior\FootprintBehavior
 * @mixin \Muffin\Trash\Model\Behavior\TrashBehavior
 */
class BestowalStatesTable extends BaseTable
{
    /**
     * @param array<string, mixed> $config Configuration options
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('awards_bestowal_states');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->belongsTo('BestowalStatuses', [
            'foreignKey' => 'status_id',
            'joinType' => 'INNER',
            'className' => 'Awards.BestowalStatuses',
        ]);

        $this->hasMany('BestowalStateFieldRules', [
            'foreignKey' => 'state_id',
            'className' => 'Awards.BestowalStateFieldRules',
            'dependent' => true,
            'cascadeCallbacks' => true,
        ]);

        $this->hasMany('OutgoingTransitions', [
            'foreignKey' => 'from_state_id',
            'className' => 'Awards.BestowalStateTransitions',
            'dependent' => true,
            'cascadeCallbacks' => true,
        ]);

        $this->hasMany('IncomingTransitions', [
            'foreignKey' => 'to_state_id',
            'className' => 'Awards.BestowalStateTransitions',
            'dependent' => true,
            'cascadeCallbacks' => true,
        ]);

        $this->addBehavior('Timestamp');
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->addBehavior('Muffin/Trash.Trash');
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name')
            ->add('name', 'unique', ['rule' => 'validateUnique', 'provider' => 'table']);

        $validator
            ->integer('status_id')
            ->requirePresence('status_id', 'create')
            ->notEmptyString('status_id');

        $validator
            ->integer('sort_order')
            ->requirePresence('sort_order', 'create')
            ->notEmptyString('sort_order');

        $validator
            ->scalar('sync_recommendation_state')
            ->maxLength('sync_recommendation_state', 255)
            ->allowEmptyString('sync_recommendation_state');

        $validator
            ->scalar('unwind_recommendation_state')
            ->maxLength('unwind_recommendation_state', 255)
            ->allowEmptyString('unwind_recommendation_state');

        $validator
            ->boolean('locks_recommendations')
            ->notEmptyString('locks_recommendations');

        $validator
            ->boolean('supports_gathering')
            ->notEmptyString('supports_gathering');

        $validator
            ->boolean('is_hidden')
            ->notEmptyString('is_hidden');

        $validator
            ->boolean('is_system')
            ->notEmptyString('is_system');

        $validator
            ->integer('created_by')
            ->allowEmptyString('created_by');

        $validator
            ->integer('modified_by')
            ->allowEmptyString('modified_by');

        $validator
            ->dateTime('deleted')
            ->allowEmptyDateTime('deleted');

        return $validator;
    }

    /**
     * @param \Cake\ORM\RulesChecker $rules The rules object
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['name']), ['errorField' => 'name']);
        $rules->add($rules->existsIn(['status_id'], 'BestowalStatuses'), [
            'errorField' => 'status_id',
            'message' => 'Invalid status.',
        ]);

        // Recommendation state mappings are YAML state-name strings (not DB FKs).
        // Replace the dropped foreign-key integrity with an in-list domain rule so
        // a non-empty mapping must be a valid Recommendation state.
        $validStates = Recommendation::getStates();
        $rules->add(
            function ($entity) use ($validStates): bool {
                $sync = $entity->sync_recommendation_state;

                return $sync === null || $sync === '' || in_array($sync, $validStates, true);
            },
            'validSyncRecommendationState',
            [
                'errorField' => 'sync_recommendation_state',
                'message' => 'Invalid recommendation state for sync mapping.',
            ],
        );
        $rules->add(
            function ($entity) use ($validStates): bool {
                $unwind = $entity->unwind_recommendation_state;

                return $unwind === null || $unwind === '' || in_array($unwind, $validStates, true);
            },
            'validUnwindRecommendationState',
            [
                'errorField' => 'unwind_recommendation_state',
                'message' => 'Invalid recommendation state for unwind mapping.',
            ],
        );

        // Prevent editing system states
        $rules->addUpdate(function ($entity) {
            if ($entity->getOriginal('is_system') && $entity->isDirty()) {
                return false;
            }

            return true;
        }, 'systemStateProtection', [
            'errorField' => 'is_system',
            'message' => 'System states cannot be modified.',
        ]);

        // Prevent deleting system states
        $rules->addDelete(function ($entity) {
            return !$entity->is_system;
        }, 'systemStateDeleteProtection', [
            'errorField' => 'is_system',
            'message' => 'System states cannot be deleted.',
        ]);

        return $rules;
    }
}
