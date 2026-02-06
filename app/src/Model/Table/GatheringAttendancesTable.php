<?php

declare(strict_types=1);

namespace App\Model\Table;

use ArrayObject;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\ORM\RulesChecker;

/**
 * GatheringAttendances Model
 *
 * Join table connecting members to gatherings with attendance information
 * and various sharing permission options.
 *
 * @property \App\Model\Table\GatheringsTable&\Cake\ORM\Association\BelongsTo $Gatherings
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $Members
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $Creators
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $Modifiers
 *
 * @method \App\Model\Entity\GatheringAttendance newEmptyEntity()
 * @method \App\Model\Entity\GatheringAttendance newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\GatheringAttendance[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\GatheringAttendance get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\GatheringAttendance findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\GatheringAttendance patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\GatheringAttendance[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\GatheringAttendance|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\GatheringAttendance saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class GatheringAttendancesTable extends Table
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

        $this->setTable('gathering_attendances');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->addBehavior('Muffin/Trash.Trash', [
            'field' => 'deleted'
        ]);

        $this->belongsTo('Gatherings', [
            'foreignKey' => 'gathering_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('Members', [
            'foreignKey' => 'member_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('Creators', [
            'className' => 'Members',
            'foreignKey' => 'created_by',
        ]);
        $this->belongsTo('Modifiers', [
            'className' => 'Members',
            'foreignKey' => 'modified_by',
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
            ->nonNegativeInteger('gathering_id')
            ->requirePresence('gathering_id', 'create')
            ->notEmptyString('gathering_id');

        $validator
            ->nonNegativeInteger('member_id')
            ->requirePresence('member_id', 'create')
            ->notEmptyString('member_id');

        $validator
            ->scalar('public_note')
            ->allowEmptyString('public_note')
            ->maxLength('public_note', 65535); // TEXT field max length

        $validator
            ->boolean('share_with_kingdom')
            ->notEmptyString('share_with_kingdom');

        $validator
            ->boolean('share_with_hosting_group')
            ->notEmptyString('share_with_hosting_group');

        $validator
            ->boolean('share_with_crown')
            ->notEmptyString('share_with_crown');

        $validator
            ->boolean('is_public')
            ->notEmptyString('is_public');

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
        $rules->add($rules->existsIn(['gathering_id'], 'Gatherings'), ['errorField' => 'gathering_id']);
        $rules->add($rules->existsIn(['member_id'], 'Members'), ['errorField' => 'member_id']);

        // Ensure unique combination of gathering_id and member_id
        $rules->add($rules->isUnique(['gathering_id', 'member_id'], [
            'allowMultipleNulls' => false,
            'message' => __('This member already has an attendance record for this gathering.')
        ]));

        return $rules;
    }

    /**
     * Enforce minor RSVP sharing rules.
     *
     * Members under 18 cannot share RSVPs with the kingdom.
     *
     * @param \Cake\Event\EventInterface $event Event
     * @param \Cake\Datasource\EntityInterface $entity Entity being saved
     * @param \ArrayObject $options Save options
     * @return void
     */
    public function beforeSave(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        $memberId = $entity->member_id ?? null;
        if (empty($memberId)) {
            return;
        }

        $member = $entity->member ?? null;
        if (!$member) {
            $member = $this->Members
                ->find()
                ->select(['Members.id', 'Members.birth_month', 'Members.birth_year'])
                ->where(['Members.id' => $memberId])
                ->first();
        }

        if ($member && $member->age !== null && $member->age < 18) {
            $entity->set('share_with_kingdom', false);
        }
    }

    /**
     * Finder for public attendances
     *
     * @param \Cake\ORM\Query\SelectQuery $query The query to modify
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findPublic($query)
    {
        return $query->where(['is_public' => true]);
    }

    /**
     * Finder for attendances shared with kingdom
     *
     * @param \Cake\ORM\Query\SelectQuery $query The query to modify
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findSharedWithKingdom($query)
    {
        return $query->where(['share_with_kingdom' => true]);
    }

    /**
     * Finder for attendances shared with hosting group
     *
     * @param \Cake\ORM\Query\SelectQuery $query The query to modify
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findSharedWithHostingGroup($query)
    {
        return $query->where(['share_with_hosting_group' => true]);
    }

    /**
     * Finder for attendances shared with crown
     *
     * @param \Cake\ORM\Query\SelectQuery $query The query to modify
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findSharedWithCrown($query)
    {
        return $query->where(['share_with_crown' => true]);
    }

    /**
     * Finder for any shared attendances
     *
     * @param \Cake\ORM\Query\SelectQuery $query The query to modify
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findShared($query)
    {
        return $query->where([
            'OR' => [
                'share_with_kingdom' => true,
                'share_with_hosting_group' => true,
                'share_with_crown' => true,
            ]
        ]);
    }

    /**
     * Get attendance count for a gathering
     *
     * @param int $gatheringId The gathering ID
     * @return int
     */
    public function getAttendanceCount(int $gatheringId): int
    {
        return $this->find()
            ->where(['gathering_id' => $gatheringId])
            ->count();
    }

    /**
     * Check if a member is attending a gathering
     *
     * @param int $gatheringId The gathering ID
     * @param int $memberId The member ID
     * @return bool
     */
    public function isAttending(int $gatheringId, int $memberId): bool
    {
        return $this->exists([
            'gathering_id' => $gatheringId,
            'member_id' => $memberId,
        ]);
    }
}
