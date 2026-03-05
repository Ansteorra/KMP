<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * MemberQuickLoginDevices Table
 *
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $Members
 *
 * @method \App\Model\Entity\MemberQuickLoginDevice newEmptyEntity()
 * @method \App\Model\Entity\MemberQuickLoginDevice newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\MemberQuickLoginDevice get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\MemberQuickLoginDevice|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\MemberQuickLoginDevice saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class MemberQuickLoginDevicesTable extends BaseTable
{
    /**
     * @param array<string, mixed> $config Configuration.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('member_quick_login_devices');
        $this->setDisplayField('device_id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Members', [
            'foreignKey' => 'member_id',
            'joinType' => 'INNER',
        ]);
    }

    /**
     * @param \Cake\Validation\Validator $validator Validator.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('member_id')
            ->requirePresence('member_id', 'create')
            ->notEmptyString('member_id');

        $validator
            ->scalar('device_id')
            ->maxLength('device_id', 128)
            ->requirePresence('device_id', 'create')
            ->notEmptyString('device_id');

        $validator
            ->scalar('pin_hash')
            ->maxLength('pin_hash', 255)
            ->requirePresence('pin_hash', 'create')
            ->notEmptyString('pin_hash');

        $validator
            ->scalar('configured_ip_address')
            ->maxLength('configured_ip_address', 45)
            ->allowEmptyString('configured_ip_address');

        $validator
            ->scalar('configured_location_hint')
            ->maxLength('configured_location_hint', 120)
            ->allowEmptyString('configured_location_hint');

        $validator
            ->scalar('configured_os')
            ->maxLength('configured_os', 120)
            ->allowEmptyString('configured_os');

        $validator
            ->scalar('configured_browser')
            ->maxLength('configured_browser', 120)
            ->allowEmptyString('configured_browser');

        $validator
            ->scalar('configured_user_agent')
            ->maxLength('configured_user_agent', 512)
            ->allowEmptyString('configured_user_agent');

        $validator
            ->integer('failed_attempts')
            ->greaterThanOrEqual('failed_attempts', 0)
            ->allowEmptyString('failed_attempts');

        $validator
            ->dateTime('last_failed_login')
            ->allowEmptyDateTime('last_failed_login');

        $validator
            ->dateTime('last_used')
            ->allowEmptyDateTime('last_used');

        $validator
            ->scalar('last_used_ip_address')
            ->maxLength('last_used_ip_address', 45)
            ->allowEmptyString('last_used_ip_address');

        $validator
            ->scalar('last_used_location_hint')
            ->maxLength('last_used_location_hint', 120)
            ->allowEmptyString('last_used_location_hint');

        return $validator;
    }

    /**
     * @param \Cake\ORM\RulesChecker $rules Rules checker.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['member_id'], 'Members'), [
            'errorField' => 'member_id',
            'message' => 'Invalid member.',
        ]);

        $rules->add($rules->isUnique(['device_id']), [
            'errorField' => 'device_id',
            'message' => 'Quick login is already configured for this device.',
        ]);

        return $rules;
    }
}
