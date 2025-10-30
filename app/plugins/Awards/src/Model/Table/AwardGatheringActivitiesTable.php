<?php

declare(strict_types=1);

namespace Awards\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\ORM\RulesChecker;

/**
 * AwardGatheringActivities Model
 *
 * Join table for many-to-many relationship between Awards and GatheringActivities.
 * This table manages which gathering activities an award can be given out during.
 *
 * @property \Awards\Model\Table\AwardsTable&\Cake\ORM\Association\BelongsTo $Awards
 * @property \App\Model\Table\GatheringActivitiesTable&\Cake\ORM\Association\BelongsTo $GatheringActivities
 *
 * @method \Awards\Model\Entity\AwardGatheringActivity newEmptyEntity()
 * @method \Awards\Model\Entity\AwardGatheringActivity newEntity(array $data, array $options = [])
 * @method \Awards\Model\Entity\AwardGatheringActivity[] newEntities(array $data, array $options = [])
 * @method \Awards\Model\Entity\AwardGatheringActivity get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Awards\Model\Entity\AwardGatheringActivity findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \Awards\Model\Entity\AwardGatheringActivity patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \Awards\Model\Entity\AwardGatheringActivity[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \Awards\Model\Entity\AwardGatheringActivity|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \Awards\Model\Entity\AwardGatheringActivity saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class AwardGatheringActivitiesTable extends Table
{
    /**
     * Configure table metadata, behaviors, and associations for the AwardGatheringActivities table.
     *
     * Sets table name, display field and primary key, attaches Timestamp and Footprint behaviors,
     * and defines BelongsTo associations to Awards and GatheringActivities.
     *
     * @param array<string, mixed> $config Table configuration.
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('award_gathering_activities');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
        $this->addBehavior('Muffin/Footprint.Footprint');

        $this->belongsTo('Awards', [
            'foreignKey' => 'award_id',
            'joinType' => 'INNER',
            'className' => 'Awards.Awards',
        ]);
        $this->belongsTo('GatheringActivities', [
            'foreignKey' => 'gathering_activity_id',
            'joinType' => 'INNER',
            'className' => 'GatheringActivities',
        ]);
    }

    /**
     * Configure validation rules ensuring `award_id` and `gathering_activity_id` are present, are integers greater than or equal to zero, and are not empty when creating a record.
     *
     * @param \Cake\Validation\Validator $validator Validator instance to modify.
     * @return \Cake\Validation\Validator The configured validator.
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->nonNegativeInteger('award_id')
            ->requirePresence('award_id', 'create')
            ->notEmptyString('award_id');

        $validator
            ->nonNegativeInteger('gathering_activity_id')
            ->requirePresence('gathering_activity_id', 'create')
            ->notEmptyString('gathering_activity_id');

        return $validator;
    }

    /**
     * Configure application integrity rules for award-gathering activity associations.
     *
     * Adds rules that require the referenced Award and GatheringActivity records to exist
     * and enforces that each (award_id, gathering_activity_id) pair is unique.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker The configured RulesChecker.
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['award_id'], 'Awards'), ['errorField' => 'award_id']);
        $rules->add($rules->existsIn(['gathering_activity_id'], 'GatheringActivities'), ['errorField' => 'gathering_activity_id']);

        // Ensure unique combination of award_id and gathering_activity_id
        $rules->add(
            function ($entity, $options) {
                $conditions = [
                    'award_id' => $entity->award_id,
                    'gathering_activity_id' => $entity->gathering_activity_id,
                ];
                // Exclude current record when updating
                if (!$entity->isNew()) {
                    $conditions['id !='] = $entity->id;
                }
                $exists = $this->find()
                    ->where($conditions)
                    ->count();
                return $exists === 0;
            },
            'uniquePair',
            [
                'errorField' => 'gathering_activity_id',
                'message' => 'This activity is already associated with this award.'
            ]
        );

        return $rules;
    }
}