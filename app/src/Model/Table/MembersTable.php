<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Members Model
 *
 * @property \App\Model\Table\MemberAuthorizationTypesTable&\Cake\ORM\Association\HasMany $MemberAuthorizationTypes
 * @property \App\Model\Table\PendingAuthorizationsTable&\Cake\ORM\Association\HasMany $PendingAuthorizations
 * @property \App\Model\Table\RolesTable&\Cake\ORM\Association\BelongsToMany $Roles
 *
 * @method \App\Model\Entity\Member newEmptyEntity()
 * @method \App\Model\Entity\Member newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\Member> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Member get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Member findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\Member patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\Member> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Member|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Member saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\Member>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Member>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Member>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Member> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Member>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Member>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Member>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Member> deleteManyOrFail(iterable $entities, array $options = [])
 */
class MembersTable extends Table
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

        $this->setTable('members');
        $this->setDisplayField('sca_name');
        $this->setPrimaryKey('id');

        $this->hasMany('MemberAuthorizationTypes', [
            'foreignKey' => 'member_id',
        ]);
        $this->hasMany('PendingAuthorizations', [
            'foreignKey' => 'member_id',
        ]);
        $this->hasMany('PendingAuthorizationsToApprove', [
            'className'=> 'PendingAuthorizations',
            'foreignKey' => 'member_authorizer_id'
        ]);


        $this->belongsToMany('Roles', [
            'through' => 'MemberRoles',
        ]);

        $this->belongsTo('Branches', [
            'foreignKey' => 'branch_id',
        ]);

        $this->addBehavior('Muffin/Trash.Trash');
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
            ->dateTime('last_updated')
            ->notEmptyDateTime('last_updated');

        $validator
            ->scalar('password')
            ->maxLength('password', 33)
            ->requirePresence('password', 'create')
            ->notEmptyString('password');

        $validator
            ->scalar('sca_name')
            ->maxLength('sca_name', 50)
            ->allowEmptyString('sca_name');

        $validator
            ->scalar('first_name')
            ->maxLength('first_name', 30)
            ->requirePresence('first_name', 'create')
            ->notEmptyString('first_name');

        $validator
            ->scalar('middle_name')
            ->maxLength('middle_name', 30)
            ->allowEmptyString('middle_name');

        $validator
            ->scalar('last_name')
            ->maxLength('last_name', 30)
            ->requirePresence('last_name', 'create')
            ->notEmptyString('last_name');

        $validator
            ->scalar('street_address')
            ->maxLength('street_address', 75)
            ->requirePresence('street_address', 'create')
            ->notEmptyString('street_address');

        $validator
            ->scalar('city')
            ->maxLength('city', 30)
            ->requirePresence('city', 'create')
            ->notEmptyString('city');

        $validator
            ->scalar('state')
            ->maxLength('state', 2)
            ->requirePresence('state', 'create')
            ->notEmptyString('state');

        $validator
            ->scalar('zip')
            ->maxLength('zip', 5)
            ->requirePresence('zip', 'create')
            ->notEmptyString('zip');

        $validator
            ->scalar('phone_number')
            ->maxLength('phone_number', 15)
            ->requirePresence('phone_number', 'create')
            ->notEmptyString('phone_number');

        $validator
            ->scalar('email_address')
            ->maxLength('email_address', 50)
            ->requirePresence('email_address', 'create')
            ->notEmptyString('email_address');

        $validator
            ->nonNegativeInteger('membership_number')
            ->allowEmptyString('membership_number');

        $validator
            ->date('membership_expires_on')
            ->allowEmptyDate('membership_expires_on');

        $validator
            ->scalar('branch_name')
            ->maxLength('branch_name', 40)
            ->allowEmptyString('branch_name');

        $validator
            ->scalar('notes')
            ->allowEmptyString('notes');

        $validator
            ->scalar('parent_name')
            ->maxLength('parent_name', 50)
            ->allowEmptyString('parent_name');

        $validator
            ->date('background_check_expires_on')
            ->allowEmptyDate('background_check_expires_on');

        $validator
            ->boolean('hidden')
            ->requirePresence('hidden', 'create')
            ->notEmptyString('hidden');

        $validator
            ->scalar('password_token')
            ->maxLength('password_token', 255)
            ->allowEmptyString('password_token');

        $validator
            ->dateTime('password_token_expires_on')
            ->allowEmptyDateTime('password_token_expires_on');

        $validator
            ->dateTime('last_login')
            ->allowEmptyDateTime('last_login');

        $validator
            ->dateTime('last_failed_login')
            ->allowEmptyDateTime('last_failed_login');

        $validator
            ->integer('failed_login_attempts')
            ->allowEmptyString('failed_login_attempts');

        $validator
            ->integer('birth_month')
            ->allowEmptyString('birth_month');

        $validator
            ->integer('birth_year')
            ->allowEmptyString('birth_year');

        $validator
            ->dateTime('deleted_date')
            ->allowEmptyDateTime('deleted_date');

        return $validator;
    }
}
