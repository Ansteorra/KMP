<?php
declare(strict_types=1);

namespace Awards\Model\Table;

use App\Model\Table\BaseTable;
use ArrayObject;
use Awards\Model\Entity\Bestowal;
use Awards\Services\CourtAgendaService;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * Manages award bestowals and their workflow-facing persistence rules.
 *
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $Members
 * @property \App\Model\Table\GatheringsTable&\Cake\ORM\Association\BelongsTo $Gatherings
 * @property \App\Model\Table\GatheringScheduledActivitiesTable&\Cake\ORM\Association\BelongsTo $GatheringScheduledActivities
 * @property \Awards\Model\Table\RecommendationsTable&\Cake\ORM\Association\BelongsTo $PrimaryRecommendation
 * @property \Awards\Model\Table\AwardsTable&\Cake\ORM\Association\BelongsTo $Awards
 * @property \Awards\Model\Table\RecommendationsTable&\Cake\ORM\Association\BelongsToMany $Recommendations
 * @property \Awards\Model\Table\BestowalRecommendationsTable&\Cake\ORM\Association\HasMany $BestowalRecommendations
 * @property \Awards\Model\Table\RecommendationApprovalRunsTable&\Cake\ORM\Association\BelongsTo $SourceApprovalRun
 * @method \Awards\Model\Entity\Bestowal newEmptyEntity()
 * @method \Awards\Model\Entity\Bestowal newEntity(array $data, array $options = [])
 * @method \Awards\Model\Entity\Bestowal get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Awards\Model\Entity\Bestowal patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \Awards\Model\Entity\Bestowal|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \Awards\Model\Entity\Bestowal saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 * @mixin \Muffin\Footprint\Model\Behavior\FootprintBehavior
 * @mixin \Muffin\Trash\Model\Behavior\TrashBehavior
 */
class BestowalsTable extends BaseTable
{
    /**
     * @param array<string, mixed> $config Table configuration options
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('awards_bestowals');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->addBehavior('Muffin/Trash.Trash');

        $this->belongsTo('Members', [
            'foreignKey' => 'member_id',
            'joinType' => 'LEFT',
            'className' => 'Members',
        ]);
        $this->belongsTo('Gatherings', [
            'foreignKey' => 'gathering_id',
            'joinType' => 'LEFT',
            'className' => 'Gatherings',
        ]);
        $this->belongsTo('GatheringScheduledActivities', [
            'foreignKey' => 'gathering_scheduled_activity_id',
            'joinType' => 'LEFT',
            'className' => 'GatheringScheduledActivities',
        ]);
        $this->belongsTo('PrimaryRecommendation', [
            'foreignKey' => 'primary_recommendation_id',
            'joinType' => 'LEFT',
            'className' => 'Awards.Recommendations',
        ]);
        $this->belongsTo('Awards', [
            'foreignKey' => 'award_id',
            'joinType' => 'LEFT',
            'className' => 'Awards.Awards',
        ]);
        $this->belongsToMany('Recommendations', [
            'joinTable' => 'awards_bestowal_recommendations',
            'foreignKey' => 'bestowal_id',
            'targetForeignKey' => 'recommendation_id',
            'className' => 'Awards.Recommendations',
        ]);
        $this->hasMany('BestowalRecommendations', [
            'foreignKey' => 'bestowal_id',
            'className' => 'Awards.BestowalRecommendations',
            'dependent' => true,
            'cascadeCallbacks' => true,
        ]);
        $this->belongsTo('SourceApprovalRun', [
            'foreignKey' => 'source_approval_run_id',
            'className' => 'Awards.RecommendationApprovalRuns',
        ]);
    }

    /**
     * Default validation rules for bestowal data.
     *
     * @param \Cake\Validation\Validator $validator Validator instance
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('member_id')
            ->allowEmptyString('member_id');

        $validator
            ->scalar('member_sca_name')
            ->maxLength('member_sca_name', 255)
            ->requirePresence('member_sca_name', 'create')
            ->notEmptyString('member_sca_name');

        $validator
            ->integer('gathering_id')
            ->allowEmptyString('gathering_id');

        $validator
            ->integer('gathering_scheduled_activity_id')
            ->allowEmptyString('gathering_scheduled_activity_id');

        $validator
            ->boolean('roaming_court')
            ->allowEmptyString('roaming_court');

        $validator
            ->integer('primary_recommendation_id')
            ->allowEmptyString('primary_recommendation_id');

        $validator
            ->integer('award_id')
            ->requirePresence('award_id', 'create')
            ->requirePresence('award_id', 'update')
            ->notEmptyString('award_id', __('Award to Bestow is required.'));

        $validator
            ->scalar('specialty')
            ->maxLength('specialty', 255)
            ->allowEmptyString('specialty');

        $validator
            ->scalar('lifecycle_status')
            ->maxLength('lifecycle_status', 20)
            ->requirePresence('lifecycle_status', 'create')
            ->notEmptyString('lifecycle_status')
            ->inList('lifecycle_status', Bestowal::LIFECYCLE_STATUSES);

        $validator
            ->integer('stack_rank')
            ->notEmptyString('stack_rank');

        $validator
            ->dateTime('bestowed_at')
            ->allowEmptyDateTime('bestowed_at');

        $validator
            ->scalar('source')
            ->maxLength('source', 50)
            ->notEmptyString('source')
            ->inList('source', [Bestowal::SOURCE_RECOMMENDATION, Bestowal::SOURCE_AD_HOC]);

        $validator
            ->scalar('noble_notes')
            ->allowEmptyString('noble_notes');

        $validator
            ->scalar('herald_notes')
            ->allowEmptyString('herald_notes');

        $validator
            ->scalar('reason_summary')
            ->allowEmptyString('reason_summary');

        $validator
            ->scalar('call_into_court')
            ->maxLength('call_into_court', 100)
            ->allowEmptyString('call_into_court');

        $validator
            ->scalar('court_availability')
            ->maxLength('court_availability', 100)
            ->allowEmptyString('court_availability');

        $validator
            ->scalar('person_to_notify')
            ->maxLength('person_to_notify', 255)
            ->allowEmptyString('person_to_notify');

        $validator
            ->scalar('close_reason')
            ->allowEmptyString('close_reason');

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
        $rules->add($rules->existsIn(['member_id'], 'Members'), ['errorField' => 'member_id']);
        $rules->add($rules->existsIn(['gathering_id'], 'Gatherings'), ['errorField' => 'gathering_id']);
        $rules->add(
            function (Bestowal $entity) {
                if ($entity->roaming_court) {
                    return $entity->gathering_scheduled_activity_id === null;
                }
                if ($entity->gathering_scheduled_activity_id === null) {
                    return true;
                }

                return $this->GatheringScheduledActivities->exists([
                    'id' => $entity->gathering_scheduled_activity_id,
                ]);
            },
            'gatheringScheduledActivityValid',
            [
                'errorField' => 'gathering_scheduled_activity_id',
                'message' => 'Invalid court session.',
            ],
        );
        $rules->add($rules->existsIn(['primary_recommendation_id'], 'PrimaryRecommendation'), [
            'errorField' => 'primary_recommendation_id',
        ]);
        $rules->add($rules->existsIn(['award_id'], 'Awards'), ['errorField' => 'award_id']);

        return $rules;
    }

    /**
     * Derive the recipient display name from the linked member when it is omitted.
     *
     * `member_sca_name` is the required display source of truth for a bestowal, but
     * member-linked bestowals (recommendation-sourced or member-selected ad-hoc) do not
     * resubmit the recipient name. Backfill it from the member record so direct saves and
     * recommendation/court flows remain valid without forcing every caller to repeat it.
     * Ad-hoc bestowals without a member keep supplying the name explicitly.
     *
     * @param \Cake\Event\EventInterface $event The beforeMarshal event.
     * @param \ArrayObject $data Inbound marshalling data.
     * @param \ArrayObject $options Marshalling options.
     * @return void
     */
    public function beforeMarshal(EventInterface $event, ArrayObject $data, ArrayObject $options): void
    {
        $existing = trim((string)($data['member_sca_name'] ?? ''));
        if (
            $existing === '' && ($data['member_id'] ?? null) !== null
            && $data['member_id'] !== '' && is_numeric($data['member_id'])
        ) {
            $member = $this->Members->find()
                ->select(['sca_name'])
                ->where(['id' => (int)$data['member_id']])
                ->first();

            $scaName = $member !== null ? trim((string)$member->sca_name) : '';
            if ($scaName !== '') {
                $data['member_sca_name'] = $scaName;
            }
        }

        if (!isset($data['lifecycle_status']) || trim((string)$data['lifecycle_status']) === '') {
            $data['lifecycle_status'] = Bestowal::LIFECYCLE_OPEN;
        }
    }

    /**
     * Enforce bestowal workflow constraints before persisting changes.
     *
     * @param \Cake\Event\EventInterface $event The beforeSave event.
     * @param \Cake\Datasource\EntityInterface $entity The entity being persisted.
     * @param \ArrayObject $options Save operation options.
     * @return void
     */
    public function beforeSave(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        if (!$entity instanceof Bestowal) {
            return;
        }

        if ($entity->roaming_court) {
            $entity->gathering_scheduled_activity_id = null;
        } elseif ($entity->gathering_scheduled_activity_id !== null) {
            $entity->roaming_court = false;
        }
    }

    /**
     * Keep the gathering's court agenda in sync with the bestowal's court assignment.
     *
     * Whenever the gathering or court-slot fields change, the default court agenda
     * mirrors the assignment in real time (item created, moved, or removed), so the
     * agenda never needs a manual bestowal sync.
     *
     * @param \Cake\Event\EventInterface $event The afterSave event.
     * @param \Cake\Datasource\EntityInterface $entity The persisted entity.
     * @param \ArrayObject $options Save operation options.
     * @return void
     */
    public function afterSave($event, $entity, $options): void
    {
        parent::afterSave($event, $entity, $options);

        if (!$entity instanceof Bestowal) {
            return;
        }
        if (
            !$entity->isDirty('gathering_scheduled_activity_id')
            && !$entity->isDirty('roaming_court')
            && !$entity->isDirty('gathering_id')
        ) {
            return;
        }

        $actorId = $entity->modified_by !== null ? (int)$entity->modified_by : null;
        (new CourtAgendaService())->syncAgendaPlacementForBestowal($entity, $actorId);
    }

    /**
     * Apply branch-based filtering to a bestowals query via member or award branch.
     *
     * @param \Cake\ORM\Query\SelectQuery $query The query to modify.
     * @param array<int> $branchIDs Branch IDs to restrict bestowals to.
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function addBranchScopeQuery($query, $branchIDs): SelectQuery
    {
        if (empty($branchIDs)) {
            return $query;
        }

        return $query
            ->leftJoinWith('Members')
            ->leftJoinWith('Awards')
            ->where([
                'OR' => [
                    'Members.branch_id IN' => $branchIDs,
                    'Awards.branch_id IN' => $branchIDs,
                ],
            ]);
    }
}
