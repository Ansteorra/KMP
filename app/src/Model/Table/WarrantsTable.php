<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\Cache\Cache;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * Warrants Model
 *
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $Members
 * @property \App\Model\Table\WarrantRostersTable&\Cake\ORM\Association\BelongsTo $WarrantRosters
 * @property \App\Model\Table\MemberRolesTable&\Cake\ORM\Association\BelongsTo $MemberRoles
 * @method \App\Model\Entity\Warrant newEmptyEntity()
 * @method \App\Model\Entity\Warrant newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\Warrant> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Warrant get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Warrant findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\Warrant patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\Warrant> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Warrant|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Warrant saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\Warrant>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Warrant>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Warrant>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Warrant> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Warrant>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Warrant>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Warrant>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Warrant> deleteManyOrFail(iterable $entities, array $options = [])
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class WarrantsTable extends BaseTable
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

        $this->setTable('warrants');
        $this->setDisplayField('status');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Members', [
            'foreignKey' => 'member_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('RevokedBy', [
            'className' => 'Members',
            'foreignKey' => 'revoker_id',
            'joinType' => 'LEFT',
            'propertyName' => 'revoked_by',
        ]);
        $this->belongsTo('WarrantRosters', [
            'foreignKey' => 'warrant_roster_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('MemberRoles', [
            'foreignKey' => 'member_role_id',
        ]);

        $this->belongsTo('CreatedByMember', [
            'className' => 'Members',
            'foreignKey' => 'created_by',
            'joinType' => 'LEFT',
        ]);

        $this->belongsTo('ModfiedByMember', [
            'className' => 'Members',
            'foreignKey' => 'modified_by',
            'joinType' => 'LEFT',
        ]);

        $this->addBehavior('ActiveWindow');
        $this->addBehavior('Timestamp');
        $this->addBehavior('Muffin/Footprint.Footprint');
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
            ->integer('member_id')
            ->notEmptyString('member_id');

        $validator
            ->integer('warrant_roster_id')
            ->notEmptyString('warrant_roster_id');

        $validator
            ->scalar('entity_type')
            ->maxLength('entity_type', 255)
            ->allowEmptyString('entity_type');

        $validator
            ->integer('entity_id')
            ->requirePresence('entity_id', 'create')
            ->notEmptyString('entity_id');

        $validator
            ->integer('member_role_id')
            ->allowEmptyString('member_role_id');

        $validator
            ->dateTime('expires_on')
            ->allowEmptyDateTime('expires_on');

        $validator
            ->dateTime('start_on')
            ->allowEmptyDateTime('start_on');

        $validator
            ->dateTime('approved_date')
            ->allowEmptyDateTime('approved_date');

        $validator
            ->scalar('status')
            ->maxLength('status', 20)
            ->notEmptyString('status');

        $validator
            ->scalar('revoked_reason')
            ->maxLength('revoked_reason', 255)
            ->allowEmptyString('revoked_reason');

        $validator
            ->integer('revoker_id')
            ->allowEmptyString('revoker_id');

        $validator
            ->integer('created_by')
            ->allowEmptyString('created_by');

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
        $rules->add($rules->existsIn(['member_id'], 'Members'), ['errorField' => 'member_id']);
        $rules->add($rules->existsIn(['warrant_roster_id'], 'WarrantRosters'), ['errorField' => 'warrant_roster_id']);
        $rules->add($rules->existsIn(['member_role_id'], 'MemberRoles'), ['errorField' => 'member_role_id']);

        return $rules;
    }

    public function afterSave($event, $entity, $options): void
    {
        $memberId = $entity->member_id;
        // Clear cached descendants and parents for the saved branch.
        Cache::delete('permissions_policies' . $memberId);
        Cache::delete('member_permissions' . $memberId);
    }
}
