<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\ORM\RulesChecker;

/**
 * Gatherings Model
 *
 * @property \App\Model\Table\BranchesTable&\Cake\ORM\Association\BelongsTo $Branches
 * @property \App\Model\Table\GatheringTypesTable&\Cake\ORM\Association\BelongsTo $GatheringTypes
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $Creators
 * @property \App\Model\Table\GatheringActivitiesTable&\Cake\ORM\Association\BelongsToMany $GatheringActivities
 * @property \App\Model\Table\GatheringAttendancesTable&\Cake\ORM\Association\HasMany $GatheringAttendances
 * @property \Waivers\Model\Table\GatheringWaiversTable&\Cake\ORM\Association\HasMany $GatheringWaivers
 *
 * @method \App\Model\Entity\Gathering newEmptyEntity()
 * @method \App\Model\Entity\Gathering newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\Gathering[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Gathering get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Gathering findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\Gathering patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Gathering[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Gathering|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Gathering saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class GatheringsTable extends Table
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

        $this->setTable('gatherings');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->addBehavior('Muffin/Trash.Trash', [
            'field' => 'deleted'
        ]);

        $this->belongsTo('Branches', [
            'foreignKey' => 'branch_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('GatheringTypes', [
            'foreignKey' => 'gathering_type_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('Creators', [
            'className' => 'Members',
            'foreignKey' => 'created_by',
            'joinType' => 'INNER',
        ]);

        // Many-to-many relationship with GatheringActivities through join table
        $this->belongsToMany('GatheringActivities', [
            'foreignKey' => 'gathering_id',
            'targetForeignKey' => 'gathering_activity_id',
            'joinTable' => 'gatherings_gathering_activities',
            'through' => 'GatheringsGatheringActivities',
            'sort' => ['GatheringsGatheringActivities.sort_order' => 'ASC'],
            'dependent' => true,
        ]);

        // One-to-many relationship with attendance records
        $this->hasMany('GatheringAttendances', [
            'foreignKey' => 'gathering_id',
            'dependent' => true,
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
            ->nonNegativeInteger('branch_id')
            ->requirePresence('branch_id', 'create')
            ->notEmptyString('branch_id');

        $validator
            ->nonNegativeInteger('gathering_type_id')
            ->requirePresence('gathering_type_id', 'create')
            ->notEmptyString('gathering_type_id');

        $validator
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        $validator
            ->scalar('description')
            ->allowEmptyString('description');

        $validator
            ->date('start_date')
            ->requirePresence('start_date', 'create')
            ->notEmptyDate('start_date');

        $validator
            ->date('end_date')
            ->allowEmptyDate('end_date')
            ->add('end_date', 'validEndDate', [
                'rule' => function ($value, $context) {
                    // If end_date is empty, validation passes (will be defaulted in controller)
                    if (empty($value)) {
                        return true;
                    }
                    // If start_date is empty, skip this validation
                    if (empty($context['data']['start_date'])) {
                        return true;
                    }
                    // Ensure end_date is on or after start_date
                    return $value >= $context['data']['start_date'];
                },
                'message' => __('End date must be on or after start date'),
            ]);

        $validator
            ->scalar('location')
            ->maxLength('location', 255)
            ->allowEmptyString('location');

        $validator
            ->nonNegativeInteger('created_by')
            ->requirePresence('created_by', 'create')
            ->notEmptyString('created_by');

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
        $rules->add($rules->existsIn(['branch_id'], 'Branches'), ['errorField' => 'branch_id']);
        $rules->add($rules->existsIn(['gathering_type_id'], 'GatheringTypes'), ['errorField' => 'gathering_type_id']);
        $rules->add($rules->existsIn(['created_by'], 'Creators'), ['errorField' => 'created_by']);

        return $rules;
    }

    /**
     * Find gatherings by date range
     *
     * @param \Cake\ORM\Query\SelectQuery $query Query object
     * @param array $options Options including 'start' and 'end'
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findByDateRange(\Cake\ORM\Query\SelectQuery $query, array $options): \Cake\ORM\Query\SelectQuery
    {
        if (isset($options['start'])) {
            $query->where(['Gatherings.start_date >=' => $options['start']]);
        }
        if (isset($options['end'])) {
            $query->where(['Gatherings.end_date <=' => $options['end']]);
        }

        return $query;
    }

    /**
     * Find gatherings by branch
     *
     * @param \Cake\ORM\Query\SelectQuery $query Query object
     * @param array $options Options including 'branch_id'
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findByBranch(\Cake\ORM\Query\SelectQuery $query, array $options): \Cake\ORM\Query\SelectQuery
    {
        if (isset($options['branch_id'])) {
            $query->where(['Gatherings.branch_id' => $options['branch_id']]);
        }

        return $query;
    }

    /**
     * Find gatherings by type
     *
     * @param \Cake\ORM\Query\SelectQuery $query Query object
     * @param array $options Options including 'gathering_type_id'
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findByType(\Cake\ORM\Query\SelectQuery $query, array $options): \Cake\ORM\Query\SelectQuery
    {
        if (isset($options['gathering_type_id'])) {
            $query->where(['Gatherings.gathering_type_id' => $options['gathering_type_id']]);
        }

        return $query;
    }
}
