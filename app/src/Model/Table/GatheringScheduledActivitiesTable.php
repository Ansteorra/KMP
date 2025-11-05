<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\I18n\FrozenTime;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\ORM\RulesChecker;

/**
 * GatheringScheduledActivities Model
 *
 * Manages scheduled activities within gatherings. Each scheduled activity
 * has a specific start/end time, custom title/description, and can either
 * reference an existing gathering activity or be marked as "other".
 *
 * @property \App\Model\Table\GatheringsTable&\Cake\ORM\Association\BelongsTo $Gatherings
 * @property \App\Model\Table\GatheringActivitiesTable&\Cake\ORM\Association\BelongsTo $GatheringActivities
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $Creators
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $Modifiers
 *
 * @method \App\Model\Entity\GatheringScheduledActivity newEmptyEntity()
 * @method \App\Model\Entity\GatheringScheduledActivity newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\GatheringScheduledActivity[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\GatheringScheduledActivity get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\GatheringScheduledActivity findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\GatheringScheduledActivity patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\GatheringScheduledActivity[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\GatheringScheduledActivity|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\GatheringScheduledActivity saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class GatheringScheduledActivitiesTable extends Table
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

        $this->setTable('gathering_scheduled_activities');
        $this->setDisplayField('display_title');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
        $this->addBehavior('Muffin/Footprint.Footprint');

        // Association to gatherings table
        $this->belongsTo('Gatherings', [
            'foreignKey' => 'gathering_id',
            'joinType' => 'INNER',
        ]);

        // Association to gathering_activities table (optional)
        $this->belongsTo('GatheringActivities', [
            'foreignKey' => 'gathering_activity_id',
            'joinType' => 'LEFT',
        ]);

        // Association to members for audit trails
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

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('gathering_id')
            ->notEmptyString('gathering_id', 'Please select a gathering');

        $validator
            ->integer('gathering_activity_id')
            ->allowEmptyString('gathering_activity_id');

        $validator
            ->dateTime('start_datetime')
            ->requirePresence('start_datetime', 'create')
            ->notEmptyDateTime('start_datetime', 'Start date and time is required');

        $validator
            ->dateTime('end_datetime')
            ->allowEmptyDateTime('end_datetime')
            ->add('end_datetime', 'afterStart', [
                'rule' => function ($value, $context) {
                    // Only validate if end_datetime is provided
                    if (isset($context['data']['start_datetime']) && $value && $context['data']['start_datetime']) {
                        return $value > $context['data']['start_datetime'];
                    }
                    return true;
                },
                'message' => 'End date/time must be after start date/time',
            ])
            ->add('end_datetime', 'requiredIfHasEndTime', [
                'rule' => function ($value, $context) {
                    // If has_end_time is true, end_datetime must be provided
                    if (!empty($context['data']['has_end_time'])) {
                        return !empty($value);
                    }
                    return true;
                },
                'message' => 'End date/time is required when "Has End Time" is checked',
            ]);

        $validator
            ->boolean('has_end_time')
            ->notEmptyString('has_end_time');

        $validator
            ->scalar('display_title')
            ->maxLength('display_title', 255)
            ->requirePresence('display_title', 'create')
            ->notEmptyString('display_title', 'Display title is required');

        $validator
            ->scalar('description')
            ->allowEmptyString('description');

        $validator
            ->boolean('pre_register')
            ->notEmptyString('pre_register');

        $validator
            ->boolean('is_other')
            ->notEmptyString('is_other');

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
        $rules->add($rules->existsIn('gathering_id', 'Gatherings'), [
            'errorField' => 'gathering_id',
            'message' => 'Invalid gathering selected',
        ]);

        // Only validate gathering_activity_id if it's not null
        $rules->add(function ($entity, $options) {
            if ($entity->gathering_activity_id === null) {
                return true;
            }
            return $this->GatheringActivities->exists(['id' => $entity->gathering_activity_id]);
        }, 'validGatheringActivity', [
            'errorField' => 'gathering_activity_id',
            'message' => 'Invalid gathering activity selected',
        ]);

        // Validate that if is_other is false, gathering_activity_id must be set
        $rules->add(function ($entity, $options) {
            if (!$entity->is_other && empty($entity->gathering_activity_id)) {
                return false;
            }
            return true;
        }, 'activityRequired', [
            'errorField' => 'gathering_activity_id',
            'message' => 'Gathering activity is required when not marked as "other"',
        ]);

        // Validate that if is_other is true, gathering_activity_id should be null
        $rules->add(function ($entity, $options) {
            if ($entity->is_other && !empty($entity->gathering_activity_id)) {
                return false;
            }
            return true;
        }, 'noActivityForOther', [
            'errorField' => 'gathering_activity_id',
            'message' => 'Gathering activity should not be set for "other" activities',
        ]);

        // Validate that scheduled activity falls within gathering date range
        $rules->add(function ($entity, $options) {
            if (empty($entity->gathering_id) || empty($entity->start_datetime)) {
                return true; // Let other validators handle missing required fields
            }

            // Get the gathering to check date range (including timezone)
            $gathering = $this->Gatherings->find()
                ->select(['id', 'start_date', 'end_date', 'timezone'])
                ->where(['id' => $entity->gathering_id])
                ->first();
            if ($gathering === null) {
                return false;
            }

            // Ensure start_date is present
            if (empty($gathering->start_date)) {
                return false;
            }

            // Get gathering timezone (or default)
            $gatheringTimezone = !empty($gathering->timezone) ? $gathering->timezone :
                \App\KMP\TimezoneHelper::getDefaultTimezone();

            // The gathering's start_date and end_date are already DateTime objects in UTC
            // We just need to ensure they're DateTime objects for comparison
            $gatheringStart = $gathering->start_date;
            if (!($gatheringStart instanceof \Cake\I18n\DateTime)) {
                $gatheringStart = new \Cake\I18n\DateTime($gatheringStart);
            }

            $gatheringEnd = $gathering->end_date;
            if (!($gatheringEnd instanceof \Cake\I18n\DateTime)) {
                $gatheringEnd = new \Cake\I18n\DateTime($gatheringEnd);
            }

            // Ensure we're comparing DateTime objects - convert entity values to DateTime if needed
            $startDatetime = $entity->start_datetime;
            if (is_string($startDatetime)) {
                $startDatetime = new \Cake\I18n\DateTime($startDatetime);
            }

            $endDatetime = $entity->end_datetime;
            if (is_string($endDatetime)) {
                $endDatetime = new \Cake\I18n\DateTime($endDatetime);
            }

            // Check if scheduled activity start is within range (both are now in UTC)
            if ($startDatetime < $gatheringStart || $startDatetime > $gatheringEnd) {
                return false;
            }

            // Check if scheduled activity end is within range (only if end_datetime is provided)
            if (!empty($endDatetime)) {
                if ($endDatetime < $gatheringStart || $endDatetime > $gatheringEnd) {
                    return false;
                }
            }

            return true;
        }, 'withinGatheringDates', [
            'errorField' => 'start_datetime',
            'message' => function ($entity, $options) {
                if (empty($entity->gathering_id)) {
                    return 'Scheduled activity must fall within the gathering dates';
                }

                $gathering = $this->Gatherings->find()
                    ->select(['start_date', 'end_date', 'timezone'])
                    ->where(['id' => $entity->gathering_id])
                    ->first();

                if ($gathering === null || empty($gathering->start_date)) {
                    return 'Scheduled activity must fall within the gathering dates';
                }

                // Get gathering timezone for proper date display
                $gatheringTimezone = !empty($gathering->timezone) ? $gathering->timezone :
                    \App\KMP\TimezoneHelper::getDefaultTimezone();

                // Convert UTC dates to gathering timezone for display in error message
                $startInGatheringTz = \App\KMP\TimezoneHelper::toUserTimezone(
                    $gathering->start_date,
                    null,
                    $gatheringTimezone
                );
                $endInGatheringTz = \App\KMP\TimezoneHelper::toUserTimezone(
                    $gathering->end_date,
                    null,
                    $gatheringTimezone
                );

                $startDateStr = $startInGatheringTz->format('M j, Y g:i A');
                $endDateStr = $endInGatheringTz->format('M j, Y g:i A');

                return sprintf(
                    'Scheduled activity must fall within the gathering dates (%s %s to %s %s)',
                    $startDateStr,
                    $gatheringTimezone,
                    $endDateStr,
                    $gatheringTimezone
                );
            }
        ]);

        return $rules;
    }

    /**
     * Find scheduled activities ordered by start time
     *
     * @param \Cake\ORM\Query\SelectQuery $query The query object
     * @param array $options Options array
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findOrdered(\Cake\ORM\Query\SelectQuery $query, array $options): \Cake\ORM\Query\SelectQuery
    {
        return $query->orderBy(['start_datetime' => 'ASC']);
    }

    /**
     * Find scheduled activities for a specific gathering
     *
     * @param \Cake\ORM\Query\SelectQuery $query The query object
     * @param array $options Options array (requires 'gathering_id' key)
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findByGathering(\Cake\ORM\Query\SelectQuery $query, array $options): \Cake\ORM\Query\SelectQuery
    {
        return $query
            ->where(['GatheringScheduledActivities.gathering_id' => $options['gathering_id']])
            ->contain(['GatheringActivities', 'Creators', 'Modifiers'])
            ->orderBy(['start_datetime' => 'ASC']);
    }
}
