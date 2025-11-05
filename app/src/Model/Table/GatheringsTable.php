<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\ORM\RulesChecker;
use Cake\Event\EventInterface;
use Cake\Datasource\EntityInterface;
use Cake\ORM\TableRegistry;

/**
 * Gatherings Model
 *
 * @property \App\Model\Table\BranchesTable&\Cake\ORM\Association\BelongsTo $Branches
 * @property \App\Model\Table\GatheringTypesTable&\Cake\ORM\Association\BelongsTo $GatheringTypes
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $Creators
 * @property \App\Model\Table\GatheringActivitiesTable&\Cake\ORM\Association\BelongsToMany $GatheringActivities
 * @property \App\Model\Table\GatheringAttendancesTable&\Cake\ORM\Association\HasMany $GatheringAttendances
 * @property \App\Model\Table\GatheringScheduledActivitiesTable&\Cake\ORM\Association\HasMany $GatheringScheduledActivities
 * @property \App\Model\Table\GatheringStaffTable&\Cake\ORM\Association\HasMany $GatheringStaff
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
        $this->addBehavior('PublicId');

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

        // One-to-many relationship with scheduled activities
        $this->hasMany('GatheringScheduledActivities', [
            'foreignKey' => 'gathering_id',
            'dependent' => true,
            'sort' => ['GatheringScheduledActivities.start_datetime' => 'ASC'],
        ]);

        // One-to-many relationship with gathering staff
        $this->hasMany('GatheringStaff', [
            'foreignKey' => 'gathering_id',
            'dependent' => true,
            'sort' => ['GatheringStaff.is_steward' => 'DESC', 'GatheringStaff.sort_order' => 'ASC'],
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
            ->dateTime('start_date')
            ->requirePresence('start_date', 'create')
            ->notEmptyDateTime('start_date');

        $validator
            ->dateTime('end_date')
            ->allowEmptyDateTime('end_date')
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
            ->scalar('timezone')
            ->maxLength('timezone', 50)
            ->allowEmptyString('timezone')
            ->add('timezone', 'validTimezone', [
                'rule' => function ($value, $context) {
                    // Allow empty timezone (will fall back to user/app default)
                    if (empty($value)) {
                        return true;
                    }
                    // Validate using PHP's timezone list
                    try {
                        new \DateTimeZone($value);
                        return true;
                    } catch (\Exception $e) {
                        return false;
                    }
                },
                'message' => __('Invalid timezone identifier'),
            ]);

        $validator
            ->decimal('latitude')
            ->allowEmptyString('latitude')
            ->greaterThanOrEqual('latitude', -90)
            ->lessThanOrEqual('latitude', 90);

        $validator
            ->decimal('longitude')
            ->allowEmptyString('longitude')
            ->greaterThanOrEqual('longitude', -180)
            ->lessThanOrEqual('longitude', 180);

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

    /**
     * After save callback
     *
     * Syncs template activities from the gathering type when:
     * 1. A new gathering is created
     * 2. The gathering type is changed
     *
     * @param \Cake\Event\EventInterface $event The event object
     * @param \Cake\Datasource\EntityInterface $entity The saved entity
     * @param \ArrayObject $options Options passed to save
     * @return void
     */
    public function afterSave(EventInterface $event, EntityInterface $entity, \ArrayObject $options): void
    {
        // Only sync if gathering_type_id is present and the entity is new or gathering_type_id changed
        if ($entity->has('gathering_type_id') && ($entity->isNew() || $entity->isDirty('gathering_type_id'))) {
            $this->syncTemplateActivities($entity);
        }
    }

    /**
     * Sync template activities from the gathering type to the gathering
     *
     * This method:
     * 1. Fetches all template activities for the gathering type
     * 2. Adds any missing activities to the gathering
     * 3. Sets the not_removable flag based on the template
     *
     * @param \Cake\Datasource\EntityInterface $gathering The gathering entity
     * @return void
     */
    protected function syncTemplateActivities(EntityInterface $gathering): void
    {
        $gatheringTypeGatheringActivitiesTable = TableRegistry::getTableLocator()->get('GatheringTypeGatheringActivities');
        $gatheringsGatheringActivitiesTable = TableRegistry::getTableLocator()->get('GatheringsGatheringActivities');

        // Get template activities for this gathering type
        $templateActivities = $gatheringTypeGatheringActivitiesTable->find()
            ->where(['gathering_type_id' => $gathering->gathering_type_id])
            ->contain(['GatheringActivities'])
            ->all();

        if ($templateActivities->isEmpty()) {
            return;
        }

        // Get existing activities for this gathering
        $existingActivities = $gatheringsGatheringActivitiesTable->find()
            ->where(['gathering_id' => $gathering->id])
            ->all()
            ->indexBy('gathering_activity_id')
            ->toArray();

        // Find the max sort order to append new activities
        $maxSortOrder = 0;
        if (!empty($existingActivities)) {
            $maxSortOrder = max(array_column($existingActivities, 'sort_order'));
        }

        // Add missing template activities
        foreach ($templateActivities as $templateActivity) {
            $activityId = $templateActivity->gathering_activity_id;

            if (!isset($existingActivities[$activityId])) {
                // Activity doesn't exist, add it
                $maxSortOrder++;
                $newActivity = $gatheringsGatheringActivitiesTable->newEntity([
                    'gathering_id' => $gathering->id,
                    'gathering_activity_id' => $activityId,
                    'sort_order' => $maxSortOrder
                ]);

                $newActivity->not_removable = $templateActivity->not_removable;
                $gatheringsGatheringActivitiesTable->save($newActivity);
            } else {
                // Activity exists, update not_removable if template says it should be
                if ($templateActivity->not_removable && !$existingActivities[$activityId]->not_removable) {
                    $existingActivity = $existingActivities[$activityId];
                    $existingActivity->not_removable = true;
                    $gatheringsGatheringActivitiesTable->save($existingActivity);
                }
            }
        }
    }
}
