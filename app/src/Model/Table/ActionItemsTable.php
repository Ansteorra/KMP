<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\ActionItem;
use App\Services\ActionItems\ActionItemService;
use ArrayObject;
use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;

/**
 * ActionItems Table - reusable, polymorphic "to-do" / check records.
 *
 * @property \App\Model\Table\ActionItemLogsTable&\Cake\ORM\Association\HasMany $ActionItemLogs
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $CompletedByMembers
 * @property \App\Model\Table\BranchesTable&\Cake\ORM\Association\BelongsTo $Branches
 * @method \App\Model\Entity\ActionItem newEmptyEntity()
 * @method \App\Model\Entity\ActionItem newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\ActionItem get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\ActionItem patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\ActionItem saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class ActionItemsTable extends BaseTable
{
    /**
     * Initialize table associations and behaviors.
     *
     * @param array<string, mixed> $config Table configuration
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('action_items');
        $this->setDisplayField('title');
        $this->setPrimaryKey('id');

        $this->hasMany('ActionItemLogs', [
            'className' => 'ActionItemLogs',
            'foreignKey' => 'action_item_id',
            'dependent' => true,
            'cascadeCallbacks' => true,
            'sort' => ['ActionItemLogs.created' => 'ASC'],
        ]);
        $this->belongsTo('CompletedByMembers', [
            'className' => 'Members',
            'foreignKey' => 'completed_by',
            'joinType' => 'LEFT',
        ]);
        $this->belongsTo('Branches', [
            'className' => 'Branches',
            'foreignKey' => 'branch_id',
            'joinType' => 'LEFT',
        ]);

        $this->addBehavior('Timestamp');
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->addBehavior('Muffin/Trash.Trash');

        // MariaDB stores JSON as longtext; explicitly map JSON columns.
        $this->getSchema()->setColumnType('assignee_config', 'json');
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('entity_type')
            ->maxLength('entity_type', 100)
            ->requirePresence('entity_type', 'create')
            ->notEmptyString('entity_type');

        $validator
            ->integer('entity_id')
            ->requirePresence('entity_id', 'create')
            ->notEmptyString('entity_id');

        $validator
            ->scalar('title')
            ->maxLength('title', 255)
            ->requirePresence('title', 'create')
            ->notEmptyString('title');

        $validator
            ->scalar('description')
            ->allowEmptyString('description');

        $validator
            ->scalar('assignee_type')
            ->maxLength('assignee_type', 30)
            ->requirePresence('assignee_type', 'create')
            ->notEmptyString('assignee_type');

        $validator
            ->scalar('status')
            ->maxLength('status', 20)
            ->notEmptyString('status');

        $validator
            ->boolean('is_gating')
            ->notEmptyString('is_gating');

        $validator
            ->integer('sort_order')
            ->allowEmptyString('sort_order');

        $validator
            ->integer('branch_id')
            ->allowEmptyString('branch_id');

        return $validator;
    }

    /**
     * Scope a query to open items only.
     *
     * @param \Cake\ORM\Query\SelectQuery $query The query to scope
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findOpen(SelectQuery $query): SelectQuery
    {
        return $query->where([$this->aliasField('status') => ActionItem::STATUS_OPEN]);
    }

    /**
     * Scope a query to a polymorphic owner.
     *
     * @param \Cake\ORM\Query\SelectQuery $query The query to scope
     * @param string $entityType Owner type, e.g. Awards.Bestowals
     * @param int $entityId Owner primary key
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findForEntity(SelectQuery $query, string $entityType, int $entityId): SelectQuery
    {
        return $query->where([
            $this->aliasField('entity_type') => $entityType,
            $this->aliasField('entity_id') => $entityId,
        ]);
    }

    /**
     * Populate denormalized assignee lookup fields from the assignment snapshot.
     *
     * @param \Cake\Event\Event $event The beforeSave event.
     * @param \Cake\Datasource\EntityInterface $entity Action item entity.
     * @param \ArrayObject $options Save options.
     * @return void
     */
    public function beforeSave(Event $event, EntityInterface $entity, ArrayObject $options): void
    {
        $config = $entity->assignee_config ?? [];
        if (!is_array($config)) {
            $config = [];
        }

        $lookup = $this->assigneeLookupFromConfig((string)$entity->assignee_type, $config, $entity->branch_id);
        foreach ($lookup as $field => $value) {
            $entity->set($field, $value);
        }
    }

    /**
     * @param string $assigneeType Assignee type.
     * @param array<string, mixed> $config Assignee config snapshot.
     * @param mixed $branchId Branch scope from the action item.
     * @return array<string, mixed>
     */
    private function assigneeLookupFromConfig(string $assigneeType, array $config, mixed $branchId): array
    {
        $lookup = [
            'assignee_lookup_type' => null,
            'assignee_lookup_id' => null,
            'assignee_lookup_name' => null,
            'assignee_lookup_branch_id' => self::positiveIntOrNull($branchId),
        ];

        switch ($assigneeType) {
            case ActionItem::ASSIGNEE_TYPE_MEMBER:
                $lookup['assignee_lookup_type'] = ActionItem::ASSIGNEE_TYPE_MEMBER;
                $lookup['assignee_lookup_id'] = self::positiveIntOrNull($config['member_id'] ?? null);
                break;
            case ActionItem::ASSIGNEE_TYPE_ROLE:
                [$roleId, $roleName] = $this->resolveRoleLookup(
                    self::positiveIntOrNull($config['role_id'] ?? null),
                    self::stringOrNull($config['role'] ?? null),
                );
                $lookup['assignee_lookup_type'] = ActionItem::ASSIGNEE_TYPE_ROLE;
                $lookup['assignee_lookup_id'] = $roleId;
                $lookup['assignee_lookup_name'] = $roleName;
                break;
            case ActionItem::ASSIGNEE_TYPE_PERMISSION:
            case ActionItem::ASSIGNEE_TYPE_POLICY:
                [$permissionId, $permissionName] = $this->resolvePermissionLookup(
                    self::positiveIntOrNull($config['permission_id'] ?? null),
                    self::stringOrNull($config['permission'] ?? null),
                );
                $lookup['assignee_lookup_type'] = ActionItem::ASSIGNEE_TYPE_PERMISSION;
                $lookup['assignee_lookup_id'] = $permissionId;
                $lookup['assignee_lookup_name'] = $permissionName;
                break;
            case ActionItem::ASSIGNEE_TYPE_DYNAMIC:
                $lookup['assignee_lookup_type'] = self::stringOrNull($config['kind'] ?? null)
                    ?? ActionItem::ASSIGNEE_TYPE_DYNAMIC;
                $lookup['assignee_lookup_id'] = self::positiveIntOrNull($config['source_id'] ?? null);
                $lookup['assignee_lookup_name'] = self::stringOrNull($config['source_key'] ?? null);
                break;
        }

        return $lookup;
    }

    /**
     * @param int|null $roleId Configured role ID.
     * @param string|null $roleName Configured role name.
     * @return array{0:int|null,1:string|null}
     */
    private function resolveRoleLookup(?int $roleId, ?string $roleName): array
    {
        $roles = TableRegistry::getTableLocator()->get('Roles');
        if ($roleId !== null) {
            $role = $roles->find()
                ->select(['id', 'name'])
                ->where(['Roles.id' => $roleId])
                ->first();

            return [$roleId, $role?->name ?? $roleName];
        }
        if ($roleName !== null) {
            $role = $roles->find()
                ->select(['id', 'name'])
                ->where(['Roles.name' => $roleName])
                ->first();

            return [$role?->id === null ? null : (int)$role->id, $role?->name ?? $roleName];
        }

        return [null, null];
    }

    /**
     * @param int|null $permissionId Configured permission ID.
     * @param string|null $permissionName Configured permission name.
     * @return array{0:int|null,1:string|null}
     */
    private function resolvePermissionLookup(?int $permissionId, ?string $permissionName): array
    {
        $permissions = TableRegistry::getTableLocator()->get('Permissions');
        if ($permissionId !== null) {
            $permission = $permissions->find()
                ->select(['id', 'name'])
                ->where(['Permissions.id' => $permissionId])
                ->first();

            return [$permissionId, $permission?->name ?? $permissionName];
        }
        if ($permissionName !== null) {
            $permission = $permissions->find()
                ->select(['id', 'name'])
                ->where(['Permissions.name' => $permissionName])
                ->first();

            return [$permission?->id === null ? null : (int)$permission->id, $permission?->name ?? $permissionName];
        }

        return [null, null];
    }

    /**
     * @param mixed $value Raw value.
     * @return int|null
     */
    private static function positiveIntOrNull(mixed $value): ?int
    {
        if (is_string($value)) {
            $value = trim($value);
        }
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            $value = (int)$value;

            return $value > 0 ? $value : null;
        }

        return null;
    }

    /**
     * @param mixed $value Raw value.
     * @return string|null
     */
    private static function stringOrNull(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string)$value);

        return $value === '' ? null : $value;
    }

    /**
     * Count open to-do items the given member is eligible to act on.
     *
     * Powers the "My To-Dos" navigation badge using the same eligibility
     * resolution that populates the My To-Dos queue.
     *
     * @param int $memberId The member id
     * @return int
     */
    public static function getOpenTaskCountForMember(int $memberId): int
    {
        if ($memberId <= 0) {
            return 0;
        }

        return (new ActionItemService())->countOpenItemsForMember($memberId);
    }
}
