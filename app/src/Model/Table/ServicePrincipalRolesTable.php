<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\Cache\Cache;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * ServicePrincipalRoles Table - Role Assignments for Service Principals
 *
 * Mirrors MemberRoles structure for API client RBAC.
 *
 * @property \App\Model\Table\ServicePrincipalsTable&\Cake\ORM\Association\BelongsTo $ServicePrincipals
 * @property \App\Model\Table\RolesTable&\Cake\ORM\Association\BelongsTo $Roles
 * @property \App\Model\Table\BranchesTable&\Cake\ORM\Association\BelongsTo $Branches
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $ApprovedBy
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $RevokedBy
 *
 * @method \App\Model\Entity\ServicePrincipalRole newEmptyEntity()
 * @method \App\Model\Entity\ServicePrincipalRole newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\ServicePrincipalRole get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\ServicePrincipalRole findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\ServicePrincipalRole patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\ServicePrincipalRole|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\ServicePrincipalRole saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class ServicePrincipalRolesTable extends BaseTable
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

        $this->setTable('service_principal_roles');
        $this->setDisplayField(['service_principal_id', 'role_id']);
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->addBehavior('ActiveWindow');

        $this->belongsTo('ServicePrincipals', [
            'className' => 'ServicePrincipals',
            'foreignKey' => 'service_principal_id',
            'joinType' => 'INNER',
        ]);

        $this->belongsTo('Roles', [
            'className' => 'Roles',
            'foreignKey' => 'role_id',
            'joinType' => 'INNER',
        ]);

        $this->belongsTo('Branches', [
            'className' => 'Branches',
            'foreignKey' => 'branch_id',
            'joinType' => 'LEFT',
        ]);

        $this->belongsTo('ApprovedBy', [
            'className' => 'Members',
            'foreignKey' => 'approver_id',
            'joinType' => 'LEFT',
            'propertyName' => 'approved_by',
        ]);

        $this->belongsTo('RevokedBy', [
            'className' => 'Members',
            'foreignKey' => 'revoker_id',
            'joinType' => 'LEFT',
            'propertyName' => 'revoked_by',
        ]);
    }

    /**
     * Clear cached permissions after save.
     *
     * @param \Cake\Event\EventInterface $event Event
     * @param \Cake\Datasource\EntityInterface $entity Entity
     * @param \ArrayObject $options Options
     * @return void
     */
    public function afterSave($event, $entity, $options): void
    {
        parent::afterSave($event, $entity, $options);
        
        $servicePrincipalId = $entity->service_principal_id;
        Cache::delete('sp_permissions_' . $servicePrincipalId);
        Cache::delete('sp_policies_' . $servicePrincipalId);
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
            ->integer('role_id')
            ->requirePresence('role_id', 'create')
            ->notEmptyString('role_id');

        $validator
            ->integer('branch_id')
            ->allowEmptyString('branch_id');

        $validator
            ->date('start_on')
            ->requirePresence('start_on', 'create')
            ->notEmptyDate('start_on');

        $validator
            ->date('expires_on')
            ->allowEmptyDate('expires_on');

        $validator
            ->integer('approver_id')
            ->allowEmptyString('approver_id');

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

        $rules->add($rules->existsIn(['role_id'], 'Roles'), [
            'errorField' => 'role_id',
            'message' => 'Invalid role.',
        ]);

        $rules->add($rules->existsIn(['branch_id'], 'Branches'), [
            'errorField' => 'branch_id',
            'message' => 'Invalid branch.',
        ]);

        return $rules;
    }

    /**
     * Find current (active) role assignments.
     *
     * @param \Cake\ORM\Query\SelectQuery $query Query
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findCurrent($query)
    {
        $now = date('Y-m-d');

        return $query->where([
            'ServicePrincipalRoles.start_on <=' => $now,
            'OR' => [
                'ServicePrincipalRoles.expires_on IS' => null,
                'ServicePrincipalRoles.expires_on >=' => $now,
            ],
            'ServicePrincipalRoles.revoked_on IS' => null,
        ]);
    }
}
