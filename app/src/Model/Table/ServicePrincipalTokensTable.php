<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * ServicePrincipalTokens Table - API Token Management
 *
 * Manages authentication tokens for service principals.
 *
 * @property \App\Model\Table\ServicePrincipalsTable&\Cake\ORM\Association\BelongsTo $ServicePrincipals
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $CreatedByMembers
 *
 * @method \App\Model\Entity\ServicePrincipalToken newEmptyEntity()
 * @method \App\Model\Entity\ServicePrincipalToken newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\ServicePrincipalToken get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\ServicePrincipalToken findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\ServicePrincipalToken patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\ServicePrincipalToken|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\ServicePrincipalToken saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class ServicePrincipalTokensTable extends BaseTable
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

        $this->setTable('service_principal_tokens');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created' => 'new',
                ],
            ],
        ]);

        $this->belongsTo('ServicePrincipals', [
            'className' => 'ServicePrincipals',
            'foreignKey' => 'service_principal_id',
            'joinType' => 'INNER',
        ]);

        $this->belongsTo('CreatedByMembers', [
            'className' => 'Members',
            'foreignKey' => 'created_by',
            'joinType' => 'LEFT',
            'propertyName' => 'created_by_member',
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
            ->integer('service_principal_id')
            ->requirePresence('service_principal_id', 'create')
            ->notEmptyString('service_principal_id');

        $validator
            ->scalar('token_hash')
            ->maxLength('token_hash', 255)
            ->requirePresence('token_hash', 'create')
            ->notEmptyString('token_hash');

        $validator
            ->scalar('name')
            ->maxLength('name', 100)
            ->allowEmptyString('name');

        $validator
            ->dateTime('expires_at')
            ->allowEmptyDateTime('expires_at');

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
        $rules->add($rules->existsIn(['service_principal_id'], 'ServicePrincipals'), [
            'errorField' => 'service_principal_id',
            'message' => 'Invalid service principal.',
        ]);

        $rules->add($rules->isUnique(['token_hash']), [
            'errorField' => 'token_hash',
            'message' => 'Token hash must be unique.',
        ]);

        return $rules;
    }

    /**
     * Find active (non-expired) tokens.
     *
     * @param \Cake\ORM\Query\SelectQuery $query Query
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findActive($query)
    {
        $now = date('Y-m-d H:i:s');

        return $query->where([
            'OR' => [
                'ServicePrincipalTokens.expires_at IS' => null,
                'ServicePrincipalTokens.expires_at >' => $now,
            ],
        ]);
    }

    /**
     * Find token by hash.
     *
     * @param \Cake\ORM\Query\SelectQuery $query Query
     * @param string $tokenHash Token hash
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findByHash($query, string $tokenHash)
    {
        return $query->where(['ServicePrincipalTokens.token_hash' => $tokenHash]);
    }

    /**
     * Update last used timestamp for a token.
     *
     * @param int $tokenId Token ID
     * @return void
     */
    public function updateLastUsed(int $tokenId): void
    {
        $this->updateAll(
            ['last_used_at' => date('Y-m-d H:i:s')],
            ['id' => $tokenId]
        );
    }
}
