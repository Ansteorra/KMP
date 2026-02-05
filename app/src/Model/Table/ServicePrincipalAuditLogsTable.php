<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * ServicePrincipalAuditLogs Table - API Request Audit Trail
 *
 * Records all API requests for compliance and debugging.
 *
 * @property \App\Model\Table\ServicePrincipalsTable&\Cake\ORM\Association\BelongsTo $ServicePrincipals
 * @property \App\Model\Table\ServicePrincipalTokensTable&\Cake\ORM\Association\BelongsTo $ServicePrincipalTokens
 *
 * @method \App\Model\Entity\ServicePrincipalAuditLog newEmptyEntity()
 * @method \App\Model\Entity\ServicePrincipalAuditLog newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\ServicePrincipalAuditLog get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\ServicePrincipalAuditLog findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\ServicePrincipalAuditLog patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\ServicePrincipalAuditLog|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\ServicePrincipalAuditLog saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class ServicePrincipalAuditLogsTable extends BaseTable
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

        $this->setTable('service_principal_audit_logs');
        $this->setDisplayField('action');
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

        $this->belongsTo('ServicePrincipalTokens', [
            'className' => 'ServicePrincipalTokens',
            'foreignKey' => 'token_id',
            'joinType' => 'LEFT',
            'propertyName' => 'token',
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
            ->scalar('action')
            ->maxLength('action', 50)
            ->requirePresence('action', 'create')
            ->notEmptyString('action');

        $validator
            ->scalar('endpoint')
            ->maxLength('endpoint', 512)
            ->requirePresence('endpoint', 'create')
            ->notEmptyString('endpoint');

        $validator
            ->scalar('http_method')
            ->maxLength('http_method', 10)
            ->requirePresence('http_method', 'create')
            ->notEmptyString('http_method');

        $validator
            ->scalar('ip_address')
            ->maxLength('ip_address', 45)
            ->allowEmptyString('ip_address');

        $validator
            ->scalar('request_summary')
            ->allowEmptyString('request_summary');

        $validator
            ->integer('response_code')
            ->allowEmptyString('response_code');

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

        return $rules;
    }

    /**
     * Find logs for a specific service principal.
     *
     * @param \Cake\ORM\Query\SelectQuery $query Query
     * @param int $servicePrincipalId Service principal ID
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findForServicePrincipal($query, int $servicePrincipalId)
    {
        return $query
            ->where(['ServicePrincipalAuditLogs.service_principal_id' => $servicePrincipalId])
            ->orderBy(['ServicePrincipalAuditLogs.created' => 'DESC']);
    }

    /**
     * Find recent logs (last N entries).
     *
     * @param \Cake\ORM\Query\SelectQuery $query Query
     * @param int $limit Number of entries
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findRecent($query, int $limit = 100)
    {
        return $query
            ->orderBy(['ServicePrincipalAuditLogs.created' => 'DESC'])
            ->limit($limit);
    }

    /**
     * Log an API request.
     *
     * @param int $servicePrincipalId Service principal ID
     * @param int|null $tokenId Token ID
     * @param string $action Action description
     * @param string $endpoint Endpoint path
     * @param string $httpMethod HTTP method
     * @param string|null $ipAddress Client IP
     * @param string|null $requestSummary Request summary
     * @param int|null $responseCode Response code
     * @return \App\Model\Entity\ServicePrincipalAuditLog|false
     */
    public function logRequest(
        int $servicePrincipalId,
        ?int $tokenId,
        string $action,
        string $endpoint,
        string $httpMethod,
        ?string $ipAddress = null,
        ?string $requestSummary = null,
        ?int $responseCode = null
    ) {
        $entity = $this->newEntity([
            'service_principal_id' => $servicePrincipalId,
            'token_id' => $tokenId,
            'action' => $action,
            'endpoint' => $endpoint,
            'http_method' => $httpMethod,
            'ip_address' => $ipAddress,
            'request_summary' => $requestSummary,
            'response_code' => $responseCode,
        ]);

        return $this->save($entity);
    }
}
