<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use App\Model\Entity\RolesPermission;
use Cake\Event\Event;
use Cake\Datasource\EntityInterface;
use ArrayObject;
use Cake\Http\Session;
use Cake\Cache\Cache;
use App\Model\Table\BaseTable;

/**
 * RolesPermissions Model
 *
 * @property \App\Model\Table\PermissionsTable&\Cake\ORM\Association\BelongsTo $Permissions
 * @property \App\Model\Table\RolesTable&\Cake\ORM\Association\BelongsTo $Roles
 *
 * @method \App\Model\Entity\RolesPermission newEmptyEntity()
 * @method \App\Model\Entity\RolesPermission newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\RolesPermission> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\RolesPermission get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\RolesPermission findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\RolesPermission patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\RolesPermission> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\RolesPermission|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\RolesPermission saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\RolesPermission>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\RolesPermission>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\RolesPermission>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\RolesPermission> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\RolesPermission>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\RolesPermission>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\RolesPermission>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\RolesPermission> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class RolesPermissionsTable extends BaseTable
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

        $this->setTable('roles_permissions');
        $this->setDisplayField(['permission_id', 'role_id']);
        $this->setPrimaryKey(['permission_id', 'role_id']);

        $this->addBehavior('Timestamp');

        $this->belongsTo('Permissions', [
            'foreignKey' => 'permission_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('Roles', [
            'foreignKey' => 'role_id',
            'joinType' => 'INNER',
        ]);
    }



    protected const CACHE_GROUPS_TO_CLEAR = ['security'];

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('permission_id')
            ->notEmptyString('permission_id');

        $validator
            ->integer('role_id')
            ->notEmptyString('role_id');

        $validator
            ->integer('created_by')
            ->requirePresence('created_by', 'create')
            ->notEmptyString('created_by');

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
        $rules->add($rules->existsIn(['permission_id'], 'Permissions'), ['errorField' => 'permission_id']);
        $rules->add($rules->existsIn(['role_id'], 'Roles'), ['errorField' => 'role_id']);

        return $rules;
    }

    public function beforeSave(Event $event, EntityInterface $entity, ArrayObject $options)
    {
        if ($entity->isNew() && empty($entity->created_by)) {
            $user = (new Session)->read('Auth');
            $entity->created_by = $user['id'];
        }
    }
}