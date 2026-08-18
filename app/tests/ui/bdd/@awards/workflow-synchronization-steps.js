const { createBdd } = require('playwright-bdd');
const { expect } = require('@playwright/test');
const {
    runPhpJson,
    waitForPageBody,
} = require('../../support/ui-helpers.cjs');

const { Given, When, Then, After } = createBdd();

const RECOMMENDATION_SYNC_PATH = '/awards/approval-processes';
const RECOMMENDATION_SYNC_POST_PATH = '/awards/approval-processes/sync-open-recommendations';
const BESTOWAL_SYNC_PATH = '/awards/bestowal-todo-templates';
const BESTOWAL_SYNC_POST_PATH = '/awards/bestowal-todo-templates/sync-open-bestowals';
const RECOMMENDATION_SYNC_CONTROL = 'Sync Open Recommendations Now';
const BESTOWAL_SYNC_CONTROL = 'Sync Open Bestowals Now';

const SETUP_SYNC_FIXTURE_PHP = String.raw`
require 'vendor/autoload.php';
require 'config/bootstrap.php';

\Cake\Core\Configure::write('Queue.plugins', array_values(array_unique(array_merge(
    (array)\Cake\Core\Configure::read('Queue.plugins'),
    ['Queue'],
))));
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$locator = \Cake\ORM\TableRegistry::getTableLocator();
$members = $locator->get('Members');
$roles = $locator->get('Roles');
$memberRoles = $locator->get('MemberRoles');
$processes = $locator->get('Awards.ApprovalProcesses');
$awards = $locator->get('Awards.Awards');
$templates = $locator->get('Awards.BestowalTodoTemplates');
$templateItems = $locator->get('Awards.BestowalTodoTemplateItems');
$recommendations = $locator->get('Awards.Recommendations');
$runs = $locator->get('Awards.RecommendationApprovalRuns');
$approvals = $locator->get('WorkflowApprovals');
$responses = $locator->get('WorkflowApprovalResponses');
$connection = $recommendations->getConnection();
$connection->enableSavePoints();

$result = $connection->transactional(function () use (
    $input,
    $locator,
    $members,
    $roles,
    $memberRoles,
    $processes,
    $awards,
    $templates,
    $templateItems,
    $recommendations,
    $runs,
    $approvals,
    $responses,
): array {
    $actor = $members->find()
        ->select(['id', 'branch_id', 'sca_name', 'email_address', 'phone_number'])
        ->where(['email_address' => 'admin@amp.ansteorra.org'])
        ->firstOrFail();
    $secondApprover = $members->find()
        ->select(['id', 'branch_id', 'sca_name', 'email_address'])
        ->where(['email_address' => 'bryce@ampdemo.com'])
        ->firstOrFail();
    $requester = $members->find()
        ->select(['id', 'branch_id', 'sca_name', 'email_address', 'phone_number'])
        ->where(['email_address' => 'forest@ampdemo.com'])
        ->firstOrFail();
    $candidate = $members->find()
        ->select(['id', 'public_id', 'sca_name'])
        ->where(['sca_name' => 'Iris Basic User Demoer'])
        ->firstOrFail();
    $seedAward = $awards->find()
        ->select(['domain_id', 'level_id', 'branch_id'])
        ->where([
            'Awards.deleted IS' => null,
            'Awards.domain_id IS NOT' => null,
            'Awards.level_id IS NOT' => null,
            'Awards.branch_id IS NOT' => null,
        ])
        ->orderByAsc('Awards.id')
        ->firstOrFail();
    $branchId = (int)$seedAward->branch_id;

    $role = $roles->saveOrFail($roles->newEntity([
        'name' => 'E2E workflow sync approvers ' . (string)$input['token'],
    ]));
    $memberRoleIds = [];
    foreach ([(int)$actor->id, (int)$secondApprover->id] as $memberId) {
        $memberRole = $memberRoles->newEmptyEntity();
        $memberRole->member_id = $memberId;
        $memberRole->role_id = (int)$role->id;
        $memberRole->branch_id = $branchId;
        $memberRole->approver_id = (int)$actor->id;
        $memberRole->start_on = new \Cake\I18n\DateTime('-1 day');
        $memberRole->expires_on = new \Cake\I18n\DateTime('+30 days');
        $memberRole->created_by = (int)$actor->id;
        $memberRole->modified_by = (int)$actor->id;
        $memberRoles->saveOrFail($memberRole);
        $memberRoleIds[] = (int)$memberRole->id;
    }

    $process = $processes->saveOrFail($processes->newEntity([
        'name' => 'E2E workflow sync process ' . (string)$input['token'],
        'description' => 'Scenario-owned two-person approval process.',
        'is_active' => true,
        'approval_process_steps' => [[
            'step_key' => 'crown',
            'label' => 'Two-person Crown approval',
            'sequence' => 1,
            'step_type' => \Awards\Model\Entity\ApprovalProcessStep::STEP_TYPE_APPROVAL,
            'approver_type' => \Awards\Model\Entity\ApprovalProcessStep::APPROVER_TYPE_ROLE,
            'approver_source_id' => (int)$role->id,
            'branch_mode' => \Awards\Model\Entity\ApprovalProcessStep::BRANCH_MODE_AWARD,
            'threshold_mode' => \Awards\Model\Entity\ApprovalProcessStep::THRESHOLD_COUNT,
            'required_count' => 2,
            'on_reject' => \Awards\Model\Entity\ApprovalProcessStep::ACTION_RETURN_PREVIOUS,
            'on_request_changes' => \Awards\Model\Entity\ApprovalProcessStep::ACTION_RETURN_PREVIOUS,
            'retain_read_visibility' => true,
        ]],
    ], ['associated' => ['ApprovalProcessSteps']]));
    $step = $locator->get('Awards.ApprovalProcessSteps')->find()
        ->where(['approval_process_id' => (int)$process->id, 'step_key' => 'crown'])
        ->firstOrFail();

    $template = $templates->saveOrFail($templates->newEntity([
        'name' => 'E2E workflow sync to-dos ' . (string)$input['token'],
        'description' => 'Scenario-owned bestowal to-do definition.',
        'branch_id' => $branchId,
        'is_active' => true,
    ]));
    $todoDefinitions = [
        [
            'item_key' => 'scroll_assigned',
            'label' => 'Scroll assigned before sync',
            'description' => 'Original shared definition.',
            'is_gating' => true,
            'sort_order' => 0,
        ],
        [
            'item_key' => 'scroll_finished',
            'label' => 'Scroll finished before sync',
            'description' => 'Definition removed and later restored by the scenario.',
            'is_gating' => false,
            'sort_order' => 1,
        ],
    ];
    $templateItemIds = [];
    foreach ($todoDefinitions as $definition) {
        $item = $templateItems->saveOrFail($templateItems->newEntity($definition + [
            'template_id' => (int)$template->id,
            'assignee_type' => \Awards\Model\Entity\BestowalTodoTemplateItem::ASSIGNEE_TYPE_MEMBER,
            'assignee_source_id' => (int)$actor->id,
            'branch_mode' => \Awards\Model\Entity\BestowalTodoTemplateItem::BRANCH_MODE_AWARD,
        ]));
        $templateItemIds[(string)$item->item_key] = (int)$item->id;
    }

    $award = $awards->saveOrFail($awards->newEntity([
        'name' => 'E2E Workflow Sync Award ' . (string)$input['token'],
        'abbreviation' => strtoupper(substr(hash('sha256', (string)$input['token']), 0, 10)),
        'domain_id' => (int)$seedAward->domain_id,
        'level_id' => (int)$seedAward->level_id,
        'branch_id' => $branchId,
        'approval_process_id' => (int)$process->id,
        'bestowal_todo_template_id' => (int)$template->id,
        'is_active' => true,
    ]));

    \Awards\Services\AwardsWorkflowProvider::register();
    \App\Services\WorkflowRegistry\WorkflowActionRegistry::register('Core', [[
        'action' => 'Core.SendEmail',
        'label' => 'Send Email',
        'description' => 'Send an email notification using a configured template',
        'inputSchema' => [],
        'outputSchema' => [],
        'serviceClass' => \App\Services\WorkflowEngine\Actions\CoreActions::class,
        'serviceMethod' => 'sendEmail',
        'isAsync' => false,
    ]]);
    \App\Services\WorkflowRegistry\WorkflowConditionRegistry::register('Core', [[
        'condition' => 'Core.FieldEquals',
        'label' => 'Field Equals Value',
        'description' => 'Check if a context field equals a specific value',
        'inputSchema' => [],
        'evaluatorClass' => \App\Services\WorkflowEngine\Conditions\CoreConditions::class,
        'evaluatorMethod' => 'fieldEquals',
    ]]);
    $container = new \Cake\Core\Container();
    $container->add(
        \App\Services\ActiveWindowManager\ActiveWindowManagerInterface::class,
        \App\Services\ActiveWindowManager\DefaultActiveWindowManager::class,
    );
    $container->add(\App\Services\WorkflowEngine\ExpressionEvaluator::class);
    $container->add(\App\Services\WorkflowEngine\Actions\CoreActions::class)
        ->addArguments([
            \App\Services\ActiveWindowManager\ActiveWindowManagerInterface::class,
            \App\Services\WorkflowEngine\ExpressionEvaluator::class,
        ]);
    $container->add(\App\Services\WorkflowEngine\Conditions\CoreConditions::class)
        ->addArgument(\App\Services\WorkflowEngine\ExpressionEvaluator::class);
    $container->add(\App\Services\WorkflowEngine\StateMachine\StateMachineHandler::class);
    $container->add(\Awards\Services\AwardsWorkflowActions::class);
    $container->add(\Awards\Services\AwardsWorkflowConditions::class);
    $engine = new \App\Services\WorkflowEngine\DefaultWorkflowEngine($container);
    $dispatcher = new \App\Services\WorkflowEngine\TriggerDispatcher($engine);

    $reason = 'E2E workflow synchronization preserved response ' . (string)$input['token'];
    $dispatchResults = $dispatcher->dispatch(
        'Awards.RecommendationCreateRequested',
        [
            'data' => [
                'award_id' => (int)$award->id,
                'member_sca_name' => (string)$candidate->sca_name,
                'member_public_id' => (string)$candidate->public_id,
                'reason' => $reason,
                'specialty' => 'No specialties available',
            ],
            'requesterContext' => [
                'id' => (int)$requester->id,
                'sca_name' => (string)$requester->sca_name,
                'email_address' => (string)$requester->email_address,
                'phone_number' => (string)($requester->phone_number ?? ''),
            ],
            'submissionMode' => 'authenticated',
            'actorId' => (int)$requester->id,
            'branchId' => $branchId,
        ],
        (int)$requester->id,
    );
    if ($dispatchResults === []) {
        throw new \RuntimeException('The recommendation trigger returned no workflow results.');
    }
    foreach ($dispatchResults as $dispatchResult) {
        if ($dispatchResult instanceof \App\Services\ServiceResult && !$dispatchResult->isSuccess()) {
            throw new \RuntimeException(
                'The recommendation trigger failed: ' . ($dispatchResult->getError() ?? 'unknown error'),
            );
        }
    }
    $recommendation = $recommendations->find()
        ->where([
            'award_id' => (int)$award->id,
            'reason' => $reason,
        ])
        ->orderByDesc('id')
        ->firstOrFail();
    $run = $runs->find()
        ->where(['recommendation_id' => (int)$recommendation->id])
        ->orderByDesc('id')
        ->firstOrFail();
    $approval = $approvals->find()
        ->where([
            'workflow_instance_id' => (int)$run->workflow_instance_id,
            'status' => \App\Model\Entity\WorkflowApproval::STATUS_PENDING,
        ])
        ->orderByDesc('id')
        ->firstOrFail();
    $approvalService = new \Awards\Services\RecommendationApprovalProcessService();
    $eligibleIds = $approvalService->resolveConfiguredApproverIds($approval);
    sort($eligibleIds);
    $expectedEligibleIds = [(int)$actor->id, (int)$secondApprover->id];
    sort($expectedEligibleIds);
    if ($eligibleIds !== $expectedEligibleIds || (int)$approval->required_count !== 2) {
        throw new \RuntimeException(sprintf(
            'Expected a two-person, two-required gate; eligible=%s required=%d.',
            json_encode($eligibleIds, JSON_THROW_ON_ERROR),
            (int)$approval->required_count,
        ));
    }

    $approvalManager = new \App\Services\WorkflowEngine\DefaultWorkflowApprovalManager();
    $responseResult = $approvalManager->recordResponse(
        (int)$approval->id,
        (int)$actor->id,
        'approve',
        'Scenario-owned response that must survive synchronization.',
    );
    if (!$responseResult->isSuccess()) {
        throw new \RuntimeException($responseResult->getError() ?? 'Could not record the preserved approval.');
    }
    $approval = $approvals->get((int)$approval->id);
    if (
        $approval->status !== \App\Model\Entity\WorkflowApproval::STATUS_PENDING
        || (int)$approval->approved_count !== 1
        || (int)$approval->required_count !== 2
    ) {
        throw new \RuntimeException('The one-of-two gate did not remain pending after its first approval.');
    }
    $response = $responses->find()
        ->where([
            'workflow_approval_id' => (int)$approval->id,
            'member_id' => (int)$actor->id,
        ])
        ->firstOrFail();
    if (
        $recommendation->bestowal_id !== null
        || $locator->get('Awards.Bestowals')->find()
            ->where(['primary_recommendation_id' => (int)$recommendation->id])
            ->count() !== 0
    ) {
        throw new \RuntimeException('A pending one-of-two approval must not create a bestowal before synchronization.');
    }

    $step->threshold_mode = \Awards\Model\Entity\ApprovalProcessStep::THRESHOLD_ANY;
    $step->required_count = null;
    $step->modified_by = (int)$actor->id;
    $locator->get('Awards.ApprovalProcessSteps')->saveOrFail($step);

    return [
        'token' => (string)$input['token'],
        'actorId' => (int)$actor->id,
        'secondApproverId' => (int)$secondApprover->id,
        'roleId' => (int)$role->id,
        'memberRoleIds' => $memberRoleIds,
        'processId' => (int)$process->id,
        'stepId' => (int)$step->id,
        'templateId' => (int)$template->id,
        'templateItemIds' => $templateItemIds,
        'awardId' => (int)$award->id,
        'recommendationId' => (int)$recommendation->id,
        'runId' => (int)$run->id,
        'workflowInstanceId' => (int)$run->workflow_instance_id,
        'approvalId' => (int)$approval->id,
        'responseId' => (int)$response->id,
        'responseRespondedAt' => $response->responded_at?->toAtomString(),
        'responseComment' => (string)$response->comment,
    ];
});

echo json_encode($result, JSON_THROW_ON_ERROR);
`;

const INSPECT_RECOMMENDATION_SYNC_PHP = String.raw`
require 'vendor/autoload.php';
require 'config/bootstrap.php';

$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$locator = \Cake\ORM\TableRegistry::getTableLocator();
$recommendation = $locator->get('Awards.Recommendations')->get((int)$input['recommendationId']);
$run = $locator->get('Awards.RecommendationApprovalRuns')->get((int)$input['runId']);
$instance = $locator->get('WorkflowInstances')->get((int)$input['workflowInstanceId']);
$approval = $locator->get('WorkflowApprovals')->get((int)$input['approvalId']);
$response = $locator->get('WorkflowApprovalResponses')->get((int)$input['responseId']);
$links = $locator->get('Awards.BestowalRecommendations')->find()
    ->where(['recommendation_id' => (int)$recommendation->id])
    ->orderByAsc('id')
    ->all();
$bestowalIds = array_values(array_unique(array_map(
    static fn($link): int => (int)$link->bestowal_id,
    $links->toList(),
)));
$primaryBestowalIds = $locator->get('Awards.Bestowals')->find()
    ->select(['id'])
    ->where(['primary_recommendation_id' => (int)$recommendation->id])
    ->all()
    ->extract('id')
    ->map(static fn($id): int => (int)$id)
    ->toList();
$bestowalIds = array_values(array_unique(array_merge($bestowalIds, $primaryBestowalIds)));
$bestowal = count($bestowalIds) === 1
    ? $locator->get('Awards.Bestowals')->get($bestowalIds[0])
    : null;

echo json_encode([
    'recommendationState' => (string)$recommendation->state,
    'recommendationBestowalId' => $recommendation->bestowal_id === null
        ? null
        : (int)$recommendation->bestowal_id,
    'runStatus' => (string)$run->status,
    'runTerminalReason' => $run->terminal_reason,
    'workflowStatus' => (string)$instance->status,
    'approvalStatus' => (string)$approval->status,
    'requiredCount' => (int)$approval->required_count,
    'approvedCount' => (int)$approval->approved_count,
    'responseId' => (int)$response->id,
    'responseMemberId' => (int)$response->member_id,
    'responseRespondedAt' => $response->responded_at?->toAtomString(),
    'responseComment' => (string)$response->comment,
    'responseCount' => $locator->get('WorkflowApprovalResponses')->find()
        ->where(['workflow_approval_id' => (int)$approval->id])
        ->count(),
    'bestowalCount' => count($bestowalIds),
    'bestowalId' => $bestowal === null ? null : (int)$bestowal->id,
    'bestowalLifecycleStatus' => $bestowal?->lifecycle_status,
    'bestowalGatheringId' => $bestowal?->gathering_id,
    'bestowalSourceRunId' => $bestowal?->source_approval_run_id === null
        ? null
        : (int)$bestowal->source_approval_run_id,
], JSON_THROW_ON_ERROR);
`;

const CHANGE_TODO_TEMPLATE_PHP = String.raw`
require 'vendor/autoload.php';
require 'config/bootstrap.php';

$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$locator = \Cake\ORM\TableRegistry::getTableLocator();
$items = $locator->get('ActionItems');
$logs = $locator->get('ActionItemLogs');
$templateItems = $locator->get('Awards.BestowalTodoTemplateItems');
$recommendation = $locator->get('Awards.Recommendations')->get((int)$input['recommendationId']);
$bestowalId = (int)$recommendation->bestowal_id;
if ($bestowalId <= 0) {
    throw new \RuntimeException('The synchronized recommendation does not have a bestowal.');
}
$connection = $items->getConnection();
$connection->enableSavePoints();
$result = $connection->transactional(function () use (
    $input,
    $items,
    $logs,
    $templateItems,
    $bestowalId,
): array {
    $shared = $items->find()->where([
        'entity_type' => \Awards\Model\Entity\Bestowal::ACTION_ITEM_ENTITY_TYPE,
        'entity_id' => $bestowalId,
        'source_ref' => 'scroll_assigned',
    ])->firstOrFail();
    $removed = $items->find()->where([
        'entity_type' => \Awards\Model\Entity\Bestowal::ACTION_ITEM_ENTITY_TYPE,
        'entity_id' => $bestowalId,
        'source_ref' => 'scroll_finished',
    ])->firstOrFail();

    $completion = (new \App\Services\ActionItems\ActionItemService())->complete(
        (int)$shared->id,
        (int)$input['actorId'],
        'Scenario completion retained across definition synchronization.',
        false,
    );
    if (!$completion->isSuccess()) {
        throw new \RuntimeException($completion->getError() ?? 'Could not complete the shared to-do.');
    }
    $shared = $items->get((int)$shared->id);
    $completionLog = $logs->find()
        ->where(['action_item_id' => (int)$shared->id])
        ->orderByDesc('id')
        ->firstOrFail();

    $sharedDefinition = $templateItems->get((int)$input['templateItemIds']['scroll_assigned']);
    $sharedDefinition->label = 'Scroll assigned after sync';
    $sharedDefinition->description = 'Updated shared definition snapshot.';
    $sharedDefinition->assignee_source_id = (int)$input['secondApproverId'];
    $sharedDefinition->is_gating = false;
    $sharedDefinition->sort_order = 1;
    $sharedDefinition->modified_by = (int)$input['actorId'];
    $templateItems->saveOrFail($sharedDefinition);

    $removedDefinition = $templateItems->get((int)$input['templateItemIds']['scroll_finished']);
    $templateItems->deleteOrFail($removedDefinition);

    $newDefinition = $templateItems->saveOrFail($templateItems->newEntity([
        'template_id' => (int)$input['templateId'],
        'item_key' => 'herald_ready',
        'label' => 'Herald ready after sync',
        'description' => 'New work introduced by the current template.',
        'assignee_type' => \Awards\Model\Entity\BestowalTodoTemplateItem::ASSIGNEE_TYPE_MEMBER,
        'assignee_source_id' => (int)$input['actorId'],
        'branch_mode' => \Awards\Model\Entity\BestowalTodoTemplateItem::BRANCH_MODE_AWARD,
        'is_gating' => true,
        'sort_order' => 0,
    ]));

    return [
        'bestowalId' => $bestowalId,
        'sharedActionItemId' => (int)$shared->id,
        'sharedCompletedAt' => $shared->completed_at?->toAtomString(),
        'sharedCompletedBy' => (int)$shared->completed_by,
        'sharedCompletionLogId' => (int)$completionLog->id,
        'sharedLogCount' => $logs->find()->where(['action_item_id' => (int)$shared->id])->count(),
        'removedActionItemId' => (int)$removed->id,
        'removedTemplateItemId' => (int)$removedDefinition->id,
        'newTemplateItemId' => (int)$newDefinition->id,
    ];
});

echo json_encode($result, JSON_THROW_ON_ERROR);
`;

const INSPECT_TODO_SYNC_PHP = String.raw`
require 'vendor/autoload.php';
require 'config/bootstrap.php';

$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$locator = \Cake\ORM\TableRegistry::getTableLocator();
$items = $locator->get('ActionItems');
$logs = $locator->get('ActionItemLogs');
$bestowal = $locator->get('Awards.Bestowals')->get((int)$input['bestowalId']);
$rows = $items->find()->where([
    'entity_type' => \Awards\Model\Entity\Bestowal::ACTION_ITEM_ENTITY_TYPE,
    'entity_id' => (int)$bestowal->id,
])->orderByAsc('id')->all();
$byRef = [];
foreach ($rows as $row) {
    $latestLog = $logs->find()
        ->where(['action_item_id' => (int)$row->id])
        ->orderByDesc('id')
        ->first();
    $byRef[(string)$row->source_ref] = [
        'id' => (int)$row->id,
        'title' => (string)$row->title,
        'status' => (string)$row->status,
        'completedAt' => $row->completed_at?->toAtomString(),
        'completedBy' => $row->completed_by === null ? null : (int)$row->completed_by,
        'isGating' => (bool)$row->is_gating,
        'latestLogId' => $latestLog === null ? null : (int)$latestLog->id,
        'latestLogNote' => $latestLog?->note,
        'logCount' => $logs->find()->where(['action_item_id' => (int)$row->id])->count(),
    ];
}
$countsByRef = [];
foreach (['scroll_assigned', 'scroll_finished', 'herald_ready'] as $sourceRef) {
    $countsByRef[$sourceRef] = $items->find()->where([
        'entity_type' => \Awards\Model\Entity\Bestowal::ACTION_ITEM_ENTITY_TYPE,
        'entity_id' => (int)$bestowal->id,
        'source_ref' => $sourceRef,
    ])->count();
}

echo json_encode([
    'bestowalLifecycleStatus' => (string)$bestowal->lifecycle_status,
    'items' => $byRef,
    'countsByRef' => $countsByRef,
], JSON_THROW_ON_ERROR);
`;

const RESTORE_TODO_DEFINITION_PHP = String.raw`
require 'vendor/autoload.php';
require 'config/bootstrap.php';

$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$items = \Cake\ORM\TableRegistry::getTableLocator()->get('Awards.BestowalTodoTemplateItems');
$item = $items->find('withTrashed')
    ->where(['id' => (int)$input['removedTemplateItemId']])
    ->firstOrFail();
/** @var \Muffin\Trash\Model\Behavior\TrashBehavior $trash */
$trash = $items->getBehavior('Trash');
if ($trash->restoreTrash($item) === false) {
    throw new \RuntimeException('Could not restore the removed to-do definition.');
}

echo json_encode(['restoredTemplateItemId' => (int)$item->id], JSON_THROW_ON_ERROR);
`;

const CLEANUP_SYNC_FIXTURE_PHP = String.raw`
require 'vendor/autoload.php';
require 'config/bootstrap.php';

$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$connection = \Cake\ORM\TableRegistry::getTableLocator()->get('Awards.Recommendations')->getConnection();
$connection->enableSavePoints();
$connection->transactional(function () use ($connection, $input): void {
    $recommendationId = (int)($input['recommendationId'] ?? 0);
    $awardId = (int)($input['awardId'] ?? 0);
    $processId = (int)($input['processId'] ?? 0);
    $templateId = (int)($input['templateId'] ?? 0);
    $roleId = (int)($input['roleId'] ?? 0);

    $bestowalIds = array_values(array_unique(array_filter(array_map(
        'intval',
        [(int)($input['bestowalId'] ?? 0)],
    ))));
    if ($recommendationId > 0) {
        $linkedBestowals = $connection->execute(
            'SELECT bestowal_id AS id FROM awards_bestowal_recommendations WHERE recommendation_id = :id '
            . 'UNION SELECT id FROM awards_bestowals WHERE primary_recommendation_id = :id',
            ['id' => $recommendationId],
        )->fetchAll('assoc');
        foreach ($linkedBestowals as $linkedBestowal) {
            $bestowalIds[] = (int)$linkedBestowal['id'];
        }
        $bestowalIds = array_values(array_unique(array_filter($bestowalIds)));
    }
    foreach ($bestowalIds as $bestowalId) {
        $connection->execute(
            'DELETE FROM action_items WHERE entity_type = :entityType AND entity_id = :entityId',
            ['entityType' => 'Awards.Bestowals', 'entityId' => $bestowalId],
        );
        $connection->execute('DELETE FROM awards_bestowals WHERE id = :id', ['id' => $bestowalId]);
    }

    $runRows = $recommendationId > 0
        ? $connection->execute(
            'SELECT id, workflow_instance_id FROM awards_recommendation_approval_runs '
            . 'WHERE recommendation_id = :id',
            ['id' => $recommendationId],
        )->fetchAll('assoc')
        : [];
    $workflowInstanceIds = array_values(array_unique(array_filter(array_map(
        'intval',
        [(int)($input['workflowInstanceId'] ?? 0)],
    ))));
    foreach ($runRows as $runRow) {
        $workflowInstanceIds[] = (int)$runRow['workflow_instance_id'];
    }
    $workflowInstanceIds = array_values(array_unique(array_filter($workflowInstanceIds)));
    if ($recommendationId > 0) {
        $connection->execute(
            'DELETE FROM awards_recommendation_approval_runs WHERE recommendation_id = :id',
            ['id' => $recommendationId],
        );
    }
    if ($recommendationId > 0) {
        $connection->execute(
            'DELETE FROM awards_recommendations_events WHERE recommendation_id = :id',
            ['id' => $recommendationId],
        );
        $connection->execute(
            'DELETE FROM awards_recommendations_states_logs WHERE recommendation_id = :id',
            ['id' => $recommendationId],
        );
        $connection->execute('DELETE FROM awards_recommendations WHERE id = :id', ['id' => $recommendationId]);
    }
    foreach ($workflowInstanceIds as $workflowInstanceId) {
        $connection->execute('DELETE FROM workflow_instances WHERE id = :id', ['id' => $workflowInstanceId]);
    }
    if ($awardId > 0) {
        $connection->execute('DELETE FROM awards_awards WHERE id = :id', ['id' => $awardId]);
    }
    if ($processId > 0) {
        $connection->execute('DELETE FROM awards_approval_processes WHERE id = :id', ['id' => $processId]);
    }
    if ($templateId > 0) {
        $connection->execute('DELETE FROM awards_bestowal_todo_templates WHERE id = :id', ['id' => $templateId]);
    }
    if ($roleId > 0) {
        $connection->execute('DELETE FROM member_roles WHERE role_id = :id', ['id' => $roleId]);
        $connection->execute('DELETE FROM roles WHERE id = :id', ['id' => $roleId]);
    }
});

echo json_encode(['deleted' => true], JSON_THROW_ON_ERROR);
`;

const syncFixture = (page) => {
    if (!page.__awardWorkflowSyncFixture) {
        throw new Error('The award workflow synchronization fixture has not been created.');
    }

    return page.__awardWorkflowSyncFixture;
};

const openSyncPage = async (page, path, heading) => {
    await page.goto(path, { waitUntil: 'domcontentloaded' });
    await waitForPageBody(page);
    await expect(page.getByRole('heading', { name: heading, exact: true })).toBeVisible();
};

const openSyncConfirmation = async (page, controlName, title, descriptionFragment) => {
    const trigger = page.getByRole('button', { name: controlName, exact: true });
    await trigger.focus();
    await expect(trigger).toBeFocused();
    await trigger.press('Enter');

    const dialog = page.getByRole('dialog', { name: title, exact: true });
    await expect(dialog).toBeVisible();
    page.__awardWorkflowSyncDialog = {
        controlName,
        title,
        descriptionFragment,
    };
};

const assertAccessibleSyncConfirmation = async (page) => {
    const dialogFixture = page.__awardWorkflowSyncDialog;
    if (!dialogFixture) {
        throw new Error('No synchronization confirmation is open.');
    }
    const dialog = page.getByRole('dialog', { name: dialogFixture.title, exact: true });
    await expect(dialog).toHaveAttribute('role', 'dialog');
    await expect(dialog).toHaveAttribute('aria-modal', 'true');
    await expect(dialog).toHaveAttribute('aria-labelledby', 'kmp-a11y-dialog-title');
    await expect(dialog).toHaveAttribute('aria-describedby', 'kmp-a11y-dialog-message');
    await expect(dialog.locator('#kmp-a11y-dialog-title')).toHaveText(dialogFixture.title);
    await expect(dialog.locator('#kmp-a11y-dialog-message')).toContainText(dialogFixture.descriptionFragment);
    await expect(dialog.getByRole('button', { name: 'Sync Now', exact: true })).toBeFocused();
};

const submitSyncConfirmation = async (page, controlName, title, postPath, expectedZeroFailureCount) => {
    await openSyncConfirmation(page, controlName, title, 'Synchronize');
    const dialog = page.getByRole('dialog', { name: title, exact: true });
    const postResponsePromise = page.waitForResponse((response) => {
        const url = new URL(response.url());
        return response.request().method() === 'POST' && url.pathname === postPath;
    }, { timeout: 120000 });
    const navigationPromise = page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 120000 });
    const [postResponse] = await Promise.all([
        postResponsePromise,
        navigationPromise,
        dialog.getByRole('button', { name: 'Sync Now', exact: true }).press('Enter'),
    ]);
    expect([200, 302, 303]).toContain(postResponse.status());
    await waitForPageBody(page, 30000);
    const flash = page.getByRole('alert').first();
    await expect(flash).toHaveClass(/alert-success/);
    const flashText = await flash.textContent();
    expect(flashText?.match(/\b0 failed\b/g) ?? []).toHaveLength(expectedZeroFailureCount);
};

const inspectRecommendationSync = (page) => {
    const fixture = syncFixture(page);

    return runPhpJson(INSPECT_RECOMMENDATION_SYNC_PHP, fixture);
};

const assertRecommendationCompleted = (page, state) => {
    const fixture = syncFixture(page);
    expect(state.approvalStatus).toBe('approved');
    expect(state.requiredCount).toBe(1);
    expect(state.approvedCount).toBe(1);
    expect(state.responseId).toBe(fixture.responseId);
    expect(state.responseMemberId).toBe(fixture.actorId);
    expect(state.responseRespondedAt).toBe(fixture.responseRespondedAt);
    expect(state.responseComment).toBe(fixture.responseComment);
    expect(state.responseCount).toBe(1);
    expect(state.workflowStatus).toBe('completed');
    expect(state.runStatus).toBe('consumed');
    expect(state.runTerminalReason).toBe('consumed_by_bestowal');
    expect(state.recommendationState).toBe('Need to Schedule');
};

const inspectTodoSync = (page) => {
    const fixture = syncFixture(page);

    return runPhpJson(INSPECT_TODO_SYNC_PHP, {
        bestowalId: fixture.bestowalId,
    });
};

Given('I create an in-flight award workflow synchronization fixture', async ({ page }) => {
    const token = `${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
    page.__awardWorkflowSyncFixture = runPhpJson(SETUP_SYNC_FIXTURE_PHP, { token }, { timeoutMs: 120000 });
});

When('I open the award approval process synchronization page', async ({ page }) => {
    await openSyncPage(page, RECOMMENDATION_SYNC_PATH, 'Award Approval Processes');
});

When('I open the recommendation synchronization confirmation with the keyboard', async ({ page }) => {
    await openSyncConfirmation(
        page,
        RECOMMENDATION_SYNC_CONTROL,
        'Synchronize open recommendations',
        'Approval requirements may change.',
    );
});

Then('the recommendation synchronization confirmation should be accessible and initially focused', async ({ page }) => {
    await assertAccessibleSyncConfirmation(page);
});

When('I open the bestowal to-do synchronization page', async ({ page }) => {
    await openSyncPage(page, BESTOWAL_SYNC_PATH, 'Bestowal To-Do Templates');
});

When('I open the bestowal synchronization confirmation with the keyboard', async ({ page }) => {
    await openSyncConfirmation(
        page,
        BESTOWAL_SYNC_CONTROL,
        'Synchronize open bestowals',
        'To-dos may be added, updated, reopened, or cancelled.',
    );
});

Then('the bestowal synchronization confirmation should be accessible and initially focused', async ({ page }) => {
    await assertAccessibleSyncConfirmation(page);
});

When('I dismiss the synchronization confirmation with Escape', async ({ page }) => {
    const dialogFixture = page.__awardWorkflowSyncDialog;
    if (!dialogFixture) {
        throw new Error('No synchronization confirmation is open.');
    }
    const dialog = page.getByRole('dialog', { name: dialogFixture.title, exact: true });
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
});

Then('focus should return to the recommendation synchronization control', async ({ page }) => {
    await expect(page.getByRole('button', { name: RECOMMENDATION_SYNC_CONTROL, exact: true })).toBeFocused();
});

Then('focus should return to the bestowal synchronization control', async ({ page }) => {
    await expect(page.getByRole('button', { name: BESTOWAL_SYNC_CONTROL, exact: true })).toBeFocused();
});

When('I confirm recommendation synchronization with the keyboard', async ({ page }) => {
    await submitSyncConfirmation(
        page,
        RECOMMENDATION_SYNC_CONTROL,
        'Synchronize open recommendations',
        RECOMMENDATION_SYNC_POST_PATH,
        2,
    );
});

Then('the in-flight recommendation should complete from its preserved approval', async ({ page }) => {
    const state = inspectRecommendationSync(page);
    assertRecommendationCompleted(page, state);
    page.__awardWorkflowSyncFixture.firstRecommendationSyncState = state;
});

Then('recommendation synchronization should create exactly one open unscheduled bestowal', async ({ page }) => {
    const fixture = syncFixture(page);
    const state = fixture.firstRecommendationSyncState ?? inspectRecommendationSync(page);
    expect(state.bestowalCount).toBe(1);
    expect(state.bestowalId).toBeGreaterThan(0);
    expect(state.recommendationBestowalId).toBe(state.bestowalId);
    expect(state.bestowalLifecycleStatus).toBe('open');
    expect(state.bestowalGatheringId).toBeNull();
    expect(state.bestowalSourceRunId).toBe(fixture.runId);
    fixture.bestowalId = state.bestowalId;
});

When('I synchronize the open recommendations again', async ({ page }) => {
    await submitSyncConfirmation(
        page,
        RECOMMENDATION_SYNC_CONTROL,
        'Synchronize open recommendations',
        RECOMMENDATION_SYNC_POST_PATH,
        2,
    );
});

Then('the recommendation synchronization should be idempotent', async ({ page }) => {
    const fixture = syncFixture(page);
    const state = inspectRecommendationSync(page);
    assertRecommendationCompleted(page, state);
    expect(state.bestowalId).toBe(fixture.bestowalId);
    expect(state.bestowalCount).toBe(1);
    expect(state.bestowalLifecycleStatus).toBe('open');
    expect(state.bestowalGatheringId).toBeNull();
});

Given("I change the fixture's current bestowal to-do template", async ({ page }) => {
    const fixture = syncFixture(page);
    const changed = runPhpJson(CHANGE_TODO_TEMPLATE_PHP, fixture);
    Object.assign(fixture, changed);
});

When('I confirm bestowal synchronization with the keyboard', async ({ page }) => {
    await submitSyncConfirmation(
        page,
        BESTOWAL_SYNC_CONTROL,
        'Synchronize open bestowals',
        BESTOWAL_SYNC_POST_PATH,
        1,
    );
});

Then('the current to-do definition should preserve completion audit and retire removed work', async ({ page }) => {
    const fixture = syncFixture(page);
    const state = inspectTodoSync(page);
    const shared = state.items.scroll_assigned;
    const removed = state.items.scroll_finished;
    const added = state.items.herald_ready;

    expect(shared.id).toBe(fixture.sharedActionItemId);
    expect(shared.title).toBe('Scroll assigned after sync');
    expect(shared.status).toBe('completed');
    expect(shared.completedAt).toBe(fixture.sharedCompletedAt);
    expect(shared.completedBy).toBe(fixture.sharedCompletedBy);
    expect(shared.latestLogId).toBe(fixture.sharedCompletionLogId);
    expect(shared.logCount).toBe(fixture.sharedLogCount);
    expect(shared.isGating).toBe(false);
    expect(state.countsByRef.scroll_assigned).toBe(1);

    expect(removed.id).toBe(fixture.removedActionItemId);
    expect(removed.status).toBe('cancelled');
    expect(removed.latestLogNote).toBe(
        'Cancelled automatically because this to-do is no longer in the current workflow definition.',
    );
    expect(state.countsByRef.scroll_finished).toBe(1);
    fixture.removedCancelledLogCount = removed.logCount;

    expect(added.id).toBeGreaterThan(0);
    expect(added.title).toBe('Herald ready after sync');
    expect(added.status).toBe('open');
    expect(state.countsByRef.herald_ready).toBe(1);
});

Then('the bestowal should remain open', async ({ page }) => {
    expect(inspectTodoSync(page).bestowalLifecycleStatus).toBe('open');
});

Given('I restore the removed fixture to-do definition', async ({ page }) => {
    const fixture = syncFixture(page);
    runPhpJson(RESTORE_TODO_DEFINITION_PHP, {
        removedTemplateItemId: fixture.removedTemplateItemId,
    });
});

When('I synchronize the open bestowals again', async ({ page }) => {
    await submitSyncConfirmation(
        page,
        BESTOWAL_SYNC_CONTROL,
        'Synchronize open bestowals',
        BESTOWAL_SYNC_POST_PATH,
        1,
    );
});

Then('the restored to-do should reopen without a duplicate', async ({ page }) => {
    const fixture = syncFixture(page);
    const state = inspectTodoSync(page);
    const restored = state.items.scroll_finished;
    expect(restored.id).toBe(fixture.removedActionItemId);
    expect(restored.status).toBe('open');
    expect(restored.latestLogNote).toBe(
        'Reopened automatically because this to-do returned to the current workflow definition.',
    );
    expect(restored.logCount).toBe(fixture.removedCancelledLogCount + 1);
    expect(state.countsByRef.scroll_finished).toBe(1);
});

After(async ({ page }) => {
    const fixture = page.__awardWorkflowSyncFixture;
    if (!fixture) {
        return;
    }

    runPhpJson(CLEANUP_SYNC_FIXTURE_PHP, fixture, { timeoutMs: 120000 });
});
