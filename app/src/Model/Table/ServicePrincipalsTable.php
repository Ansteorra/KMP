<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * ServicePrincipals Table - API Client Management
 *
 * Manages service principal entities for third-party API integrations.
 *
 * @property \App\Model\Table\ServicePrincipalRolesTable&\Cake\ORM\Association\HasMany $ServicePrincipalRoles
 * @property \App\Model\Table\ServicePrincipalTokensTable&\Cake\ORM\Association\HasMany $ServicePrincipalTokens
 * @property \App\Model\Table\ServicePrincipalAuditLogsTable&\Cake\ORM\Association\HasMany $ServicePrincipalAuditLogs
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $CreatedByMembers
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $ModifiedByMembers
 *
 * @method \App\Model\Entity\ServicePrincipal newEmptyEntity()
 * @method \App\Model\Entity\ServicePrincipal newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\ServicePrincipal get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\ServicePrincipal findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\ServicePrincipal patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\ServicePrincipal|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\ServicePrincipal saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class ServicePrincipalsTable extends BaseTable
{
    /**
     * Initialize method
     *
     * @param array<string, mixed> $config Configuration
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('service_principals');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
        $this->addBehavior('Muffin/Footprint.Footprint');

        $this->hasMany('ServicePrincipalRoles', [
            'className' => 'ServicePrincipalRoles',
            'foreignKey' => 'service_principal_id',
            'dependent' => true,
            'cascadeCallbacks' => true,
        ]);

        $this->hasMany('ServicePrincipalTokens', [
            'className' => 'ServicePrincipalTokens',
            'foreignKey' => 'service_principal_id',
            'dependent' => true,
            'cascadeCallbacks' => true,
        ]);

        $this->hasMany('ServicePrincipalAuditLogs', [
            'className' => 'ServicePrincipalAuditLogs',
            'foreignKey' => 'service_principal_id',
            'dependent' => true,
            'cascadeCallbacks' => true,
        ]);

        $this->belongsTo('CreatedByMembers', [
            'className' => 'Members',
            'foreignKey' => 'created_by',
            'joinType' => 'LEFT',
            'propertyName' => 'created_by_member',
        ]);

        $this->belongsTo('ModifiedByMembers', [
            'className' => 'Members',
            'foreignKey' => 'modified_by',
            'joinType' => 'LEFT',
            'propertyName' => 'modified_by_member',
        ]);
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name', 'Name is required');

        $validator
            ->scalar('description')
            ->allowEmptyString('description');

        $validator
            ->scalar('client_id')
            ->maxLength('client_id', 64)
            ->requirePresence('client_id', 'create')
            ->notEmptyString('client_id');

        $validator
            ->scalar('client_secret_hash')
            ->maxLength('client_secret_hash', 255)
            ->requirePresence('client_secret_hash', 'create')
            ->notEmptyString('client_secret_hash');

        $validator
            ->boolean('is_active')
            ->notEmptyString('is_active');

        $validator
            ->allowEmptyArray('ip_allowlist');

        $validator
            ->dateTime('last_used_at')
            ->allowEmptyDateTime('last_used_at');

        return $validator;
    }

    /**
     * Returns a rules checker object.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['client_id']), [
            'errorField' => 'client_id',
            'message' => 'Client ID must be unique.',
        ]);

        $rules->add($rules->isUnique(['name']), [
            'errorField' => 'name',
            'message' => 'Service principal name must be unique.',
        ]);

        return $rules;
    }

    /**
     * Find active service principals.
     *
     * @param \Cake\ORM\Query\SelectQuery $query Query
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findActive($query)
    {
        return $query->where(['ServicePrincipals.is_active' => true]);
    }

    /**
     * Find service principal by client ID.
     *
     * @param \Cake\ORM\Query\SelectQuery $query Query
     * @param string $clientId Client ID
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findByClientId($query, string $clientId)
    {
        return $query->where(['ServicePrincipals.client_id' => $clientId]);
    }
}
