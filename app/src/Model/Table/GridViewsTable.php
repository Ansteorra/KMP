<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * GridViews Table - Manage Saved Grid View Configurations
 *
 * The GridViewsTable provides data access and business logic for managing saved grid
 * view configurations. This enables a Dataverse-style grid system where users can
 * create, save, and manage custom views of data grids throughout the application.
 *
 * ## Core Responsibilities
 *
 * ### View Management
 * - **CRUD Operations**: Create, read, update, delete grid views
 * - **Default Management**: Set and unset default views per user/grid
 * - **System Defaults**: Manage application-wide default views
 * - **Ownership**: Enforce member ownership and access control
 *
 * ### View Resolution
 * - **Priority Resolution**: Explicit → User Default → System Default → Fallback
 * - **Context-Aware Loading**: Load applicable views for a member + grid combination
 * - **Efficient Queries**: Optimized lookups with proper indexing
 *
 * ### Data Integrity
 * - **Unique Constraints**: Enforce one system default per grid
 * - **User Default Limits**: One default per user per grid
 * - **JSON Validation**: Ensure config field contains valid JSON
 * - **Foreign Key Integrity**: Maintain referential integrity with members table
 *
 * ## Association Patterns
 *
 * ### Member Relationships
 * ```php
 * // Load view with owner
 * $view = $this->GridViews->get($id, [
 *     'contain' => ['Members']
 * ]);
 *
 * // Find all views for a member
 * $views = $this->GridViews->find()
 *     ->where(['member_id' => $memberId])
 *     ->all();
 * ```
 *
 * ### Audit Relationships
 * ```php
 * // Load with creator and modifier
 * $view = $this->GridViews->get($id, [
 *     'contain' => ['Creators', 'Modifiers']
 * ]);
 * ```
 *
 * ## Custom Finders
 *
 * ### findByGrid
 * Find all views (system + user) for a specific grid
 *
 * ### findSystemDefault
 * Find the system default view for a grid
 *
 * ### findUserDefault
 * Find a specific user's default view for a grid
 *
 * ### findForMember
 * Find all views available to a member (system defaults + their own)
 *
 * ## Usage Examples
 *
 * ### Loading Views for a Grid
 * ```php
 * $views = $this->GridViews->findByGrid('Members.index.main', $member)
 *     ->all();
 * ```
 *
 * ### Setting a User Default
 * ```php
 * $this->GridViews->setUserDefault($viewId, $memberId, 'Members.index.main');
 * ```
 *
 * ### Getting Effective View
 * ```php
 * $view = $this->GridViews->getEffectiveView(
 *     'Members.index.main',
 *     $member,
 *     $requestedViewId
 * );
 * ```
 *
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $Members
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $Creators
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $Modifiers
 *
 * @method \App\Model\Entity\GridView newEmptyEntity()
 * @method \App\Model\Entity\GridView newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\GridView> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\GridView get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\GridView findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\GridView patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\GridView> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\GridView|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\GridView saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class GridViewsTable extends BaseTable
{
    /**
     * Initialize method - Configure table relationships and behaviors
     *
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('grid_views');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        // Add behaviors for timestamps, auditing, and soft delete
        $this->addBehavior('Timestamp');
        $this->addBehavior('Muffin/Footprint.Footprint', [
            'events' => [
                'Model.beforeSave' => [
                    'created_by' => 'new',
                    'modified_by' => 'always',
                ],
            ],
        ]);
        $this->addBehavior('Muffin/Trash.Trash', [
            'field' => 'deleted',
        ]);

        // Association to view owner
        $this->belongsTo('Members', [
            'foreignKey' => 'member_id',
            'joinType' => 'LEFT',
            'propertyName' => 'member',
        ]);

        // Association to creator
        $this->belongsTo('Creators', [
            'className' => 'Members',
            'foreignKey' => 'created_by',
            'joinType' => 'LEFT',
        ]);

        // Association to modifier
        $this->belongsTo('Modifiers', [
            'className' => 'Members',
            'foreignKey' => 'modified_by',
            'joinType' => 'LEFT',
        ]);
    }

    /**
     * Default validation rules
     *
     * @param \Cake\Validation\Validator $validator Validator instance
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->scalar('grid_key')
            ->maxLength('grid_key', 100)
            ->requirePresence('grid_key', 'create')
            ->notEmptyString('grid_key');

        $validator
            ->integer('member_id')
            ->allowEmptyString('member_id');

        $validator
            ->scalar('name')
            ->maxLength('name', 100)
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        $validator
            ->boolean('is_default')
            ->notEmptyString('is_default');

        $validator
            ->boolean('is_system_default')
            ->notEmptyString('is_system_default');

        $validator
            ->scalar('config')
            ->requirePresence('config', 'create')
            ->notEmptyString('config')
            ->add('config', 'validJson', [
                'rule' => function ($value, $context) {
                    json_decode($value);
                    return json_last_error() === JSON_ERROR_NONE;
                },
                'message' => 'Config must be valid JSON',
            ]);

        return $validator;
    }

    /**
     * Build rules for maintaining data integrity
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to configure
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        // System defaults must have member_id = NULL
        $rules->add(
            function ($entity, $options) {
                if ($entity->is_system_default && $entity->member_id !== null) {
                    return 'System defaults must not have a member_id';
                }
                return true;
            },
            'systemDefaultNoMember',
            [
                'errorField' => 'is_system_default',
                'message' => 'System defaults must not have a member_id',
            ]
        );

        // Only one system default per grid_key
        $rules->add(
            function ($entity, $options) {
                if (!$entity->is_system_default) {
                    return true;
                }

                $existing = $this->find()
                    ->where([
                        'grid_key' => $entity->grid_key,
                        'is_system_default' => true,
                        'id !=' => $entity->id ?? 0,
                    ])
                    ->count();

                return $existing === 0;
            },
            'uniqueSystemDefault',
            [
                'errorField' => 'is_system_default',
                'message' => 'Only one system default allowed per grid',
            ]
        );

        // Only one user default per (member_id, grid_key)
        $rules->add(
            function ($entity, $options) {
                if (!$entity->is_default || $entity->member_id === null) {
                    return true;
                }

                $existing = $this->find()
                    ->where([
                        'grid_key' => $entity->grid_key,
                        'member_id' => $entity->member_id,
                        'is_default' => true,
                        'id !=' => $entity->id ?? 0,
                    ])
                    ->count();

                return $existing === 0;
            },
            'uniqueUserDefault',
            [
                'errorField' => 'is_default',
                'message' => 'Only one default view allowed per user per grid',
            ]
        );

        // Foreign key checks
        $rules->add($rules->existsIn(['member_id'], 'Members'), [
            'errorField' => 'member_id',
            'message' => 'Member does not exist',
        ]);

        return $rules;
    }

    /**
     * Find views by grid key
     *
     * Returns system default and user-specific views for a grid.
     *
     * @param \Cake\ORM\Query\SelectQuery $query Query object
     * @param array<string, mixed> $options Options including 'gridKey' and optionally 'memberId'
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findByGrid($query, array $options)
    {
        $gridKey = $options['gridKey'] ?? null;
        $memberId = $options['memberId'] ?? null;

        if (!$gridKey) {
            return $query->where(['1 = 0']); // Return empty result
        }

        $query->where(['GridViews.grid_key' => $gridKey]);

        // Get system defaults and user's own views
        if ($memberId) {
            $query->where([
                'OR' => [
                    ['GridViews.member_id IS' => null, 'GridViews.is_system_default' => true],
                    ['GridViews.member_id' => $memberId],
                ],
            ]);
        } else {
            // Only system defaults if no member specified
            $query->where([
                'GridViews.member_id IS' => null,
                'GridViews.is_system_default' => true,
            ]);
        }

        return $query->orderBy(['GridViews.is_system_default' => 'DESC', 'GridViews.name' => 'ASC']);
    }

    /**
     * Find system default view for a grid
     *
     * @param \Cake\ORM\Query\SelectQuery $query Query object
     * @param array<string, mixed> $options Options including 'gridKey'
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findSystemDefault($query, array $options)
    {
        $gridKey = $options['gridKey'] ?? null;

        if (!$gridKey) {
            return $query->where(['1 = 0']);
        }

        return $query->where([
            'GridViews.grid_key' => $gridKey,
            'GridViews.is_system_default' => true,
            'GridViews.member_id IS' => null,
        ]);
    }

    /**
     * Find user's default view for a grid
     *
     * @param \Cake\ORM\Query\SelectQuery $query Query object
     * @param array<string, mixed> $options Options including 'gridKey' and 'memberId'
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findUserDefault($query, array $options)
    {
        $gridKey = $options['gridKey'] ?? null;
        $memberId = $options['memberId'] ?? null;

        if (!$gridKey || !$memberId) {
            return $query->where(['1 = 0']);
        }

        return $query->where([
            'GridViews.grid_key' => $gridKey,
            'GridViews.member_id' => $memberId,
            'GridViews.is_default' => true,
        ]);
    }
}
