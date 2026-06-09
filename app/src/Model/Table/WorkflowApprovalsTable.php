<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\WorkflowApproval;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\RulesChecker;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use Exception;

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
    /**
     * Request-local member approval scope cache.
     *
     * @var array<int, array<string, mixed>>
     */
    private static array $memberApprovalScopeCache = [];

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
        $this->getSchema()->setColumnType('approver_config', 'json');
        $this->getSchema()->setColumnType('escalation_config', 'json');
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
            ->integer('version')
            ->notEmptyString('version');

        return $validator;
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
        return count(self::getPendingApprovalIdsForMember($memberId));
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
     * @return array<\App\Model\Entity\WorkflowApproval>
     */
    public static function getPendingApprovalsForMember(
        int $memberId,
        array $contain = [],
        ?array $workflowInstanceIds = null,
    ): array {
        $approvalIds = self::getPendingApprovalIdsForMember($memberId, $workflowInstanceIds);
        if ($approvalIds === []) {
            return [];
        }

        return TableRegistry::getTableLocator()->get('WorkflowApprovals')->find()
            ->contain($contain)
            ->where(['WorkflowApprovals.id IN' => $approvalIds])
            ->orderBy(['WorkflowApprovals.modified' => 'DESC'])
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
     * Get pending approval rows matching the member's current approval scope.
     *
     * @param int $memberId The member ID to check.
     * @param array<int>|null $workflowInstanceIds Optional workflow instance scope.
     * @return array<\App\Model\Entity\WorkflowApproval>
     */
    private static function getPendingApprovalsMatchingMember(int $memberId, ?array $workflowInstanceIds = null): array
    {
        try {
            if ($workflowInstanceIds !== null) {
                $workflowInstanceIds = array_map('intval', $workflowInstanceIds);
                $workflowInstanceIds = array_values(array_unique(array_filter($workflowInstanceIds)));
                if ($workflowInstanceIds === []) {
                    return [];
                }
            }

            $approvalsTable = TableRegistry::getTableLocator()->get('WorkflowApprovals');
            $memberScope = self::getMemberApprovalScope($memberId);

            $query = $approvalsTable->find()
                ->select(['id', 'workflow_instance_id', 'approver_type', 'approver_config', 'current_approver_id'])
                ->where(['WorkflowApprovals.status' => WorkflowApproval::STATUS_PENDING])
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

            $candidateTypes = [
                WorkflowApproval::APPROVER_TYPE_MEMBER,
                WorkflowApproval::APPROVER_TYPE_DYNAMIC,
            ];
            if ($memberScope['roleNames'] !== []) {
                $candidateTypes[] = WorkflowApproval::APPROVER_TYPE_ROLE;
            }
            if ($memberScope['permissionNames'] !== []) {
                $candidateTypes[] = WorkflowApproval::APPROVER_TYPE_PERMISSION;
            }
            $candidateTypes[] = WorkflowApproval::APPROVER_TYPE_POLICY;

            $query->where([
                'OR' => [
                    ['WorkflowApprovals.current_approver_id' => $memberId],
                    ['WorkflowApprovals.approver_type IN' => $candidateTypes],
                ],
            ]);

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

            return $eligible;
        } catch (Exception $e) {
            Log::error("Error fetching pending approvals for member {$memberId}: {$e->getMessage()}");

            return [];
        }
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
        if ($currentApproverId > 0) {
            return $memberId === $currentApproverId;
        }

        return match ($approval->approver_type) {
            WorkflowApproval::APPROVER_TYPE_PERMISSION => in_array(
                (string)($config['permission'] ?? ''),
                $memberScope['permissionNames'],
                true,
            ),
            WorkflowApproval::APPROVER_TYPE_ROLE => in_array(
                (string)($config['role'] ?? ''),
                $memberScope['roleNames'],
                true,
            ),
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
            'permission' => isset($memberScope['permissionIdsByBranch'][$branchId][$sourceId]),
            'office' => isset($memberScope['officeIdsByBranch'][$branchId][$sourceId]),
            default => false,
        };
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

        $memberRolesTable = TableRegistry::getTableLocator()->get('MemberRoles');
        $now = DateTime::now();

        $roleNamesByName = [];
        $roleIdsByBranch = [];
        $roleRows = $memberRolesTable->find('current')
            ->innerJoinWith('Roles')
            ->select([
                'role_id' => 'MemberRoles.role_id',
                'branch_id' => 'MemberRoles.branch_id',
                'role_name' => 'Roles.name',
            ])
            ->where(['MemberRoles.member_id' => $memberId])
            ->enableHydration(false)
            ->all()
            ->toList();
        foreach ($roleRows as $row) {
            $roleName = (string)($row['role_name'] ?? '');
            if ($roleName !== '') {
                $roleNamesByName[$roleName] = true;
            }
            $branchId = (int)($row['branch_id'] ?? 0);
            $roleId = (int)($row['role_id'] ?? 0);
            if ($branchId > 0 && $roleId > 0) {
                $roleIdsByBranch[$branchId][$roleId] = true;
            }
        }

        $permissionNamesByName = [];
        $permissionIdsByBranch = [];
        $permissionRows = $memberRolesTable->find('current')
            ->select([
                'branch_id' => 'MemberRoles.branch_id',
                'permission_id' => 'Permissions.id',
                'permission_name' => 'Permissions.name',
            ])
            ->matching('Roles.Permissions')
            ->where(['MemberRoles.member_id' => $memberId])
            ->enableHydration(false)
            ->all()
            ->toList();
        foreach ($permissionRows as $row) {
            $permissionName = (string)($row['permission_name'] ?? '');
            if ($permissionName !== '') {
                $permissionNamesByName[$permissionName] = true;
            }
            $branchId = (int)($row['branch_id'] ?? 0);
            $permissionId = (int)($row['permission_id'] ?? 0);
            if ($branchId > 0 && $permissionId > 0) {
                $permissionIdsByBranch[$branchId][$permissionId] = true;
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
            'permissionIdsByBranch' => $permissionIdsByBranch,
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
