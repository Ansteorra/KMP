<?php

declare(strict_types=1);

namespace Waivers\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * GatheringWaiverExemptions Model
 *
 * Manages attestations that waivers were not needed for specific activity/waiver type combinations.
 *
 * @property \App\Model\Table\GatheringActivitiesTable&\Cake\ORM\Association\BelongsTo $GatheringActivities
 * @property \Waivers\Model\Table\WaiverTypesTable&\Cake\ORM\Association\BelongsTo $WaiverTypes
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $Members
 *
 * @method \Waivers\Model\Entity\GatheringWaiverExemption newEmptyEntity()
 * @method \Waivers\Model\Entity\GatheringWaiverExemption newEntity(array $data, array $options = [])
 * @method array<\Waivers\Model\Entity\GatheringWaiverExemption> newEntities(array $data, array $options = [])
 * @method \Waivers\Model\Entity\GatheringWaiverExemption get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Waivers\Model\Entity\GatheringWaiverExemption findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \Waivers\Model\Entity\GatheringWaiverExemption patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\Waivers\Model\Entity\GatheringWaiverExemption> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \Waivers\Model\Entity\GatheringWaiverExemption|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \Waivers\Model\Entity\GatheringWaiverExemption saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\Waivers\Model\Entity\GatheringWaiverExemption>|\Cake\Datasource\ResultSetInterface<\Waivers\Model\Entity\GatheringWaiverExemption>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\Waivers\Model\Entity\GatheringWaiverExemption>|\Cake\Datasource\ResultSetInterface<\Waivers\Model\Entity\GatheringWaiverExemption> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\Waivers\Model\Entity\GatheringWaiverExemption>|\Cake\Datasource\ResultSetInterface<\Waivers\Model\Entity\GatheringWaiverExemption>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\Waivers\Model\Entity\GatheringWaiverExemption>|\Cake\Datasource\ResultSetInterface<\Waivers\Model\Entity\GatheringWaiverExemption> deleteManyOrFail(iterable $entities, array $options = [])
 */
class GatheringWaiverExemptionsTable extends Table
{
    /**
     * Initialize method
     *
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('waivers_gathering_waiver_exemptions');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('GatheringActivities', [
            'foreignKey' => 'gathering_activity_id',
            'joinType' => 'INNER',
            'className' => 'GatheringActivities',
        ]);
        $this->belongsTo('WaiverTypes', [
            'foreignKey' => 'waiver_type_id',
            'joinType' => 'INNER',
            'className' => 'Waivers.WaiverTypes',
        ]);
        $this->belongsTo('Members', [
            'foreignKey' => 'member_id',
            'joinType' => 'INNER',
            'className' => 'Members',
        ]);
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('gathering_activity_id')
            ->requirePresence('gathering_activity_id', 'create')
            ->notEmptyString('gathering_activity_id');

        $validator
            ->integer('waiver_type_id')
            ->requirePresence('waiver_type_id', 'create')
            ->notEmptyString('waiver_type_id');

        $validator
            ->scalar('reason')
            ->maxLength('reason', 500)
            ->requirePresence('reason', 'create')
            ->notEmptyString('reason');

        $validator
            ->integer('member_id')
            ->requirePresence('member_id', 'create')
            ->notEmptyString('member_id');

        $validator
            ->scalar('notes')
            ->maxLength('notes', 65535)
            ->allowEmptyString('notes');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['gathering_activity_id'], 'GatheringActivities'), ['errorField' => 'gathering_activity_id']);
        $rules->add($rules->existsIn(['waiver_type_id'], 'WaiverTypes'), ['errorField' => 'waiver_type_id']);
        $rules->add($rules->existsIn(['member_id'], 'Members'), ['errorField' => 'member_id']);

        // Ensure only one exemption per activity/waiver type combination
        $rules->add(
            function ($entity, $options) {
                $conditions = [
                    'gathering_activity_id' => $entity->gathering_activity_id,
                    'waiver_type_id' => $entity->waiver_type_id,
                ];
                // Allow update of existing exemption
                if (!$entity->isNew()) {
                    $conditions['id !='] = $entity->id;
                }
                return !$this->exists($conditions);
            },
            'uniqueExemption',
            [
                'errorField' => 'gathering_activity_id',
                'message' => 'An exemption already exists for this activity and waiver type combination.'
            ]
        );

        return $rules;
    }
}
