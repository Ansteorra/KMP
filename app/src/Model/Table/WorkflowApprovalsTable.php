<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\KMP\PermissionsLoader;
use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowInstance;
use App\Services\ApprovalContext\ApprovalContextRendererRegistry;
use ArrayObject;
use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use Exception;
use Throwable;

/**
 * WorkflowApprovals Model
 *
 * @property \App\Model\Table\WorkflowInstancesTable&\Cake\ORM\Association\BelongsTo $WorkflowInstances
 * @property \App\Model\Table\WorkflowExecutionLogsTable&\Cake\ORM\Association\BelongsTo $WorkflowExecutionLogs
 * @property \App\Model\Table\WorkflowApprovalResponsesTable&\Cake\ORM\Association\HasMany $WorkflowApprovalResponses
 * @property \App\Model\Table\WorkflowApprovalTriageStatesTable&\Cake\ORM\Association\HasMany $WorkflowApprovalTriageStates
 * @method \App\Model\Entity\WorkflowApproval newEmptyEntity()
 * @method \App\Model\Entity\WorkflowApproval newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\WorkflowApproval patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 */
class WorkflowApprovalsTable extends BaseTable
{
    public const REQUEST_TITLE_MAX_LENGTH = 255;

    /**
     * Request-local member approval scope cache.
     *
     * @var array<int, array<string, mixed>>
     */
    private static array $memberApprovalScopeCache = [];

    /**
     * Request-local pending approval eligibility cache.
     *
     * @var array<string, array<\App\Model\Entity\WorkflowApproval>>
     */
    private static array $pendingApprovalEligibilityCache = [];

    /**
     * Initialize method.
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('workflow_approvals');
        $this->setDisplayField('status');
        $this->setPrimaryKey('id');

        $this->belongsTo('WorkflowInstances', [
            'foreignKey' => 'workflow_instance_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('WorkflowExecutionLogs', [
            'foreignKey' => 'execution_log_id',
            'joinType' => 'INNER',
        ]);
        $this->hasMany('WorkflowApprovalResponses', [
            'foreignKey' => 'workflow_approval_id',
            'dependent' => true,
        ]);
        $this->hasMany('WorkflowApprovalTriageStates', [
            'foreignKey' => 'workflow_approval_id',
            'dependent' => true,
        ]);
        $this->belongsTo('CurrentApprover', [
            'className' => 'Members',
            'foreignKey' => 'current_approver_id',
            'joinType' => 'LEFT',
        ]);

        $this->addBehavior('Timestamp');

        // MariaDB stores JSON as longtext; explicitly map JSON columns
        $this->setJsonColumnTypesIfPresent(['approver_config', 'escalation_config']);
    }

    /**
     * Default validation rules.
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('workflow_instance_id')
            ->requirePresence('workflow_instance_id', 'create')
            ->notEmptyString('workflow_instance_id');

        $validator
            ->scalar('node_id')
            ->maxLength('node_id', 100)
            ->requirePresence('node_id', 'create')
            ->notEmptyString('node_id');

        $validator
            ->integer('execution_log_id')
            ->requirePresence('execution_log_id', 'create')
            ->notEmptyString('execution_log_id');

        $validator
            ->scalar('approver_type')
            ->requirePresence('approver_type', 'create')
            ->notEmptyString('approver_type')
            ->inList('approver_type', [
                WorkflowApproval::APPROVER_TYPE_PERMISSION,
                WorkflowApproval::APPROVER_TYPE_ROLE,
                WorkflowApproval::APPROVER_TYPE_MEMBER,
                WorkflowApproval::APPROVER_TYPE_DYNAMIC,
                WorkflowApproval::APPROVER_TYPE_POLICY,
            ]);

        $validator
            ->integer('required_count')
            ->notEmptyString('required_count');

        $validator
            ->integer('approved_count')
            ->notEmptyString('approved_count');

        $validator
            ->integer('rejected_count')
            ->notEmptyString('rejected_count');

        $validator
            ->scalar('status')
            ->requirePresence('status', 'create')
            ->notEmptyString('status')
            ->inList('status', [
                WorkflowApproval::STATUS_PENDING,
                WorkflowApproval::STATUS_APPROVED,
                WorkflowApproval::STATUS_REJECTED,
                WorkflowApproval::STATUS_EXPIRED,
                WorkflowApproval::STATUS_CANCELLED,
            ]);

        $validator
            ->boolean('allow_parallel')
            ->notEmptyString('allow_parallel');

        $validator
            ->dateTime('deadline')
            ->allowEmptyDateTime('deadline');

        $validator
            ->scalar('request_title')
            ->maxLength('request_title', self::REQUEST_TITLE_MAX_LENGTH)
            ->allowEmptyString('request_title');

        $validator
            ->integer('version')
            ->notEmptyString('version');

        return $validator;
    }

    /**
     * Resolve the searchable approval request title for a workflow instance.
     *
     * @param \App\Model\Entity\WorkflowInstance $instance Workflow instance.
     * @return string|null
     */
    public function resolveRequestTitleForInstance(WorkflowInstance $instance): ?string
    {
        try {
            $title = trim(ApprovalContextRendererRegistry::render($instance)->getTitle());
        } catch (Throwable $e) {
            Log::warning(sprintf(
                'WorkflowApprovals: Could not resolve request title for workflow instance %s: %s',
                (string)($instance->id ?? 'unknown'),
                $e->getMessage(),
            ));

            return null;
        }

        if ($title === '') {
            return null;
        }

        return mb_substr($title, 0, self::REQUEST_TITLE_MAX_LENGTH);
    }

    /**
     * Resolve the searchable approval request title for a workflow instance ID.
     *
     * @param int $instanceId Workflow instance ID.
     * @return string|null
     */
    public function resolveRequestTitleForInstanceId(int $instanceId): ?string
    {
        try {
            $instance = TableRegistry::getTableLocator()
                ->get('WorkflowInstances')
                ->find()
                ->where(['WorkflowInstances.id' => $instanceId])
                ->first();
        } catch (Throwable $e) {
            Log::warning(sprintf(
                'WorkflowApprovals: Could not load workflow instance %d for request title: %s',
                $instanceId,
                $e->getMessage(),
            ));

            return null;
        }

        if (!$instance instanceof WorkflowInstance) {
            return null;
        }

        return $this->resolveRequestTitleForInstance($instance);
    }

    /**
     * Build rules for referential integrity.
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['workflow_instance_id'], 'WorkflowInstances'), [
            'errorField' => 'workflow_instance_id',
        ]);
        $rules->add($rules->existsIn(['execution_log_id'], 'WorkflowExecutionLogs'), [
            'errorField' => 'execution_log_id',
        ]);

        return $rules;
    }

    /**
     * Get the count of pending approvals for a specific member.
     *
     * @param int $memberId The member ID to check
     * @return int Count of pending approvals
     */
    public static function getPendingApprovalCountForMember(int $memberId): int
    {
        return count(self::getPendingApprovalsMatchingMember($memberId));
    }

    /**
     * Get pending approvals for a member without invoking dynamic resolver services.
     *
     * This performs a cheap ID pass first, then loads only eligible rows with the
     * caller's requested associations for rich UI rendering.
     *
     * @param int $memberId The member ID to check.
     * @param array<string, mixed> $contain Associations to contain on the rich fetch.
     * @param array<int>|null $workflowInstanceIds Optional workflow instance scope.
     * @param int|null $limit Maximum approvals to return.
     * @param int $offset Number of eligible approvals to skip.
     * @return array<\App\Model\Entity\WorkflowApproval>
     */
    public static function getPendingApprovalsForMember(
        int $memberId,
        array $contain = [],
        ?array $workflowInstanceIds = null,
        ?int $limit = null,
        int $offset = 0,
    ): array {
        $approvalIds = self::getPendingApprovalIdsForMember($memberId, $workflowInstanceIds);
        if ($limit !== null) {
            $approvalIds = array_slice($approvalIds, max(0, $offset), max(0, $limit));
        } elseif ($offset > 0) {
            $approvalIds = array_slice($approvalIds, $offset);
        }
        if ($approvalIds === []) {
            return [];
        }

        return TableRegistry::getTableLocator()->get('WorkflowApprovals')->find()
            ->contain($contain)
            ->where(['WorkflowApprovals.id IN' => $approvalIds])
            ->orderBy(['WorkflowApprovals.modified' => 'DESC', 'WorkflowApprovals.id' => 'DESC'])
            ->all()
            ->toArray();
    }

    /**
     * Get pending approval IDs for a member without invoking dynamic resolver services.
     *
     * @param int $memberId The member ID to check.
     * @param array<int>|null $workflowInstanceIds Optional workflow instance scope.
     * @return array<int>
     */
    public static function getPendingApprovalIdsForMember(int $memberId, ?array $workflowInstanceIds = null): array
    {
        $approvalIds = [];
        foreach (self::getPendingApprovalsMatchingMember($memberId, $workflowInstanceIds) as $approval) {
            $approvalIds[] = (int)$approval->id;
        }

        return $approvalIds;
    }

    /**
     * Get pending workflow instance IDs for a member without invoking dynamic resolver services.
     *
     * @param int $memberId The member ID to check.
     * @param array<int>|null $workflowInstanceIds Optional workflow instance scope.
     * @return array<int>
     */
    public static function getPendingApprovalWorkflowInstanceIdsForMember(
        int $memberId,
        ?array $workflowInstanceIds = null,
    ): array {
        $instanceIds = [];
        foreach (self::getPendingApprovalsMatchingMember($memberId, $workflowInstanceIds) as $approval) {
            $instanceId = (int)($approval->workflow_instance_id ?? 0);
            if ($instanceId > 0) {
                $instanceIds[] = $instanceId;
            }
        }

        return array_values(array_unique($instanceIds));
    }

    /**
     * Check whether one pending approval is currently actionable for a member.
     *
     * @param int $approvalId Workflow approval ID.
     * @param int $memberId Member ID.
     * @return bool
     */
    public static function isPendingApprovalForMember(int $approvalId, int $memberId): bool
    {
        try {
            $responsesTable = TableRegistry::getTableLocator()->get('WorkflowApprovalResponses');
            $hasResponse = $responsesTable->find()
                ->where([
                    'workflow_approval_id' => $approvalId,
                    'member_id' => $memberId,
                ])
                ->count() > 0;
            if ($hasResponse) {
                return false;
            }

            $approval = TableRegistry::getTableLocator()->get('WorkflowApprovals')->find()
                ->select(['id', 'workflow_instance_id', 'approver_type', 'approver_config', 'current_approver_id'])
                ->where([
                    'WorkflowApprovals.id' => $approvalId,
                    'WorkflowApprovals.status' => WorkflowApproval::STATUS_PENDING,
                ])
                ->first();
            if (!$approval instanceof WorkflowApproval) {
                return false;
            }
            if (self::hasPriorDynamicWorkflowResponse($approval, $memberId)) {
                return false;
            }

            $approvals = [$approval];
            $memberScope = self::getMemberApprovalScope($memberId);
            $awardBranchIdsByRun = self::getAwardBranchIdsByApprovalRun($approvals);
            $branchIndex = self::getBranchIndex($awardBranchIdsByRun);

            return self::isApprovalPendingForMember(
                $approval,
                $memberId,
                $memberScope,
                $awardBranchIdsByRun,
                $branchIndex,
            );
        } catch (Exception $e) {
            Log::error("Error checking pending approval {$approvalId} for member {$memberId}: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Clear request-local pending approval eligibility cache.
     *
     * @return void
     */
    public static function clearPendingApprovalEligibilityCache(): void
    {
        self::$pendingApprovalEligibilityCache = [];
    }

    /**
     * Populate denormalized lookup fields from the approval config snapshot.
     *
     * @param \Cake\Event\Event $event The beforeSave event.
     * @param \Cake\Datasource\EntityInterface $entity Approval entity.
     * @param \ArrayObject $options Save options.
     * @return void
     */
    public function beforeSave(Event $event, EntityInterface $entity, ArrayObject $options): void
    {
        $this->syncApprovalLookupFields($entity);
    }

    /**
     * Clear pending approval caches when approval rows change.
     *
     * @param mixed $event Event object.
     * @param mixed $entity Saved entity.
     * @param mixed $options Save options.
     * @return void
     */
    public function afterSave($event, $entity, $options): void
    {
        parent::afterSave($event, $entity, $options);
        self::clearPendingApprovalEligibilityCache();
    }

    /**
     * Clear pending approval caches when approval rows are deleted.
     *
     * @param mixed $event Event object.
     * @param mixed $entity Deleted entity.
     * @param mixed $options Delete options.
     * @return void
     */
    public function afterDelete($event, $entity, $options): void
    {
        parent::afterDelete($event, $entity, $options);
        self::clearPendingApprovalEligibilityCache();
    }

    /**
     * Get pending approval rows matching the member's current approval scope.
     *
     * @param int $memberId The member ID to check.
     * @param array<int>|null $workflowInstanceIds Optional workflow instance scope.
     * @return array<\App\Model\Entity\WorkflowApproval>
     */
    private static function getPendingApprovalsMatchingMember(int $memberId, ?array $workflowInstanceIds = null): array
    {
        try {
            $workflowInstanceIds = self::normalizeWorkflowInstanceIds($workflowInstanceIds);
            if ($workflowInstanceIds !== null && $workflowInstanceIds === []) {
                return [];
            }

            $cacheKey = self::pendingApprovalEligibilityCacheKey($memberId, $workflowInstanceIds);
            if (array_key_exists($cacheKey, self::$pendingApprovalEligibilityCache)) {
                return self::$pendingApprovalEligibilityCache[$cacheKey];
            }

            $approvalsTable = TableRegistry::getTableLocator()->get('WorkflowApprovals');
            $memberScope = self::getMemberApprovalScope($memberId);

            $query = $approvalsTable->find()
                ->select(['id', 'workflow_instance_id', 'approver_type', 'approver_config', 'current_approver_id'])
                ->where(['WorkflowApprovals.status' => WorkflowApproval::STATUS_PENDING])
                ->orderBy(['WorkflowApprovals.modified' => 'DESC', 'WorkflowApprovals.id' => 'DESC'])
                ->where([
                    sprintf(
                        'NOT EXISTS (
                            SELECT 1
                            FROM workflow_approval_responses member_responses
                            WHERE member_responses.workflow_approval_id = WorkflowApprovals.id
                              AND member_responses.member_id = %d
                        )',
                        $memberId,
                    ),
                    sprintf(
                        "(WorkflowApprovals.approver_type != '%s' OR NOT EXISTS (
                            SELECT 1
                            FROM workflow_approval_responses prior_member_responses
                            INNER JOIN workflow_approvals prior_approvals
                                ON prior_approvals.id = prior_member_responses.workflow_approval_id
                            WHERE prior_approvals.workflow_instance_id = WorkflowApprovals.workflow_instance_id
                              AND prior_member_responses.member_id = %d
                        ))",
                        WorkflowApproval::APPROVER_TYPE_DYNAMIC,
                        $memberId,
                    ),
                ]);
            if ($workflowInstanceIds !== null) {
                $query->where(['WorkflowApprovals.workflow_instance_id IN' => $workflowInstanceIds]);
            }

            self::applyPendingApprovalCandidateScope($query, $memberId, $memberScope);

            $approvals = $query->all()->toArray();
            $awardBranchIdsByRun = self::getAwardBranchIdsByApprovalRun($approvals);
            $branchIndex = self::getBranchIndex($awardBranchIdsByRun);

            $eligible = [];
            foreach ($approvals as $approval) {
                if (
                    self::isApprovalPendingForMember(
                        $approval,
                        $memberId,
                        $memberScope,
                        $awardBranchIdsByRun,
                        $branchIndex,
                    )
                ) {
                    $eligible[] = $approval;
                }
            }

            return self::$pendingApprovalEligibilityCache[$cacheKey] = $eligible;
        } catch (Exception $e) {
            Log::error("Error fetching pending approvals for member {$memberId}: {$e->getMessage()}");

            return [];
        }
    }

    /**
     * Normalize optional workflow instance scoping for cache keys and queries.
     *
     * @param array<int>|null $workflowInstanceIds Optional workflow instance IDs.
     * @return array<int>|null
     */
    private static function normalizeWorkflowInstanceIds(?array $workflowInstanceIds): ?array
    {
        if ($workflowInstanceIds === null) {
            return null;
        }

        $workflowInstanceIds = array_map('intval', $workflowInstanceIds);
        $workflowInstanceIds = array_values(array_unique(array_filter($workflowInstanceIds)));
        sort($workflowInstanceIds);

        return $workflowInstanceIds;
    }

    /**
     * Build a stable cache key for a member/scoped pending approval lookup.
     *
     * @param int $memberId Member ID.
     * @param array<int>|null $workflowInstanceIds Optional workflow instance IDs.
     * @return string
     */
    private static function pendingApprovalEligibilityCacheKey(int $memberId, ?array $workflowInstanceIds): string
    {
        return $memberId . '|' . ($workflowInstanceIds === null ? '*' : implode(',', $workflowInstanceIds));
    }

    /**
     * Narrow pending approvals to rows that could match the member's current scope.
     *
     * The PHP eligibility pass remains authoritative for branch scoping, policies,
     * and current approver fallback values in JSON config.
     *
     * @param \Cake\ORM\Query\SelectQuery $query Pending approvals query.
     * @param int $memberId Current member ID.
     * @param array<string, mixed> $memberScope Current member approval scope.
     * @return void
     */
    private static function applyPendingApprovalCandidateScope(
        SelectQuery $query,
        int $memberId,
        array $memberScope,
    ): void {
        $roleIds = self::approvalScopeNestedIds($memberScope['roleIdsByBranch'] ?? []);
        $permissionIds = array_keys($memberScope['permissionsById'] ?? []);
        $officeIds = self::approvalScopeNestedIds($memberScope['officeIdsByBranch'] ?? []);

        $query->where([
            'OR' => [
                ['WorkflowApprovals.current_approver_id' => $memberId],
                [
                    'AND' => [
                        [
                            'OR' => [
                                ['WorkflowApprovals.current_approver_id IS' => null],
                                ['WorkflowApprovals.current_approver_id' => 0],
                            ],
                        ],
                        [
                            'OR' => self::pendingApprovalTypeCandidateConditions(
                                $memberId,
                                $memberScope,
                                $roleIds,
                                $permissionIds,
                                $officeIds,
                            ),
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @param int $memberId Current member ID.
     * @param array<string, mixed> $memberScope Current member approval scope.
     * @param array<int> $roleIds Active role IDs from the member scope.
     * @param array<int> $permissionIds Active permission IDs from the member scope.
     * @param array<int> $officeIds Active office IDs from the member scope.
     * @return array<array<string, mixed>>
     */
    private static function pendingApprovalTypeCandidateConditions(
        int $memberId,
        array $memberScope,
        array $roleIds,
        array $permissionIds,
        array $officeIds,
    ): array {
        $conditions = [
            [
                'WorkflowApprovals.approver_lookup_type' => WorkflowApproval::APPROVER_TYPE_MEMBER,
                'WorkflowApprovals.approver_lookup_id' => $memberId,
            ],
            [
                'WorkflowApprovals.approver_type' => WorkflowApproval::APPROVER_TYPE_DYNAMIC,
            ],
            [
                'WorkflowApprovals.approver_lookup_type' => WorkflowApproval::APPROVER_TYPE_POLICY,
            ],
        ];

        $permissionConditions = [];
        if ($permissionIds !== []) {
            $permissionConditions[] = ['WorkflowApprovals.approver_lookup_id IN' => $permissionIds];
        }
        if ($memberScope['permissionNames'] !== []) {
            $permissionConditions[] = ['WorkflowApprovals.approver_lookup_name IN' => $memberScope['permissionNames']];
        }
        if ($permissionConditions !== []) {
            $conditions[] = [
                'WorkflowApprovals.approver_lookup_type' => WorkflowApproval::APPROVER_TYPE_PERMISSION,
                'OR' => $permissionConditions,
            ];
        }

        $roleConditions = [];
        if ($roleIds !== []) {
            $roleConditions[] = ['WorkflowApprovals.approver_lookup_id IN' => $roleIds];
        }
        if ($memberScope['roleNames'] !== []) {
            $roleConditions[] = ['WorkflowApprovals.approver_lookup_name IN' => $memberScope['roleNames']];
        }
        if ($roleConditions !== []) {
            $conditions[] = [
                'WorkflowApprovals.approver_lookup_type' => WorkflowApproval::APPROVER_TYPE_ROLE,
                'OR' => $roleConditions,
            ];
        }

        if ($officeIds !== []) {
            $conditions[] = [
                'WorkflowApprovals.approver_lookup_type' => 'office',
                'WorkflowApprovals.approver_lookup_id IN' => $officeIds,
            ];
        }

        return $conditions;
    }

    /**
     * @param array<int, array<int, bool>> $idsByBranch Scope IDs keyed by branch.
     * @return array<int>
     */
    private static function approvalScopeNestedIds(array $idsByBranch): array
    {
        $ids = [];
        foreach ($idsByBranch as $branchIds) {
            foreach (array_keys($branchIds) as $id) {
                $id = (int)$id;
                if ($id > 0) {
                    $ids[$id] = true;
                }
            }
        }

        return array_keys($ids);
    }

    /**
     * Populate lookup fields used by member-centric approval queries.
     *
     * @param \Cake\Datasource\EntityInterface $entity Approval entity.
     * @return void
     */
    private function syncApprovalLookupFields(EntityInterface $entity): void
    {
        $config = $entity->approver_config ?? [];
        if (!is_array($config)) {
            $config = [];
        }

        if (array_key_exists('current_approver_id', $config)) {
            $entity->current_approver_id = self::positiveIntOrNull($config['current_approver_id']);
        }

        foreach ($this->approvalLookupFromConfig((string)$entity->approver_type, $config) as $field => $value) {
            $entity->set($field, $value);
        }
    }

    /**
     * @param string $approverType Approval type.
     * @param array<string, mixed> $config Approval config snapshot.
     * @return array<string, mixed>
     */
    private function approvalLookupFromConfig(string $approverType, array $config): array
    {
        $lookup = [
            'approver_lookup_type' => null,
            'approver_lookup_id' => null,
            'approver_lookup_name' => null,
            'approver_lookup_branch_id' => self::positiveIntOrNull($config['award_approval_branch_id'] ?? null),
            'approver_lookup_branch_mode' => self::stringOrNull($config['award_approval_branch_mode'] ?? null),
            'approver_lookup_branch_type' => self::stringOrNull($config['award_approval_branch_type'] ?? null),
            'approver_lookup_context_id' => self::positiveIntOrNull($config['award_approval_run_id'] ?? null),
        ];

        switch ($approverType) {
            case WorkflowApproval::APPROVER_TYPE_MEMBER:
                $lookup['approver_lookup_type'] = WorkflowApproval::APPROVER_TYPE_MEMBER;
                $lookup['approver_lookup_id'] = self::positiveIntOrNull($config['member_id'] ?? null);
                break;
            case WorkflowApproval::APPROVER_TYPE_ROLE:
                [$roleId, $roleName] = $this->resolveRoleLookup(
                    self::positiveIntOrNull($config['role_id'] ?? null),
                    self::stringOrNull($config['role'] ?? null),
                );
                $lookup['approver_lookup_type'] = WorkflowApproval::APPROVER_TYPE_ROLE;
                $lookup['approver_lookup_id'] = $roleId;
                $lookup['approver_lookup_name'] = $roleName;
                break;
            case WorkflowApproval::APPROVER_TYPE_PERMISSION:
                [$permissionId, $permissionName] = $this->resolvePermissionLookup(
                    self::positiveIntOrNull($config['permission_id'] ?? null),
                    self::stringOrNull($config['permission'] ?? null),
                );
                $lookup['approver_lookup_type'] = WorkflowApproval::APPROVER_TYPE_PERMISSION;
                $lookup['approver_lookup_id'] = $permissionId;
                $lookup['approver_lookup_name'] = $permissionName;
                break;
            case WorkflowApproval::APPROVER_TYPE_DYNAMIC:
                $lookup['approver_lookup_type'] = self::stringOrNull($config['award_approval_approver_type'] ?? null);
                $lookup['approver_lookup_id'] = self::positiveIntOrNull(
                    $config['award_approval_approver_source_id'] ?? $config['member_id'] ?? null,
                );
                break;
            case WorkflowApproval::APPROVER_TYPE_POLICY:
                $lookup['approver_lookup_type'] = WorkflowApproval::APPROVER_TYPE_POLICY;
                $lookup['approver_lookup_name'] = self::stringOrNull($config['policyClass'] ?? null);
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
     * Check badge-count eligibility without invoking dynamic resolver services.
     *
     * @param \App\Model\Entity\WorkflowApproval $approval Pending approval row.
     * @param int $memberId Current member ID.
     * @param array<string, mixed> $memberScope Current member approval scope.
     * @param array<int, int> $awardBranchIdsByRun Award branch IDs keyed by recommendation approval run ID.
     * @param array<int, array{parent_id:int|null,type:string|null}> $branchIndex Branch hierarchy keyed by branch ID.
     * @return bool
     */
    private static function isApprovalPendingForMember(
        WorkflowApproval $approval,
        int $memberId,
        array $memberScope,
        array $awardBranchIdsByRun,
        array $branchIndex,
    ): bool {
        $config = $approval->approver_config ?? [];
        $currentApproverId = (int)($approval->current_approver_id ?? $config['current_approver_id'] ?? 0);
        if ($approval->approver_type === WorkflowApproval::APPROVER_TYPE_DYNAMIC && $currentApproverId > 0) {
            if (empty($config['award_approval_approver_type'])) {
                return $memberId === $currentApproverId;
            }

            return $memberId === $currentApproverId && self::dynamicConfigIncludesMember(
                $config,
                $memberId,
                $memberScope,
                $awardBranchIdsByRun,
                $branchIndex,
            );
        }
        if ($currentApproverId > 0) {
            return $memberId === $currentApproverId;
        }

        return match ($approval->approver_type) {
            WorkflowApproval::APPROVER_TYPE_PERMISSION => self::memberHasConfiguredPermission($config, $memberScope),
            WorkflowApproval::APPROVER_TYPE_ROLE => self::memberHasConfiguredRole($config, $memberScope),
            WorkflowApproval::APPROVER_TYPE_MEMBER => $memberId === (int)($config['member_id'] ?? 0),
            WorkflowApproval::APPROVER_TYPE_DYNAMIC => self::dynamicConfigIncludesMember(
                $config,
                $memberId,
                $memberScope,
                $awardBranchIdsByRun,
                $branchIndex,
            ),
            WorkflowApproval::APPROVER_TYPE_POLICY => self::memberPassesPolicy($approval, $memberId),
            default => false,
        };
    }

    /**
     * Check direct permission approver config against the member scope.
     *
     * @param array<string, mixed> $config Approval config.
     * @param array<string, mixed> $memberScope Current member approval scope.
     * @return bool
     */
    private static function memberHasConfiguredPermission(array $config, array $memberScope): bool
    {
        $permissionId = (int)($config['permission_id'] ?? 0);
        if ($permissionId > 0) {
            return isset($memberScope['permissionsById'][$permissionId]);
        }

        $permissionName = (string)($config['permission'] ?? '');

        return $permissionName !== '' && in_array($permissionName, $memberScope['permissionNames'], true);
    }

    /**
     * Check direct role approver config against the member scope.
     *
     * @param array<string, mixed> $config Approval config.
     * @param array<string, mixed> $memberScope Current member approval scope.
     * @return bool
     */
    private static function memberHasConfiguredRole(array $config, array $memberScope): bool
    {
        $roleId = (int)($config['role_id'] ?? 0);
        if ($roleId > 0) {
            foreach ($memberScope['roleIdsByBranch'] as $roleIds) {
                if (isset($roleIds[$roleId])) {
                    return true;
                }
            }

            return false;
        }

        $roleName = (string)($config['role'] ?? '');

        return $roleName !== '' && in_array($roleName, $memberScope['roleNames'], true);
    }

    /**
     * Check whether the member passes the approval's configured policy.
     */
    private static function memberPassesPolicy(WorkflowApproval $approval, int $memberId): bool
    {
        $config = $approval->approver_config ?? [];
        $policyClass = $config['policyClass'] ?? null;
        $policyAction = $config['policyAction'] ?? null;
        $entityTable = $config['entityTable'] ?? null;
        $entityIdKey = $config['entityIdKey'] ?? null;

        if (!$policyClass || !$policyAction || !$entityTable || !$entityIdKey) {
            return false;
        }
        if (!class_exists((string)$policyClass)) {
            return false;
        }

        $instance = TableRegistry::getTableLocator()->get('WorkflowInstances')
            ->get((int)$approval->workflow_instance_id);
        $entityId = self::resolveContextValue($instance->context ?? [], (string)$entityIdKey);
        if (!$entityId) {
            return false;
        }

        $table = TableRegistry::getTableLocator()->get((string)$entityTable);
        $entity = $table->find()
            ->where([$table->getAlias() . '.id' => (int)$entityId])
            ->first();
        if (!$entity) {
            return false;
        }

        $member = TableRegistry::getTableLocator()->get('Members')->get($memberId);
        $policy = new $policyClass();
        if (!method_exists($policy, (string)$policyAction)) {
            return false;
        }

        try {
            return (bool)$policy->{$policyAction}($member, $entity);
        } catch (Exception $exception) {
            Log::error("Policy approval check failed: {$exception->getMessage()}");

            return false;
        }
    }

    /**
     * Resolve a dot-path value from workflow context.
     */
    private static function resolveContextValue(array $context, string $key): mixed
    {
        $current = $context;
        foreach (explode('.', $key) as $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return null;
            }
            $current = $current[$part];
        }

        return $current;
    }

    /**
     * Check dynamic approver config against the member's current branch-scoped roles, permissions, and offices.
     *
     * @param array<string, mixed> $config Approval config.
     * @param int $memberId Current member ID.
     * @param array<string, mixed> $memberScope Current member approval scope.
     * @param array<int, int> $awardBranchIdsByRun Award branch IDs keyed by recommendation approval run ID.
     * @param array<int, array{parent_id:int|null,type:string|null}> $branchIndex Branch hierarchy keyed by branch ID.
     * @return bool
     */
    private static function dynamicConfigIncludesMember(
        array $config,
        int $memberId,
        array $memberScope,
        array $awardBranchIdsByRun,
        array $branchIndex,
    ): bool {
        $approverType = (string)($config['award_approval_approver_type'] ?? '');
        if ($approverType === 'member') {
            return $memberId === (int)($config['award_approval_approver_source_id'] ?? $config['member_id'] ?? 0);
        }

        $sourceId = (int)($config['award_approval_approver_source_id'] ?? 0);
        if ($sourceId <= 0) {
            return false;
        }

        $branchId = self::approvalBranchId($config, $awardBranchIdsByRun, $branchIndex);
        if ($branchId === null) {
            return false;
        }

        return match ($approverType) {
            'role' => isset($memberScope['roleIdsByBranch'][$branchId][$sourceId]),
            'permission' => self::memberPermissionCoversBranch(
                $memberScope['permissionsById'][$sourceId] ?? null,
                $branchId,
            ),
            'office' => isset($memberScope['officeIdsByBranch'][$branchId][$sourceId]),
            default => false,
        };
    }

    /**
     * Check whether a loader permission object covers the given branch.
     *
     * Permission scope is resolved by {@see \App\KMP\PermissionsLoader::getPermissions()}:
     * `branch_ids === null` denotes a Global permission (covers every branch),
     * otherwise it is the explicit list of branches the member holds it in
     * (including descendants for branch-and-children scoping).
     *
     * @param object|null $permission Loader permission object, or null if not held.
     * @param int $branchId Branch ID to test coverage for.
     * @return bool
     */
    private static function memberPermissionCoversBranch(?object $permission, int $branchId): bool
    {
        if ($permission === null) {
            return false;
        }
        if ($permission->branch_ids === null) {
            return true;
        }

        return in_array($branchId, $permission->branch_ids, true);
    }

    /**
     * Check whether the member has already responded in this dynamic approval's workflow instance.
     *
     * @param \App\Model\Entity\WorkflowApproval $approval Pending approval.
     * @param int $memberId Member ID.
     * @return bool
     */
    private static function hasPriorDynamicWorkflowResponse(WorkflowApproval $approval, int $memberId): bool
    {
        if ($approval->approver_type !== WorkflowApproval::APPROVER_TYPE_DYNAMIC) {
            return false;
        }

        $workflowInstanceId = (int)$approval->workflow_instance_id;
        if ($workflowInstanceId <= 0 || $memberId <= 0) {
            return false;
        }

        return TableRegistry::getTableLocator()->get('WorkflowApprovalResponses')->find()
            ->innerJoinWith('WorkflowApprovals', function ($q) use ($workflowInstanceId) {
                return $q->where(['WorkflowApprovals.workflow_instance_id' => $workflowInstanceId]);
            })
            ->where(['WorkflowApprovalResponses.member_id' => $memberId])
            ->count() > 0;
    }

    /**
     * Resolve the branch scope encoded by an award approval config.
     *
     * @param array<string, mixed> $config Approval config.
     * @param array<int, int> $awardBranchIdsByRun Award branch IDs keyed by recommendation approval run ID.
     * @param array<int, array{parent_id:int|null,type:string|null}> $branchIndex Branch hierarchy keyed by branch ID.
     * @return int|null
     */
    private static function approvalBranchId(array $config, array $awardBranchIdsByRun, array $branchIndex): ?int
    {
        if (!empty($config['award_approval_branch_id'])) {
            return (int)$config['award_approval_branch_id'];
        }

        $runId = (int)($config['award_approval_run_id'] ?? 0);
        $awardBranchId = $awardBranchIdsByRun[$runId] ?? null;
        if ($awardBranchId === null) {
            return null;
        }

        if (($config['award_approval_branch_mode'] ?? 'award_branch') !== 'ancestor_branch_type') {
            return $awardBranchId;
        }

        $targetType = (string)($config['award_approval_branch_type'] ?? '');
        if ($targetType === '') {
            return null;
        }

        $branchId = $awardBranchId;
        while (isset($branchIndex[$branchId])) {
            if ((string)$branchIndex[$branchId]['type'] === $targetType) {
                return $branchId;
            }
            $parentId = $branchIndex[$branchId]['parent_id'];
            if ($parentId === null) {
                break;
            }
            $branchId = $parentId;
        }

        return null;
    }

    /**
     * Build the current member scope needed for fast approval badge counts.
     *
     * @param int $memberId Member ID.
     * @return array<string, mixed>
     */
    private static function getMemberApprovalScope(int $memberId): array
    {
        if (isset(self::$memberApprovalScopeCache[$memberId])) {
            return self::$memberApprovalScopeCache[$memberId];
        }

        $now = DateTime::now();

        // Source roles and permissions through the canonical cached loader so the
        // approval scope honours warrant/membership validation and permission
        // scoping rules (Global vs branch) instead of querying member_roles directly.
        $roleNamesByName = [];
        $roleIdsByBranch = [];
        foreach (PermissionsLoader::getRoles($memberId) as $role) {
            $roleName = (string)$role->name;
            if ($roleName !== '') {
                $roleNamesByName[$roleName] = true;
            }
            $roleId = (int)$role->id;
            foreach ($role->branch_ids as $branchId) {
                $branchId = (int)$branchId;
                if ($branchId > 0 && $roleId > 0) {
                    $roleIdsByBranch[$branchId][$roleId] = true;
                }
            }
        }

        // Permissions keep their full scoping-aware objects (branch_ids === null
        // means Global / all branches) for the dynamic branch-coverage check below.
        $permissionNamesByName = [];
        $permissionsById = [];
        foreach (PermissionsLoader::getPermissions($memberId) as $permission) {
            $permissionName = (string)$permission->name;
            if ($permissionName !== '') {
                $permissionNamesByName[$permissionName] = true;
            }
            $permissionId = (int)$permission->id;
            if ($permissionId > 0) {
                $permissionsById[$permissionId] = $permission;
            }
        }

        $officeIdsByBranch = [];
        $officerRows = TableRegistry::getTableLocator()->get('Officers.Officers')->find()
            ->select(['office_id', 'branch_id'])
            ->where([
                'Officers.member_id' => $memberId,
                'Officers.status' => 'Current',
                'Officers.start_on <=' => $now,
                'OR' => [
                    'Officers.expires_on IS' => null,
                    'Officers.expires_on >=' => $now,
                ],
            ])
            ->enableHydration(false)
            ->all();
        foreach ($officerRows as $row) {
            $branchId = (int)($row['branch_id'] ?? 0);
            $officeId = (int)($row['office_id'] ?? 0);
            if ($branchId > 0 && $officeId > 0) {
                $officeIdsByBranch[$branchId][$officeId] = true;
            }
        }

        return self::$memberApprovalScopeCache[$memberId] = [
            'permissionNames' => array_keys($permissionNamesByName),
            'roleNames' => array_keys($roleNamesByName),
            'roleIdsByBranch' => $roleIdsByBranch,
            'permissionsById' => $permissionsById,
            'officeIdsByBranch' => $officeIdsByBranch,
        ];
    }

    /**
     * Clear request-local approval scope cache.
     *
     * Primarily useful for tests or long-running CLI workers that mutate member
     * role/office state and then need fresh approval-scope reads.
     *
     * @return void
     */
    public static function clearApprovalScopeCache(): void
    {
        self::$memberApprovalScopeCache = [];
        self::clearPendingApprovalEligibilityCache();
    }

    /**
     * Batch-load award branch IDs for pending award approval runs.
     *
     * @param array<\App\Model\Entity\WorkflowApproval> $approvals Pending approvals.
     * @return array<int, int>
     */
    private static function getAwardBranchIdsByApprovalRun(array $approvals): array
    {
        $runIds = [];
        foreach ($approvals as $approval) {
            $config = $approval->approver_config ?? [];
            if (($config['service'] ?? null) !== 'Awards.ResolveApprovalStepApprovers') {
                continue;
            }
            $runId = (int)($config['award_approval_run_id'] ?? 0);
            if ($runId > 0) {
                $runIds[] = $runId;
            }
        }
        $runIds = array_values(array_unique($runIds));
        if ($runIds === []) {
            return [];
        }

        $runsTable = TableRegistry::getTableLocator()->get('Awards.RecommendationApprovalRuns');
        $rows = $runsTable->find()
            ->select([
                'id' => 'RecommendationApprovalRuns.id',
                'award_branch_id' => 'Awards.branch_id',
            ])
            ->innerJoinWith('Recommendations.Awards')
            ->where(['RecommendationApprovalRuns.id IN' => $runIds])
            ->enableHydration(false)
            ->all();

        $branchIds = [];
        foreach ($rows as $row) {
            $branchIds[(int)$row['id']] = (int)$row['award_branch_id'];
        }

        return $branchIds;
    }

    /**
     * Load branch hierarchy data only when award approvals may need it.
     *
     * @param array<int, int> $awardBranchIdsByRun Award branch IDs keyed by recommendation approval run ID.
     * @return array<int, array{parent_id:int|null,type:string|null}>
     */
    private static function getBranchIndex(array $awardBranchIdsByRun): array
    {
        if ($awardBranchIdsByRun === []) {
            return [];
        }

        $branches = TableRegistry::getTableLocator()->get('Branches')->find()
            ->select(['id', 'parent_id', 'type'])
            ->enableHydration(false)
            ->all();

        $index = [];
        foreach ($branches as $branch) {
            $index[(int)$branch['id']] = [
                'parent_id' => $branch['parent_id'] !== null ? (int)$branch['parent_id'] : null,
                'type' => $branch['type'] !== null ? (string)$branch['type'] : null,
            ];
        }

        return $index;
    }
}
