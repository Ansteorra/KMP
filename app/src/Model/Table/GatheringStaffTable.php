<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * GatheringStaff Model
 *
 * Manages staff assignments for gatherings including stewards and other roles.
 *
 * @property \App\Model\Table\GatheringsTable&\Cake\ORM\Association\BelongsTo $Gatherings
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $Members
 *
 * @method \App\Model\Entity\GatheringStaff newEmptyEntity()
 * @method \App\Model\Entity\GatheringStaff newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\GatheringStaff[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\GatheringStaff get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\GatheringStaff findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\GatheringStaff patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\GatheringStaff[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\GatheringStaff|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\GatheringStaff saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class GatheringStaffTable extends Table
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

        $this->setTable('gathering_staff');
        $this->setDisplayField('display_name');
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
            ->requirePresence('gathering_id', 'create')
            ->notEmptyString('gathering_id');

        $validator
            ->integer('member_id')
            ->allowEmptyString('member_id');

        $validator
            ->scalar('sca_name')
            ->maxLength('sca_name', 255)
            ->allowEmptyString('sca_name');

        $validator
            ->scalar('role')
            ->maxLength('role', 100)
            ->requirePresence('role', 'create')
            ->notEmptyString('role');

        $validator
            ->boolean('is_steward')
            ->notEmptyString('is_steward');

        $validator
            ->boolean('show_on_public_page')
            ->notEmptyString('show_on_public_page');

        $validator
            ->email('email')
            ->allowEmptyString('email');

        $validator
            ->scalar('phone')
            ->maxLength('phone', 50)
            ->allowEmptyString('phone');

        $validator
            ->scalar('contact_notes')
            ->allowEmptyString('contact_notes');

        $validator
            ->integer('sort_order')
            ->notEmptyString('sort_order');

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

        // Custom rule: Must have either member_id or sca_name, but not both
        $rules->add(
            function ($entity, $options) {
                $hasMember = !empty($entity->member_id);
                $hasScaName = !empty($entity->sca_name);

                // Exactly one must be set (XOR)
                return $hasMember xor $hasScaName;
            },
            'memberOrScaName',
            [
                'errorField' => 'member_id',
                'message' => 'Must specify either a member or an SCA name, but not both.'
            ]
        );

        // Custom rule: Stewards must have email OR phone
        $rules->add(
            function ($entity, $options) {
                if (!$entity->is_steward) {
                    return true; // Rule only applies to stewards
                }

                return !empty($entity->email) || !empty($entity->phone);
            },
            'stewardContactInfo',
            [
                'errorField' => 'email',
                'message' => 'Stewards must provide either an email address or phone number.'
            ]
        );

        return $rules;
    }

    /**
     * Find stewards for a gathering
     *
     * @param \Cake\ORM\Query\SelectQuery $query Query object
     * @param array $options Options including gathering_id
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findStewards(SelectQuery $query, array $options): SelectQuery
    {
        return $query->where(['GatheringStaff.is_steward' => true])
            ->orderBy(['GatheringStaff.sort_order' => 'ASC']);
    }

    /**
     * Find non-steward staff for a gathering
     *
     * @param \Cake\ORM\Query\SelectQuery $query Query object
     * @param array $options Options including gathering_id
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findOtherStaff(SelectQuery $query, array $options): SelectQuery
    {
        return $query->where(['GatheringStaff.is_steward' => false])
            ->orderBy(['GatheringStaff.sort_order' => 'ASC']);
    }

    /**
     * Before save callback to populate contact info from member for new stewards
     *
     * @param \Cake\Event\EventInterface $event Event object
     * @param \Cake\Datasource\EntityInterface $entity Entity being saved
     * @param \ArrayObject $options Options
     * @return void
     */
    public function beforeSave(\Cake\Event\EventInterface $event, \Cake\Datasource\EntityInterface $entity, \ArrayObject $options): void
    {
        // Stewards must always show on public page
        if ($entity->is_steward) {
            $entity->show_on_public_page = true;
        }

        // If this is a new steward with a member_id, copy contact info from member
        if ($entity->isNew() && $entity->is_steward && !empty($entity->member_id)) {
            // Only copy if not already provided
            if (empty($entity->email) && empty($entity->phone)) {
                $member = $this->Members->get($entity->member_id, [
                    'fields' => ['email_address', 'phone_number']
                ]);

                if (!empty($member->email_address)) {
                    $entity->email = $member->email_address;
                }
                if (!empty($member->phone_number)) {
                    $entity->phone = $member->phone_number;
                }
            }
        }
    }
}
