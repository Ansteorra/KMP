<?php

declare(strict_types=1);

namespace Waivers\Model\Table;

use Cake\I18n\Date;
use Cake\I18n\DateTime;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * GatheringWaivers Model
 *
 * @property \App\Model\Table\GatheringsTable&\Cake\ORM\Association\BelongsTo $Gatherings
 * @property \Waivers\Model\Table\WaiverTypesTable&\Cake\ORM\Association\BelongsTo $WaiverTypes
 * @property \App\Model\Table\DocumentsTable&\Cake\ORM\Association\BelongsTo $Documents
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $CreatedByMembers
 * @property \Waivers\Model\Table\GatheringWaiverActivitiesTable&\Cake\ORM\Association\HasMany $GatheringWaiverActivities
 * @property \App\Model\Table\NotesTable&\Cake\ORM\Association\HasMany $AuditNotes
 *
 * @method \Waivers\Model\Entity\GatheringWaiver newEmptyEntity()
 * @method \Waivers\Model\Entity\GatheringWaiver newEntity(array $data, array $options = [])
 * @method array<\Waivers\Model\Entity\GatheringWaiver> newEntities(array $data, array $options = [])
 * @method \Waivers\Model\Entity\GatheringWaiver get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Waivers\Model\Entity\GatheringWaiver findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \Waivers\Model\Entity\GatheringWaiver patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\Waivers\Model\Entity\GatheringWaiver> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \Waivers\Model\Entity\GatheringWaiver|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \Waivers\Model\Entity\GatheringWaiver saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\Waivers\Model\Entity\GatheringWaiver>|\Cake\Datasource\ResultSetInterface<\Waivers\Model\Entity\GatheringWaiver>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\Waivers\Model\Entity\GatheringWaiver>|\Cake\Datasource\ResultSetInterface<\Waivers\Model\Entity\GatheringWaiver> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\Waivers\Model\Entity\GatheringWaiver>|\Cake\Datasource\ResultSetInterface<\Waivers\Model\Entity\GatheringWaiver>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\Waivers\Model\Entity\GatheringWaiver>|\Cake\Datasource\ResultSetInterface<\Waivers\Model\Entity\GatheringWaiver> deleteManyOrFail(iterable $entities, array $options = [])
 */
class GatheringWaiversTable extends Table
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

        $this->setTable('waivers_gathering_waivers');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->addBehavior('Muffin/Trash.Trash');

        $this->belongsTo('Gatherings', [
            'foreignKey' => 'gathering_id',
            'joinType' => 'INNER',
            'className' => 'Gatherings',
        ]);
        $this->belongsTo('WaiverTypes', [
            'foreignKey' => 'waiver_type_id',
            'joinType' => 'INNER',
            'className' => 'Waivers.WaiverTypes',
        ]);
        $this->belongsTo('Documents', [
            'foreignKey' => 'document_id',
            'joinType' => 'LEFT',
            'className' => 'Documents',
        ]);
        $this->belongsTo('CreatedByMembers', [
            'foreignKey' => 'created_by',
            'className' => 'Members',
        ]);
        $this->belongsTo('DeclinedByMembers', [
            'foreignKey' => 'declined_by',
            'className' => 'Members',
        ]);
        $this->hasMany('GatheringWaiverActivities', [
            'foreignKey' => 'gathering_waiver_id',
            'className' => 'Waivers.GatheringWaiverActivities',
        ]);
        $this->hasMany('AuditNotes', [
            'foreignKey' => 'entity_id',
            'conditions' => ['AuditNotes.entity_type' => 'Waivers.GatheringWaivers'],
            'className' => 'Notes',
            'propertyName' => 'audit_notes',
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
            ->requirePresence('gathering_id', 'create')
            ->notEmptyString('gathering_id');

        $validator
            ->integer('waiver_type_id')
            ->requirePresence('waiver_type_id', 'create')
            ->notEmptyString('waiver_type_id');

        $validator
            ->integer('document_id')
            ->allowEmptyString('document_id');

        $validator
            ->scalar('status')
            ->inList('status', ['pending', 'active', 'deleted'])
            ->requirePresence('status', 'create')
            ->notEmptyString('status');

        $validator
            ->date('retention_date')
            ->requirePresence('retention_date', 'create')
            ->notEmptyDate('retention_date');

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
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['gathering_id'], 'Gatherings'), [
            'errorField' => 'gathering_id',
        ]);
        $rules->add($rules->existsIn(['waiver_type_id'], 'WaiverTypes'), [
            'errorField' => 'waiver_type_id',
        ]);
        $rules->add($rules->existsIn(['document_id'], 'Documents'), [
            'errorField' => 'document_id',
        ]);
        $rules->add($rules->existsIn(['created_by'], 'CreatedByMembers'), [
            'errorField' => 'created_by',
        ]);

        return $rules;
    }

    /**
     * Find expired waivers
     *
     * @param \Cake\ORM\Query\SelectQuery $query The query object
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findExpired(SelectQuery $query): SelectQuery
    {
        return $query->where([
            'GatheringWaivers.status' => 'active',
            'GatheringWaivers.retention_date <' => DateTime::now(),
        ]);
    }

    /**
     * Find waivers by status
     *
     * @param \Cake\ORM\Query\SelectQuery $query The query object
     * @param string $status The status to filter by
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findByStatus(SelectQuery $query, string $status): SelectQuery
    {
        return $query->where(['GatheringWaivers.status' => $status]);
    }

    /**
     * Find waivers for a specific gathering
     *
     * @param \Cake\ORM\Query\SelectQuery $query The query object
     * @param int $gatheringId The gathering ID
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findByGathering(SelectQuery $query, int $gatheringId): SelectQuery
    {
        return $query->where(['GatheringWaivers.gathering_id' => $gatheringId]);
    }

    /**
     * Find valid (non-declined) waivers for a specific gathering
     *
     * Returns waivers that have not been declined and are not soft-deleted.
     * This is useful for determining actual waiver coverage and compliance.
     *
     * @param \Cake\ORM\Query\SelectQuery $query The query object
     * @param int $gatheringId The gathering ID
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findValidByGathering(SelectQuery $query, int $gatheringId): SelectQuery
    {
        return $query->where([
            'GatheringWaivers.gathering_id' => $gatheringId,
            'GatheringWaivers.declined_at IS' => null,
            'GatheringWaivers.deleted IS' => null,
        ]);
    }

    /**
     * Count gatherings needing waivers for a specific user
     *
     * Returns the count of gatherings where:
     * - End date is today or in the future
     * - Have required waivers configured
     * - Missing at least one required waiver upload
     * - User has permission to upload waivers for the gathering's branch
     *
     * @param int $memberId The member ID to check permissions for
     * @return int Count of gatherings needing waivers
     */
    public static function countGatheringsNeedingWaivers(int $memberId): int
    {
        $membersTable = \Cake\ORM\TableRegistry::getTableLocator()->get('Members');
        $member = $membersTable->get($memberId);

        // Get branches user can upload waivers for
        $branchIds = $member->getBranchIdsForAction('add', 'Waivers.GatheringWaivers');

        // If user has no permissions, return 0
        if (is_array($branchIds) && empty($branchIds)) {
            return 0;
        }

        // If null (global permission), get all branches
        if ($branchIds === null) {
            $branchesTable = \Cake\ORM\TableRegistry::getTableLocator()->get('Branches');
            $branchIds = $branchesTable->find()
                ->select(['id'])
                ->all()
                ->extract('id')
                ->toArray();
        }

        $gatheringsTable = \Cake\ORM\TableRegistry::getTableLocator()->get('Gatherings');
        $gatheringWaiversTable = \Cake\ORM\TableRegistry::getTableLocator()->get('Waivers.GatheringWaivers');
        $activityWaiversTable = \Cake\ORM\TableRegistry::getTableLocator()->get('Waivers.GatheringActivityWaivers');

        $today = Date::now()->toDateString();
        $oneWeekFromNow = Date::now()->addDays(7)->toDateString();

        // Find gatherings in user's branches that are:
        // - Not yet ended (ongoing or future)
        // - Either already started OR starting within next 7 days
        $gatherings = $gatheringsTable->find()
            ->where([
                'OR' => [
                    'Gatherings.end_date >=' => $today,
                    'AND' => [
                        'Gatherings.end_date IS' => null,
                        'Gatherings.start_date >=' => $today,
                    ]
                ],
                'OR' => [
                    'Gatherings.start_date <' => $today, // Already started (past or ongoing)
                    'Gatherings.start_date <=' => $oneWeekFromNow, // Starts within next 7 days
                ],
                'Gatherings.branch_id IN' => $branchIds,
                'Gatherings.deleted IS' => null,
            ])
            ->contain(['GatheringActivities' => function ($q) {
                return $q->select(['id']);
            }])
            ->all();

        $count = 0;

        foreach ($gatherings as $gathering) {
            if (empty($gathering->gathering_activities)) {
                continue;
            }

            $activityIds = collection($gathering->gathering_activities)->extract('id')->toArray();

            // Get required waiver types
            $requiredWaiverTypes = $activityWaiversTable->find()
                ->where([
                    'gathering_activity_id IN' => $activityIds,
                    'deleted IS' => null,
                ])
                ->select(['waiver_type_id'])
                ->distinct(['waiver_type_id'])
                ->all()
                ->extract('waiver_type_id')
                ->toArray();

            if (empty($requiredWaiverTypes)) {
                continue;
            }

            // Get uploaded waiver types
            $uploadedWaiverTypes = $gatheringWaiversTable->find()
                ->where([
                    'gathering_id' => $gathering->id,
                    'deleted IS' => null,
                    'declined_at IS' => null, // Exclude declined waivers
                ])
                ->select(['waiver_type_id'])
                ->distinct(['waiver_type_id'])
                ->all()
                ->extract('waiver_type_id')
                ->toArray();

            // Check if any required waivers are missing
            $missingWaiverTypes = array_diff($requiredWaiverTypes, $uploadedWaiverTypes);

            if (!empty($missingWaiverTypes)) {
                $count++;
            }
        }

        return $count;
    }
}
