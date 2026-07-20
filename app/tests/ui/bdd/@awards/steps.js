const { createBdd } = require('playwright-bdd');
const { expect } = require('@playwright/test');
const { execFileSync } = require('node:child_process');
const path = require('node:path');
const {
    waitForTurboStreamResponse,
    assertUrlContainsQuery,
    assertGridShellPreserved,
    waitForGridStateJson,
    waitForPageBody,
    flushWorkflowsAndQueue,
    waitForQueueSettled,
    waitForGridRows,
    waitForStableMailpitSearchTotal,
} = require('../../support/ui-helpers.cjs');

const { Given, When, Then, After } = createBdd();

const APP_ROOT = path.resolve(__dirname, '../../../..');
const REPO_ROOT = path.resolve(APP_ROOT, '..');
const { getMailpitApiUrl, shouldUseDockerPhp } = require('../../support/test-environment.cjs');
const FIXTURE_MEMBER_NAME = 'Iris Basic User Demoer';
const FIXTURE_REQUESTER_EMAIL = 'forest@ampdemo.com';
const FEEDBACK_RECIPIENT_NAME = 'Bryce Local Seneschal Demoer';
const GRID_ROWS_SELECTOR = 'table.table tbody tr:visible, .dataTable tbody tr:visible';
const APPROVAL_PROCESS_NAMES = {
    singleCrown: 'Single Approver - Crown',
    singleLocal: 'Single Approver - Local',
    localThenCrown: 'Dual Approver - Local then Crown',
};

const scopedMailpitMessageCount = async (page, query) => {
    const response = await page.request.get(getMailpitApiUrl('api/v1/search'), {
        params: { query },
    });
    if (!response.ok()) {
        return 0;
    }

    const data = await response.json();
    return data.messages_count ?? 0;
};

const waitForScopedMailpitMessageCount = async (page, query, minCount = 1) => {
    flushWorkflowsAndQueue();
    await waitForQueueSettled();
    await expect.poll(async () => scopedMailpitMessageCount(page, query), {
        timeout: 30000,
    }).toBeGreaterThanOrEqual(minCount);
};

const publicFeedbackFixture = (page) => {
    const fixture = page.__awardPublicFeedbackFixture;
    if (!fixture) {
        throw new Error('The public feedback-lane recommendation has not been submitted yet.');
    }

    return fixture;
};

const publicFeedbackSubmissionSubject = (page) => {
    const fixture = publicFeedbackFixture(page);

    return `Award Recommendation: ${fixture.awardName} for ${fixture.recipient}`;
};

const ensureFeedbackRequesterCanManageRecommendations = () => {
    runPhpJson(`
require 'vendor/autoload.php';
require 'config/bootstrap.php';

$locator = \\Cake\\ORM\\TableRegistry::getTableLocator();
$members = $locator->get('Members');
$roles = $locator->get('Roles');
$memberRoles = $locator->get('MemberRoles');
$officers = $locator->get('Officers.Officers');

$member = $members->find()
    ->select(['id', 'branch_id'])
    ->where(['email_address' => '${FIXTURE_REQUESTER_EMAIL}'])
    ->firstOrFail();
$role = $roles->find()
    ->select(['id'])
    ->where(['name' => 'Ansteorran Crown'])
    ->firstOrFail();

$memberRole = $memberRoles->find()
    ->where([
        'member_id' => (int)$member->id,
        'role_id' => (int)$role->id,
        'revoker_id IS' => null,
    ])
    ->orderByDesc('id')
    ->first();

if ($memberRole === null) {
    $memberRole = $memberRoles->newEntity([
        'member_id' => (int)$member->id,
        'role_id' => (int)$role->id,
        'branch_id' => (int)$member->branch_id,
        'created_by' => (int)$member->id,
        'modified_by' => (int)$member->id,
    ]);
}

$memberRole->start_on = new \\Cake\\I18n\\DateTime('-1 day');
$memberRole->expires_on = new \\Cake\\I18n\\DateTime('+1 year');
$memberRole->modified_by = (int)$member->id;
$memberRoles->saveOrFail($memberRole);

$officer = $officers->find()
    ->where(['granted_member_role_id' => (int)$memberRole->id])
    ->orderByDesc('id')
    ->first();
if ($officer !== null) {
    $officer->status = 'Current';
    $officer->start_on = new \\Cake\\I18n\\DateTime('-1 day');
    $officer->expires_on = new \\Cake\\I18n\\DateTime('+1 year');
    $officer->modified_by = (int)$member->id;
    $officers->saveOrFail($officer);
}

foreach (['member_permissions', 'permissions_structure'] as $cacheConfig) {
    if (\\Cake\\Cache\\Cache::getConfig($cacheConfig) !== null) {
        \\Cake\\Cache\\Cache::clear($cacheConfig);
    }
}

echo json_encode(['memberRoleId' => (int)$memberRole->id], JSON_THROW_ON_ERROR);
`);
};

const FIXTURE_SETS = {
    'detail edit': [
        { name: 'detail', awardName: 'Award of Arms' },
    ],
    'grid edit': [
        { name: 'quick', awardName: 'Award of Amicitia of Ansteorra' },
    ],
    'bulk edit': [
        { name: 'bulk-one', awardName: 'Award of Arms' },
        { name: 'bulk-two', awardName: 'Award of the Compass Rose of Ansteorra' },
    ],
    grouping: [
        { name: 'group-head', awardName: 'Award of Arms' },
        { name: 'group-one', awardName: 'Award of Amicitia of Ansteorra' },
        { name: 'group-two', awardName: 'Award of the Compass Rose of Ansteorra' },
        { name: 'group-three', awardName: 'Award of the Golden Bridge of Ansteorra' },
    ],
    'workflow single crown': [
        { name: 'wf-crown', processName: APPROVAL_PROCESS_NAMES.singleCrown },
    ],
    'workflow single local': [
        { name: 'wf-local', processName: APPROVAL_PROCESS_NAMES.singleLocal },
    ],
    'workflow local then crown': [
        { name: 'wf-dual', processName: APPROVAL_PROCESS_NAMES.localThenCrown },
    ],
    'workflow grouping': [
        { name: 'wf-group-head', processName: APPROVAL_PROCESS_NAMES.singleLocal },
        { name: 'wf-group-one', processName: APPROVAL_PROCESS_NAMES.singleLocal },
        { name: 'wf-group-two', processName: APPROVAL_PROCESS_NAMES.singleLocal },
    ],
    'workflow multi-award local': [
        { name: 'wf-award-a', processName: APPROVAL_PROCESS_NAMES.singleLocal },
        { name: 'wf-award-b', processName: APPROVAL_PROCESS_NAMES.singleLocal },
    ],
};

const CREATE_FIXTURES_PHP = `
require 'vendor/autoload.php';
require 'config/bootstrap.php';

\\Cake\\Core\\Configure::write('Queue.plugins', array_values(array_unique(array_merge(
    (array)\\Cake\\Core\\Configure::read('Queue.plugins'),
    ['Queue'],
))));
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$locator = \\Cake\\ORM\\TableRegistry::getTableLocator();
$recommendations = $locator->get('Awards.Recommendations');
$members = $locator->get('Members');
$awards = $locator->get('Awards.Awards');
$approvalProcesses = $locator->get('Awards.ApprovalProcesses');
$approvalResolver = new \\Awards\\Services\\AwardApprovalResolverService();
$container = new \\Cake\\Core\\Container();
\\Awards\\Services\\AwardsWorkflowProvider::register();
\\App\\Services\\WorkflowRegistry\\WorkflowActionRegistry::register('Core', [
    [
        'action' => 'Core.SendEmail',
        'label' => 'Send Email',
        'description' => 'Send an email notification using a configured template',
        'inputSchema' => [
            'to' => ['type' => 'string', 'label' => 'Recipient Email', 'required' => true],
            'template' => ['type' => 'emailTemplate', 'label' => 'Email Template', 'required' => true],
            'vars' => ['type' => 'object', 'label' => 'Template Variables'],
            'replyTo' => ['type' => 'string', 'label' => 'Reply-To Email'],
        ],
        'outputSchema' => [
            'sent' => ['type' => 'boolean', 'label' => 'Email Sent'],
        ],
        'serviceClass' => \\App\\Services\\WorkflowEngine\\Actions\\CoreActions::class,
        'serviceMethod' => 'sendEmail',
        'isAsync' => false,
    ],
]);
\\App\\Services\\WorkflowRegistry\\WorkflowConditionRegistry::register('Core', [
    [
        'condition' => 'Core.FieldEquals',
        'label' => 'Field Equals Value',
        'description' => 'Check if a context field equals a specific value',
        'inputSchema' => [
            'field' => ['type' => 'string', 'label' => 'Field Path', 'required' => true],
            'value' => ['type' => 'mixed', 'label' => 'Expected Value', 'required' => true],
        ],
        'evaluatorClass' => \\App\\Services\\WorkflowEngine\\Conditions\\CoreConditions::class,
        'evaluatorMethod' => 'fieldEquals',
    ],
]);
$container->add(
    \\App\\Services\\ActiveWindowManager\\ActiveWindowManagerInterface::class,
    \\App\\Services\\ActiveWindowManager\\DefaultActiveWindowManager::class,
);
$container->add(\\App\\Services\\WorkflowEngine\\ExpressionEvaluator::class);
$container->add(\\App\\Services\\WorkflowEngine\\Actions\\CoreActions::class)
    ->addArguments([
        \\App\\Services\\ActiveWindowManager\\ActiveWindowManagerInterface::class,
        \\App\\Services\\WorkflowEngine\\ExpressionEvaluator::class,
    ]);
$container->add(\\App\\Services\\WorkflowEngine\\Conditions\\CoreConditions::class)
    ->addArgument(\\App\\Services\\WorkflowEngine\\ExpressionEvaluator::class);
$container->add(\\App\\Services\\WorkflowEngine\\StateMachine\\StateMachineHandler::class);
$container->add(\\Awards\\Services\\AwardsWorkflowActions::class);
$container->add(\\Awards\\Services\\AwardsWorkflowConditions::class);
$workflowEngine = new \\App\\Services\\WorkflowEngine\\DefaultWorkflowEngine($container);
$triggerDispatcher = new \\App\\Services\\WorkflowEngine\\TriggerDispatcher($workflowEngine);
$extractRecommendationIdFromDispatch = static function (array $dispatchResults): ?int {
    foreach ($dispatchResults as $dispatchResult) {
        if (!$dispatchResult instanceof \\App\\Services\\ServiceResult) {
            continue;
        }
        if (!$dispatchResult->isSuccess()) {
            continue;
        }

        $dispatchData = $dispatchResult->getData();
        if (!is_array($dispatchData)) {
            continue;
        }

        $workflowResult = $dispatchData['workflowResult'] ?? null;
        if (!is_array($workflowResult)) {
            continue;
        }

        $recommendationId = $workflowResult['data']['recommendationId']
            ?? $workflowResult['recommendationId']
            ?? null;
        if (is_numeric($recommendationId)) {
            return (int)$recommendationId;
        }
    }

    return null;
};
$ensureOfficeApproversCurrent = static function ($process) use ($locator): void {
    $officers = $locator->get('Officers.Officers');
    $now = \\Cake\\I18n\\DateTime::now();
    foreach ($process->approval_process_steps ?? [] as $step) {
        if ((string)$step->approver_type !== 'office' || empty($step->approver_source_id)) {
            continue;
        }

        $officeId = (int)$step->approver_source_id;
        $currentExists = $officers->find()
            ->where([
                'office_id' => $officeId,
                'status' => 'Current',
                'start_on <=' => $now,
                'OR' => [
                    'expires_on IS' => null,
                    'expires_on >=' => $now,
                ],
            ])
            ->count() > 0;
        if ($currentExists) {
            continue;
        }

        $officer = $officers->find()
            ->where(['office_id' => $officeId])
            ->orderByDesc('id')
            ->first();
        if ($officer === null) {
            continue;
        }

        $officer->status = 'Current';
        $officer->start_on = $now->subDays(1);
        $officer->expires_on = $now->addYears(1);
        $officers->saveOrFail($officer);
    }
};

$requester = $members->find()
    ->select(['id', 'sca_name', 'email_address', 'phone_number'])
    ->where(['email_address' => $input['requesterEmail']])
    ->firstOrFail();
$member = $members->find()
    ->select(['id', 'public_id', 'sca_name'])
    ->where(['sca_name' => $input['memberName']])
    ->firstOrFail();

$awardNames = array_values(array_filter(array_unique(array_map(
    static fn(array $fixture): ?string => $fixture['awardName'] ?? null,
    $input['fixtures'],
))));
$awardRows = $awardNames === []
    ? []
    : $awards->find()
        ->select(['id', 'name', 'approval_process_id', 'branch_id'])
        ->where(['name IN' => $awardNames])
        ->all()
        ->indexBy('name')
        ->toArray();

$created = [];
$usedAwardIdsByProcess = [];

foreach ($input['fixtures'] as $fixture) {
    $award = null;
    $processName = isset($fixture['processName']) ? (string)$fixture['processName'] : '';
    if ($processName !== '') {
        $process = $approvalProcesses->find()
            ->contain(['ApprovalProcessSteps'])
            ->where([
                'ApprovalProcesses.name' => $processName,
                'ApprovalProcesses.is_active' => true,
                'ApprovalProcesses.deleted IS' => null,
            ])
            ->first();
        if ($process === null) {
            throw new \\RuntimeException('Approval process not found: ' . $processName);
        }
        $ensureOfficeApproversCurrent($process);

        $candidates = $awards->find()
            ->select(['id', 'name', 'approval_process_id', 'branch_id'])
            ->where([
                'Awards.approval_process_id' => (int)$process->id,
                'Awards.deleted IS' => null,
                'Awards.is_active' => true,
            ])
            ->orderByAsc('Awards.id')
            ->all();

        $usedForProcess = $usedAwardIdsByProcess[$processName] ?? [];
        $orderedCandidates = [];
        foreach ($candidates as $candidate) {
            if (!in_array((int)$candidate->id, $usedForProcess, true)) {
                $orderedCandidates[] = $candidate;
            }
        }
        foreach ($candidates as $candidate) {
            if (in_array((int)$candidate->id, $usedForProcess, true)) {
                $orderedCandidates[] = $candidate;
            }
        }

        foreach ($orderedCandidates as $candidate) {
            $preview = $approvalResolver->previewProcess($process, $candidate);
            $isUsable = $preview !== [];
            foreach ($preview as $stepPreview) {
                if ($stepPreview['error'] !== null || empty($stepPreview['members'])) {
                    $isUsable = false;
                    break;
                }
            }
            if ($isUsable) {
                $award = $candidate;
                $usedAwardIdsByProcess[$processName] = array_values(array_unique(array_merge(
                    $usedForProcess,
                    [(int)$candidate->id],
                )));
                break;
            }
        }

        if ($award === null) {
            throw new \\RuntimeException('No usable award found for process: ' . $processName);
        }
    } else {
        $award = $awardRows[$fixture['awardName']] ?? null;
        if ($award === null) {
            throw new \\RuntimeException('Award not found: ' . $fixture['awardName']);
        }
    }

    $reasonToken = (string)$fixture['reason'] . ' [' . uniqid('wf-', true) . ']';
    $dispatchResults = $triggerDispatcher->dispatch(
        'Awards.RecommendationCreateRequested',
        [
            'data' => [
                'award_id' => (int)$award->id,
                'member_sca_name' => (string)$member->sca_name,
                'member_public_id' => (string)$member->public_id,
                'reason' => $reasonToken,
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
        ],
        (int)$requester->id,
    );
    if ($dispatchResults === []) {
        throw new \\RuntimeException('Fixture creation workflow dispatch returned no results.');
    }
    foreach ($dispatchResults as $dispatchResult) {
        if ($dispatchResult instanceof \\App\\Services\\ServiceResult && !$dispatchResult->isSuccess()) {
            throw new \\RuntimeException(
                'Fixture creation workflow dispatch failed: ' . ($dispatchResult->getError() ?? 'unknown error')
            );
        }
    }

    $recommendationId = $extractRecommendationIdFromDispatch($dispatchResults);

    $recommendation = $recommendationId === null
        ? null
        : $recommendations->find()
            ->where(['id' => $recommendationId])
            ->first();
    if ($recommendation === null) {
        $recommendation = $recommendations->find()
            ->where([
                'award_id' => (int)$award->id,
                'member_id' => (int)$member->id,
                'requester_id' => (int)$requester->id,
                'reason' => $reasonToken,
            ])
            ->orderByDesc('id')
            ->first();
    }
    if ($recommendation === null) {
        throw new \\RuntimeException('Fixture creation did not persist recommendation for reason token: ' . $reasonToken);
    }

    $approvers = [];
    $resolvedProcessName = $processName;
    if (!empty($award->approval_process_id)) {
        $process = $approvalProcesses->get((int)$award->approval_process_id, contain: ['ApprovalProcessSteps']);
        if ($resolvedProcessName === '') {
            $resolvedProcessName = (string)$process->name;
        }
        foreach ($approvalResolver->previewProcess($process, $award) as $stepPreview) {
            $step = $stepPreview['step'];
            $membersForStep = [];
            foreach ($stepPreview['members'] as $memberEntity) {
                $memberRow = $members->find()
                    ->select(['id', 'sca_name', 'email_address'])
                    ->where(['id' => (int)$memberEntity->id])
                    ->first();
                if ($memberRow !== null) {
                    $membersForStep[] = [
                        'id' => (int)$memberRow->id,
                        'scaName' => (string)$memberRow->sca_name,
                        'email' => (string)$memberRow->email_address,
                    ];
                }
            }
            $approvers[] = [
                'stepKey' => (string)$step->step_key,
                'stepLabel' => (string)$step->label,
                'members' => $membersForStep,
            ];
        }
    }

    $created[$fixture['name']] = [
        'id' => (int)$recommendation->id,
        'awardName' => (string)$award->name,
        'awardId' => (int)$award->id,
        'processName' => $resolvedProcessName,
        'approvers' => $approvers,
        'memberScaName' => (string)$recommendation->member_sca_name,
        'reason' => (string)$reasonToken,
    ];
}

echo json_encode(['fixtures' => $created], JSON_THROW_ON_ERROR);
`;

const CLEANUP_FIXTURES_PHP = `
require 'vendor/autoload.php';
require 'config/bootstrap.php';

\\Cake\\Core\\Configure::write('Queue.plugins', array_values(array_unique(array_merge(
    (array)\\Cake\\Core\\Configure::read('Queue.plugins'),
    ['Queue'],
))));
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$recommendations = \\Cake\\ORM\\TableRegistry::getTableLocator()->get('Awards.Recommendations');
$ids = array_values(array_unique(array_map('intval', $input['ids'] ?? [])));
rsort($ids);

foreach ($ids as $id) {
    if (!$recommendations->exists(['id' => $id])) {
        continue;
    }

    $recommendation = $recommendations->get($id);
    $recommendations->delete($recommendation);
}

echo json_encode(['deleted' => $ids], JSON_THROW_ON_ERROR);
`;

const GET_RECOMMENDATION_BESTOWAL_PHP = `
require 'vendor/autoload.php';
require 'config/bootstrap.php';

\\Cake\\Core\\Configure::write('Queue.plugins', array_values(array_unique(array_merge(
    (array)\\Cake\\Core\\Configure::read('Queue.plugins'),
    ['Queue'],
))));
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$recommendations = \\Cake\\ORM\\TableRegistry::getTableLocator()->get('Awards.Recommendations');
$recommendation = $recommendations->get((int)$input['recommendationId'], contain: ['Bestowals']);

echo json_encode([
    'recommendationId' => (int)$recommendation->id,
    'bestowalId' => $recommendation->bestowal_id === null ? null : (int)$recommendation->bestowal_id,
    'bestowalState' => $recommendation->bestowal?->state,
], JSON_THROW_ON_ERROR);
`;

const GET_RECOMMENDATION_WORKFLOW_STATE_PHP = `
require 'vendor/autoload.php';
require 'config/bootstrap.php';

$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$locator = \\Cake\\ORM\\TableRegistry::getTableLocator();
$runs = $locator->get('Awards.RecommendationApprovalRuns');
$recommendations = $locator->get('Awards.Recommendations');
$workflowApprovals = $locator->get('WorkflowApprovals');

$recommendation = $recommendations->get((int)$input['recommendationId']);
$run = $runs->find()
    ->where(['recommendation_id' => (int)$input['recommendationId']])
    ->orderByDesc('id')
    ->first();
$latestTerminalRun = $runs->find()
    ->where([
        'recommendation_id' => (int)$input['recommendationId'],
        'terminal_reason IS NOT' => null,
    ])
    ->orderByDesc('completed')
    ->orderByDesc('id')
    ->first();

$pendingCount = 0;
$latestApprovalStatus = null;
if ($run !== null) {
    $pendingCount = $workflowApprovals->find()
        ->where([
            'workflow_instance_id' => (int)$run->workflow_instance_id,
            'status' => 'pending',
        ])
        ->count();
    $latestApproval = $workflowApprovals->find()
        ->where(['workflow_instance_id' => (int)$run->workflow_instance_id])
        ->orderByDesc('id')
        ->first();
    $latestApprovalStatus = $latestApproval?->status;
}

echo json_encode([
    'recommendationId' => (int)$recommendation->id,
    'state' => (string)$recommendation->state,
    'status' => (string)$recommendation->status,
    'bestowalId' => $recommendation->bestowal_id === null ? null : (int)$recommendation->bestowal_id,
    'runId' => $run === null ? null : (int)$run->id,
    'runStatus' => $run?->status,
    'runTerminalReason' => $run?->terminal_reason,
    'latestTerminalReason' => $latestTerminalRun?->terminal_reason,
    'workflowInstanceId' => $run === null ? null : (int)$run->workflow_instance_id,
    'pendingApprovalCount' => (int)$pendingCount,
    'latestApprovalStatus' => $latestApprovalStatus,
], JSON_THROW_ON_ERROR);
`;

const RESPOND_TO_RECOMMENDATION_APPROVAL_PHP = `
require 'vendor/autoload.php';
require 'config/bootstrap.php';

$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
\\Awards\\Services\\AwardsWorkflowProvider::register();
$locator = \\Cake\\ORM\\TableRegistry::getTableLocator();
$runs = $locator->get('Awards.RecommendationApprovalRuns');
$workflowApprovals = $locator->get('WorkflowApprovals');

$run = $runs->find()
    ->where(['recommendation_id' => (int)$input['recommendationId']])
    ->orderByDesc('id')
    ->first();
if ($run === null) {
    throw new \\RuntimeException('No approval run found for recommendation.');
}

$approval = $workflowApprovals->find()
    ->where([
        'workflow_instance_id' => (int)$run->workflow_instance_id,
        'status' => 'pending',
    ])
    ->orderByAsc('id')
    ->first();
if ($approval === null) {
    throw new \\RuntimeException('No pending workflow approval found.');
}

$decision = (string)$input['decision'];
$comment = isset($input['comment']) ? (string)$input['comment'] : null;
$manager = new \\App\\Services\\WorkflowEngine\\DefaultWorkflowApprovalManager();
$candidateMemberIds = array_values(array_unique(array_filter(array_map(
    'intval',
    array_merge([(int)$input['memberId']], $input['candidateMemberIds'] ?? [])
))));
$memberId = null;
foreach ($candidateMemberIds as $candidateMemberId) {
    foreach ($manager->getPendingApprovalsForMember($candidateMemberId) as $candidateApproval) {
        if ((int)$candidateApproval->id === (int)$approval->id) {
            $memberId = $candidateMemberId;
            break 2;
        }
    }
}
if ($memberId === null) {
    throw new \\RuntimeException('No fixture candidate is eligible to respond to approval #' . (int)$approval->id . '.');
}
$result = $manager->recordResponse((int)$approval->id, $memberId, $decision, $comment);
if (!$result->isSuccess()) {
    throw new \\RuntimeException((string)($result->getError() ?? 'Unable to record approval response.'));
}
$resultData = $result->getData();
if (is_array($resultData) && in_array($resultData['approvalStatus'] ?? '', ['approved', 'rejected'], true)) {
    \\App\\Services\\WorkflowRegistry\\WorkflowActionRegistry::register('Core', [
        [
            'action' => 'Core.SendEmail',
            'label' => 'Send Email',
            'description' => 'Send an email notification using a configured template',
            'inputSchema' => [
                'to' => ['type' => 'string', 'label' => 'Recipient Email', 'required' => true],
                'template' => ['type' => 'emailTemplate', 'label' => 'Email Template', 'required' => true],
                'vars' => ['type' => 'object', 'label' => 'Template Variables'],
                'replyTo' => ['type' => 'string', 'label' => 'Reply-To Email'],
            ],
            'outputSchema' => [
                'sent' => ['type' => 'boolean', 'label' => 'Email Sent'],
            ],
            'serviceClass' => \\App\\Services\\WorkflowEngine\\Actions\\CoreActions::class,
            'serviceMethod' => 'sendEmail',
            'isAsync' => false,
        ],
    ]);
    \\App\\Services\\WorkflowRegistry\\WorkflowConditionRegistry::register('Core', [
        [
            'condition' => 'Core.FieldEquals',
            'label' => 'Field Equals Value',
            'description' => 'Check if a context field equals a specific value',
            'inputSchema' => [
                'field' => ['type' => 'string', 'label' => 'Field Path', 'required' => true],
                'value' => ['type' => 'mixed', 'label' => 'Expected Value', 'required' => true],
            ],
            'evaluatorClass' => \\App\\Services\\WorkflowEngine\\Conditions\\CoreConditions::class,
            'evaluatorMethod' => 'fieldEquals',
        ],
    ]);
    $container = new \\Cake\\Core\\Container();
    $container->add(
        \\App\\Services\\ActiveWindowManager\\ActiveWindowManagerInterface::class,
        \\App\\Services\\ActiveWindowManager\\DefaultActiveWindowManager::class,
    );
    $container->add(\\App\\Services\\WorkflowEngine\\ExpressionEvaluator::class);
    $container->add(\\App\\Services\\WorkflowEngine\\Actions\\CoreActions::class)
        ->addArguments([
            \\App\\Services\\ActiveWindowManager\\ActiveWindowManagerInterface::class,
            \\App\\Services\\WorkflowEngine\\ExpressionEvaluator::class,
        ]);
    $container->add(\\App\\Services\\WorkflowEngine\\Conditions\\CoreConditions::class)
        ->addArgument(\\App\\Services\\WorkflowEngine\\ExpressionEvaluator::class);
    $container->add(\\App\\Services\\WorkflowEngine\\StateMachine\\StateMachineHandler::class);
    $container->add(\\Awards\\Services\\AwardsWorkflowActions::class);
    $container->add(\\Awards\\Services\\AwardsWorkflowConditions::class);
    $engine = new \\App\\Services\\WorkflowEngine\\DefaultWorkflowEngine($container);
    $outputPort = $resultData['approvalStatus'] === 'approved' ? 'approved' : 'rejected';
    $resume = $engine->resumeWorkflow(
        (int)$resultData['instanceId'],
        (string)$resultData['nodeId'],
        $outputPort,
        [
            'approval' => $resultData,
            'approverId' => $memberId,
            'decision' => $decision,
            'comment' => $comment,
        ],
    );
    if (!$resume->isSuccess()) {
        throw new \\RuntimeException((string)($resume->getError() ?? 'Unable to resume approval workflow.'));
    }
}

echo json_encode([
    'approvalId' => (int)$approval->id,
    'decision' => $decision,
    'memberId' => $memberId,
], JSON_THROW_ON_ERROR);
`;

const CREATE_NO_PROCESS_RECOMMENDATION_FIXTURE_PHP = `
require 'vendor/autoload.php';
require 'config/bootstrap.php';

\\Cake\\Core\\Configure::write('Queue.plugins', array_values(array_unique(array_merge(
    (array)\\Cake\\Core\\Configure::read('Queue.plugins'),
    ['Queue'],
))));
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$locator = \\Cake\\ORM\\TableRegistry::getTableLocator();
$awards = $locator->get('Awards.Awards');
$members = $locator->get('Members');
$recommendations = $locator->get('Awards.Recommendations');
$container = new \\Cake\\Core\\Container();
\\Awards\\Services\\AwardsWorkflowProvider::register();
\\App\\Services\\WorkflowRegistry\\WorkflowActionRegistry::register('Core', [
    [
        'action' => 'Core.SendEmail',
        'label' => 'Send Email',
        'description' => 'Send an email notification using a configured template',
        'inputSchema' => [
            'to' => ['type' => 'string', 'label' => 'Recipient Email', 'required' => true],
            'template' => ['type' => 'emailTemplate', 'label' => 'Email Template', 'required' => true],
            'vars' => ['type' => 'object', 'label' => 'Template Variables'],
            'replyTo' => ['type' => 'string', 'label' => 'Reply-To Email'],
        ],
        'outputSchema' => [
            'sent' => ['type' => 'boolean', 'label' => 'Email Sent'],
        ],
        'serviceClass' => \\App\\Services\\WorkflowEngine\\Actions\\CoreActions::class,
        'serviceMethod' => 'sendEmail',
        'isAsync' => false,
    ],
]);
\\App\\Services\\WorkflowRegistry\\WorkflowConditionRegistry::register('Core', [
    [
        'condition' => 'Core.FieldEquals',
        'label' => 'Field Equals Value',
        'description' => 'Check if a context field equals a specific value',
        'inputSchema' => [
            'field' => ['type' => 'string', 'label' => 'Field Path', 'required' => true],
            'value' => ['type' => 'mixed', 'label' => 'Expected Value', 'required' => true],
        ],
        'evaluatorClass' => \\App\\Services\\WorkflowEngine\\Conditions\\CoreConditions::class,
        'evaluatorMethod' => 'fieldEquals',
    ],
]);
$container->add(
    \\App\\Services\\ActiveWindowManager\\ActiveWindowManagerInterface::class,
    \\App\\Services\\ActiveWindowManager\\DefaultActiveWindowManager::class,
);
$container->add(\\App\\Services\\WorkflowEngine\\ExpressionEvaluator::class);
$container->add(\\App\\Services\\WorkflowEngine\\Actions\\CoreActions::class)
    ->addArguments([
        \\App\\Services\\ActiveWindowManager\\ActiveWindowManagerInterface::class,
        \\App\\Services\\WorkflowEngine\\ExpressionEvaluator::class,
    ]);
$container->add(\\App\\Services\\WorkflowEngine\\Conditions\\CoreConditions::class)
    ->addArgument(\\App\\Services\\WorkflowEngine\\ExpressionEvaluator::class);
$container->add(\\App\\Services\\WorkflowEngine\\StateMachine\\StateMachineHandler::class);
$container->add(\\Awards\\Services\\AwardsWorkflowActions::class);
$container->add(\\Awards\\Services\\AwardsWorkflowConditions::class);
$workflowEngine = new \\App\\Services\\WorkflowEngine\\DefaultWorkflowEngine($container);
$triggerDispatcher = new \\App\\Services\\WorkflowEngine\\TriggerDispatcher($workflowEngine);
$extractRecommendationIdFromDispatch = static function (array $dispatchResults): ?int {
    foreach ($dispatchResults as $dispatchResult) {
        if (!$dispatchResult instanceof \\App\\Services\\ServiceResult) {
            continue;
        }
        if (!$dispatchResult->isSuccess()) {
            continue;
        }

        $dispatchData = $dispatchResult->getData();
        if (!is_array($dispatchData)) {
            continue;
        }

        $workflowResult = $dispatchData['workflowResult'] ?? null;
        if (!is_array($workflowResult)) {
            continue;
        }

        $recommendationId = $workflowResult['data']['recommendationId']
            ?? $workflowResult['recommendationId']
            ?? null;
        if (is_numeric($recommendationId)) {
            return (int)$recommendationId;
        }
    }

    return null;
};

$requester = $members->find()->select(['id', 'sca_name', 'email_address', 'phone_number'])
    ->where(['email_address' => (string)$input['requesterEmail']])
    ->firstOrFail();
$member = $members->find()->select(['id', 'public_id', 'sca_name'])
    ->where(['sca_name' => (string)$input['memberName']])
    ->firstOrFail();
$seedAward = $awards->find()->select(['domain_id', 'level_id', 'branch_id'])->where(['deleted IS' => null])->firstOrFail();

$tempAward = $awards->newEntity([
    'name' => 'E2E No Process Award ' . uniqid('', true),
    'abbreviation' => strtoupper(substr(md5(uniqid('', true)), 0, 8)),
    'domain_id' => (int)$seedAward->domain_id,
    'level_id' => (int)$seedAward->level_id,
    'branch_id' => (int)$seedAward->branch_id,
    'approval_process_id' => null,
    'is_active' => true,
]);
$awards->saveOrFail($tempAward);

$reasonToken = (string)$input['reason'] . ' [' . uniqid('wf-', true) . ']';
$dispatchResults = $triggerDispatcher->dispatch(
    'Awards.RecommendationCreateRequested',
    [
        'data' => [
            'award_id' => (int)$tempAward->id,
            'member_sca_name' => (string)$member->sca_name,
            'member_public_id' => (string)$member->public_id,
            'reason' => $reasonToken,
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
    ],
    (int)$requester->id,
);
if ($dispatchResults === []) {
    throw new \\RuntimeException('No-process fixture workflow dispatch returned no results.');
}
foreach ($dispatchResults as $dispatchResult) {
    if ($dispatchResult instanceof \\App\\Services\\ServiceResult && !$dispatchResult->isSuccess()) {
        throw new \\RuntimeException(
            'No-process fixture workflow dispatch failed: ' . ($dispatchResult->getError() ?? 'unknown error')
        );
    }
}

$recommendationId = $extractRecommendationIdFromDispatch($dispatchResults);

$recommendation = $recommendationId === null
    ? null
    : $recommendations->find()
        ->where(['id' => $recommendationId])
        ->first();
if ($recommendation === null) {
    $recommendation = $recommendations->find()
        ->where([
            'award_id' => (int)$tempAward->id,
            'member_id' => (int)$member->id,
            'requester_id' => (int)$requester->id,
            'reason' => $reasonToken,
        ])
        ->orderByDesc('id')
        ->first();
}
if ($recommendation === null) {
    throw new \\RuntimeException('No-process fixture creation did not persist recommendation.');
}
echo json_encode([
    'recommendationId' => (int)$recommendation->id,
    'awardId' => (int)$tempAward->id,
    'awardName' => (string)$tempAward->name,
    'reason' => $reasonToken,
], JSON_THROW_ON_ERROR);
`;

const normalizeText = (value) => value.replace(/\s+/g, ' ').trim();

const runPhpJson = (script, payload) => {
    const fixtureJson = JSON.stringify(payload);
    const useDockerPhp = shouldUseDockerPhp();
    const options = {
        cwd: useDockerPhp ? REPO_ROOT : APP_ROOT,
        encoding: 'utf8',
        input: fixtureJson,
        maxBuffer: 32 * 1024 * 1024,
    };
    const output = useDockerPhp
        ? execFileSync(
            'docker',
            [
                'compose',
                'exec',
                '-T',
                'app',
                'php',
                '-d',
                'xdebug.mode=off',
                '-r',
                script,
            ],
            options,
        ).trim()
        : execFileSync(
            'php',
            ['-d', 'xdebug.mode=off', '-r', script],
            {
                ...options,
                env: process.env,
            },
        ).trim();

    return output === '' ? {} : JSON.parse(output);
};

const ensureFixtureSet = (page) => {
    const fixtures = page.__awardRecommendationFixtures;
    if (!fixtures) {
        throw new Error('Award recommendation fixtures have not been created for this scenario.');
    }

    return fixtures;
};

const getFixture = (page, name) => {
    const fixtures = ensureFixtureSet(page);
    const fixture = fixtures.fixtureMap[name];
    if (!fixture) {
        throw new Error(`Unknown recommendation fixture "${name}".`);
    }

    return fixture;
};

const getFixtureApprover = (fixture, stepIndex = 0) => {
    const step = fixture.approvers?.[stepIndex];
    const member = step?.members?.[0];
    if (!member?.id) {
        throw new Error(`No approver resolved for fixture ${fixture.id} at step index ${stepIndex}.`);
    }

    return member;
};

const getOpenRecommendationEditModal = async (page) => {
    const selectors = ['#editModal', '#editRecommendationModal'];

    for (const selector of selectors) {
        const modal = page.locator(selector);
        if (await modal.count() > 0 && await modal.first().isVisible()) {
            return modal.first();
        }
    }

    throw new Error('No recommendation edit modal is currently open.');
};

const getRecommendationFieldLocator = (modal, fieldLabel) => {
    switch (fieldLabel) {
        case 'Plan to Give At':
            return modal.locator('input[name="gathering_name-Disp"]');
        case 'Given On':
            return modal.locator('input[name="given"]');
        case 'Reason for No Action':
            return modal.locator('input[name="close_reason"]');
        default:
            return modal.getByLabel(fieldLabel, { exact: true });
    }
};

const searchRecommendationsGrid = async (page, query, options = {}) => {
    const switchToAuditView = async () => {
        const auditTab = page.getByRole('tab', { name: /All \/ Audit/ });
        await Promise.all([
            page.waitForResponse((res) => {
                const resUrl = new URL(res.url());
                return res.status() === 200
                    && resUrl.pathname.endsWith('/awards/recommendations/grid-data')
                    && resUrl.searchParams.get('view_id') === 'sys-recs-all';
            }, { timeout: 30000 }),
            auditTab.click(),
        ]);
        await expect(auditTab).toHaveAttribute('aria-selected', 'true', { timeout: 15000 });
    };

    const filterBtn = page.locator('#filterDropdown, button:has-text("Filter")').first();
    await expect(filterBtn).toBeVisible({ timeout: 30000 });
    await filterBtn.click();
    await page.waitForTimeout(300);

    const searchInput = page.locator('[data-grid-view-target="searchInput"]');
    await expect(searchInput).toBeVisible({ timeout: 15000 });
    await searchInput.fill(query);
    const [response] = await Promise.all([
        page.waitForResponse((res) => {
            const resUrl = new URL(res.url());
            return res.status() === 200
                && resUrl.pathname.endsWith('/awards/recommendations/grid-data')
                && resUrl.searchParams.get('search') === query;
        }, { timeout: 30000 }).catch(() => null),
        searchInput.press('Enter'),
    ]);
    if (response) {
        const body = await response.text().catch(() => '');
        const table = page.locator('table.table, .dataTable, table').first();
        try {
            await waitForGridRows(page, GRID_ROWS_SELECTOR);
            await expect(table).toContainText(query, { timeout: 30000 });
        } catch (error) {
            if (!options.allowAuditFallback) {
                if (options.allowServerFragmentFallback && body.includes(query)) {
                    return;
                }

                throw new Error(
                    `recommendations grid search for "${query}" did not render a matching row; `
                    + `captured fragment query-present=${body.includes(query)}.`,
                    { cause: error },
                );
            }

            await switchToAuditView();
            await searchRecommendationsGrid(page, query, {
                allowAuditFallback: false,
                allowServerFragmentFallback: options.allowServerFragmentFallback,
            });
            return;
        }
    }

    if (!response) {
        const table = page.locator('table.table, .dataTable, table').first();
        try {
            await waitForGridRows(page, GRID_ROWS_SELECTOR);
            await expect(table).toContainText(query, { timeout: 30000 });
        } catch (error) {
            if (!options.allowAuditFallback) {
                throw error;
            }

            await switchToAuditView();
            await searchRecommendationsGrid(page, query, {
                allowAuditFallback: false,
                allowServerFragmentFallback: options.allowServerFragmentFallback,
            });
            return;
        }
    }
    await page.keyboard.press('Escape').catch(() => {});
    await page.waitForTimeout(500);
};

const searchCurrentGrid = async (page, query) => {
    await expect(page.locator('table.table, .dataTable, table').first()).toBeVisible({ timeout: 30000 });
    const filterBtn = page.locator('#filterDropdown, button:has-text("Filter")').first();
    await filterBtn.click();

    const searchInput = page.locator('[data-grid-view-target="searchInput"]');
    await expect(searchInput).toBeVisible({ timeout: 15000 });
    await searchInput.fill(query);
    // The grid view controller fetches a server-rendered HTML fragment from the
    // grid-data endpoint with the search term as a query param. Deterministically
    // await THAT request (matched by the endpoint suffix + the exact search value)
    // so we never race the pre-search grid. The original predicate matched the page
    // path ('/approvals') instead of the grid-data endpoint, so it never resolved.
    const [response] = await Promise.all([
        page.waitForResponse((res) => {
            const resUrl = new URL(res.url());
            return res.status() === 200
                && resUrl.pathname.endsWith('/grid-data')
                && resUrl.searchParams.get('search') === query;
        }, { timeout: 30000 }).catch(() => null),
        searchInput.press('Enter'),
    ]);
    // Inspect the server-rendered grid fragment before waiting for rows so empty
    // server-side results fail clearly instead of timing out on a missing row.
    let serverShowing = null;
    let responseBody = '';
    if (response) {
        responseBody = await response.text().catch(() => '');
        const showingMatch = responseBody.match(/showing\s+(\d+)\s+record/i);
        const showing = showingMatch ? showingMatch[1] : 'unknown';
        serverShowing = showing;
        const bodyHasQuery = responseBody.includes(query);
        if (showing === '0' || !bodyHasQuery) {
            throw new Error(
                `approvals grid-data search for "${query}" returned ${showing} record(s) `
                + `and captured fragment query-present=${bodyHasQuery}.`,
            );
        }
    }

    if (serverShowing !== '0') {
        await waitForGridRows(page, GRID_ROWS_SELECTOR);
    }
    await expect(page.locator('table.table, .dataTable, table').first()).toContainText(query, { timeout: 30000 });
    await page.keyboard.press('Escape').catch(() => {});

    return { response, body: responseBody };
};

const selectAutocompleteOption = async (page, comboBox, optionText) => {
    const input = comboBox.locator('[data-ac-target="input"]');
    await expect(input).toBeVisible({ timeout: 15000 });

    // Drive the autocomplete like a real user. URL-backed autocompletes (e.g. the
    // recipient lookup) render fetched results as <li role="option"> nodes into the
    // results target and never populate the controller's in-memory option array, so
    // reading controller.options would never resolve. Typing + clicking the rendered
    // option exercises the same code path a user would and works for both URL-backed
    // and pre-loaded (dataList) autocompletes.
    await input.click();
    await Promise.all([
        page.waitForResponse((response) => response.url().includes('/members/auto-complete')
            && response.status() === 200, { timeout: 15000 }).catch(() => null),
        input.fill(optionText),
    ]);

    const option = comboBox
        .locator('[data-ac-target="results"] [role="option"]:not([aria-disabled="true"])')
        .filter({ hasText: optionText })
        .first();

    await expect(option).toBeVisible({ timeout: 15000 });
    await option.click();
};

const selectFixtureRows = async (page) => {
    const fixtures = ensureFixtureSet(page);
    for (const id of fixtures.ids) {
        const checkbox = page.locator(`table tbody tr[data-id="${id}"] input[data-grid-view-target="rowCheckbox"]`);
        if (await checkbox.count() === 0) {
            const visibleIds = await page.locator('table tbody tr[data-id]').evaluateAll(
                (rows) => rows.map((row) => row.getAttribute('data-id')).filter(Boolean),
            );
            throw new Error(`fixture recommendation ${id} is not visible in the grid. Visible row IDs: ${visibleIds.join(', ')}`);
        }
        await expect(checkbox).toBeVisible();
        await checkbox.check();
    }
};

const getStateRow = (page) => page.locator('tr').filter({ has: page.locator('th', { hasText: 'State' }) }).locator('td');
const getStatusRow = (page) => page.locator('tr').filter({ has: page.locator('th', { hasText: 'Status' }) }).locator('td');
const escapeRegExp = (value) => String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const getBestowalTodoItem = (page, title) => page.locator('#nav-bestowalTodos .list-group-item')
    .filter({
        has: page.locator('.fw-semibold').filter({
            hasText: new RegExp(`${escapeRegExp(title)}\\s*$`),
        }),
    });

When('I navigate to {string}', async ({ page }, path) => {
    await page.goto(path, { waitUntil: 'domcontentloaded' });
    await waitForPageBody(page);
});

Given('I create recommendation fixtures for {string}', async ({ page }, setName) => {
    const fixtureDefinitions = FIXTURE_SETS[setName];
    if (!fixtureDefinitions) {
        throw new Error(`Unknown award recommendation fixture set "${setName}".`);
    }

    const token = `E2E-REC-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
    const result = runPhpJson(CREATE_FIXTURES_PHP, {
        requesterEmail: FIXTURE_REQUESTER_EMAIL,
        memberName: FIXTURE_MEMBER_NAME,
        fixtures: fixtureDefinitions.map((fixture) => ({
            ...fixture,
            reason: `${token} ${fixture.name} workflow coverage`,
        })),
    });

    const fixtureMap = result.fixtures ?? {};
    const ids = Object.values(fixtureMap).map((fixture) => fixture.id);
    const headId = ids.length > 0 ? Math.min(...ids) : null;
    const headName = Object.entries(fixtureMap).find(([, fixture]) => fixture.id === headId)?.[0] ?? null;

    page.__awardRecommendationFixtures = {
        token,
        fixtureMap,
        ids,
        headId,
        headName,
    };
});

Given('I create a recommendation fixture without an approval process', async ({ page }) => {
    const token = `E2E-REC-NOPROC-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
    const result = runPhpJson(CREATE_NO_PROCESS_RECOMMENDATION_FIXTURE_PHP, {
        requesterEmail: FIXTURE_REQUESTER_EMAIL,
        memberName: FIXTURE_MEMBER_NAME,
        reason: `${token} no process fallback coverage`,
    });

    page.__awardRecommendationFixtures = {
        token,
        fixtureMap: {
            'no-process': {
                id: result.recommendationId,
                awardId: result.awardId,
                awardName: result.awardName,
                memberScaName: FIXTURE_MEMBER_NAME,
                reason: result.reason,
                approvers: [],
            },
        },
        ids: [result.recommendationId],
        headId: result.recommendationId,
        headName: 'no-process',
    };
    page.__temporaryAwardIds = [result.awardId];
});

When('I search the recommendations grid for the current fixture token', async ({ page }) => {
    await searchRecommendationsGrid(page, ensureFixtureSet(page).token, { allowAuditFallback: true });
});

When('I open the {string} recommendation detail view', async ({ page }, name) => {
    const fixture = getFixture(page, name);
    await page.goto(`/awards/recommendations/view/${fixture.id}`, { waitUntil: 'networkidle' });
});

When('I open the group head recommendation detail view', async ({ page }) => {
    const fixtures = ensureFixtureSet(page);
    await page.goto(`/awards/recommendations/view/${fixtures.headId}`, { waitUntil: 'networkidle' });
});

When('I open the detail edit modal', async ({ page }) => {
    await page.getByRole('button', { name: 'Edit', exact: true }).click();
    await page.waitForSelector('#editModal.show', { state: 'visible', timeout: 10000 });
    await page.locator('#editModal select[name="state"]').waitFor({ state: 'visible', timeout: 10000 });
});

When('I open the {string} recommendation edit modal from the grid', async ({ page }, name) => {
    const fixture = getFixture(page, name);
    const row = page.locator(`table tbody tr[data-id="${fixture.id}"]`);
    await expect(row).toBeVisible();
    await row.locator('button.edit-rec').click();
    await page.waitForSelector('#editRecommendationModal.show', { state: 'visible', timeout: 10000 });
    await expect(page.locator('form#recommendation_form')).toBeAttached({ timeout: 10000 });
    await expect(page.locator('#editRecommendationModal turbo-frame#editRecommendation textarea[name="note"]'))
        .toBeVisible({ timeout: 10000 });
    await expect(page.locator('#editRecommendationModal button#recommendation_submit')).toBeVisible({ timeout: 10000 });
});

When('I change the open recommendation state to {string}', async ({ page }, state) => {
    const modal = await getOpenRecommendationEditModal(page);
    await modal.locator('select[name="state"]').selectOption({ label: state });
    await page.waitForTimeout(500);
});

When('I select the first available gathering in the open recommendation edit modal', async ({ page }) => {
    const modal = await getOpenRecommendationEditModal(page);
    const input = modal.locator('input[name="gathering_name-Disp"]');
    const combo = input.locator('xpath=ancestor::div[@data-controller="ac"][1]');
    await expect(input).toBeVisible();
    await expect(input).toBeEnabled({ timeout: 10000 });
    await input.fill('Scale Future Gathering');
    const option = combo.locator('ul.auto-complete-list li').first();
    await expect(option).toBeVisible({ timeout: 10000 });
    await option.click();
    await expect(modal.locator('input[name="gathering_id"]')).not.toHaveValue('', { timeout: 5000 });
});

When('I set the open recommendation given date to today', async ({ page }) => {
    const modal = await getOpenRecommendationEditModal(page);
    const today = new Date().toISOString().slice(0, 10);
    await modal.locator('input[name="given"]').fill(today);
});

When('I fill in the open recommendation note with {string}', async ({ page }, note) => {
    const modal = await getOpenRecommendationEditModal(page);
    await modal.locator('textarea[name="note"]').fill(note);
});

When('I fill in the open recommendation close reason with {string}', async ({ page }, reason) => {
    const modal = await getOpenRecommendationEditModal(page);
    await modal.locator('input[name="close_reason"]').fill(reason);
});

When('I submit the open recommendation edit modal', async ({ page }) => {
    const modal = await getOpenRecommendationEditModal(page);
    await modal.locator('button[type="submit"]').click();
    await page.waitForTimeout(1500);
    if (await modal.isVisible()) {
        await expect(modal).toBeHidden({ timeout: 10000 });
    }
    await page.waitForLoadState('networkidle');
});

Then('the open recommendation edit modal should show the {string} field', async ({ page }, fieldLabel) => {
    const modal = await getOpenRecommendationEditModal(page);
    await expect(getRecommendationFieldLocator(modal, fieldLabel)).toBeVisible();
});

Then('the open recommendation edit modal should not show the {string} field', async ({ page }, fieldLabel) => {
    const modal = await getOpenRecommendationEditModal(page);
    await expect(getRecommendationFieldLocator(modal, fieldLabel)).not.toBeVisible();
});

Then('the recommendation detail page should show {string} in the state row', async ({ page }, text) => {
    await expect(getStateRow(page)).toContainText(text);
});

Then('the recommendation detail page should show {string} in the status row', async ({ page }, text) => {
    await expect(getStatusRow(page)).toContainText(text);
});

Then('the {string} recommendation should have an active bestowal link', async ({ page }, name) => {
    const fixture = getFixture(page, name);
    const data = runPhpJson(GET_RECOMMENDATION_BESTOWAL_PHP, {
        recommendationId: fixture.id,
    });

    expect(data.bestowalId).toBeGreaterThan(0);
    expect(data.bestowalState).toBeTruthy();
});

Then('the {string} recommendation should have a workflow run with status {string}', async ({ page }, name, expectedStatus) => {
    const fixture = getFixture(page, name);
    const data = runPhpJson(GET_RECOMMENDATION_WORKFLOW_STATE_PHP, { recommendationId: fixture.id });
    expect(data.runId).toBeGreaterThan(0);
    expect(String(data.runStatus)).toBe(expectedStatus);
});

Then('the {string} recommendation should have no workflow run', async ({ page }, name) => {
    const fixture = getFixture(page, name);
    const data = runPhpJson(GET_RECOMMENDATION_WORKFLOW_STATE_PHP, { recommendationId: fixture.id });
    expect(data.runId).toBeNull();
});

Then('the {string} recommendation should have {int} pending approvals', async ({ page }, name, count) => {
    const fixture = getFixture(page, name);
    const data = runPhpJson(GET_RECOMMENDATION_WORKFLOW_STATE_PHP, { recommendationId: fixture.id });
    expect(data.pendingApprovalCount).toBe(count);
});

Then('the {string} recommendation should be linked to a bestowal', async ({ page }, name) => {
    const fixture = getFixture(page, name);
    const data = runPhpJson(GET_RECOMMENDATION_WORKFLOW_STATE_PHP, { recommendationId: fixture.id });
    expect(data.bestowalId).toBeGreaterThan(0);
});

Then('the {string} recommendation should not be linked to a bestowal', async ({ page }, name) => {
    const fixture = getFixture(page, name);
    const data = runPhpJson(GET_RECOMMENDATION_WORKFLOW_STATE_PHP, { recommendationId: fixture.id });
    expect(data.bestowalId).toBeNull();
});

Then('recommendations {string} and {string} should link to different bestowals', async ({ page }, firstName, secondName) => {
    const first = getFixture(page, firstName);
    const second = getFixture(page, secondName);
    const firstState = runPhpJson(GET_RECOMMENDATION_WORKFLOW_STATE_PHP, { recommendationId: first.id });
    const secondState = runPhpJson(GET_RECOMMENDATION_WORKFLOW_STATE_PHP, { recommendationId: second.id });
    expect(firstState.bestowalId).toBeGreaterThan(0);
    expect(secondState.bestowalId).toBeGreaterThan(0);
    expect(firstState.bestowalId).not.toBe(secondState.bestowalId);
});

When('I approve the pending workflow step {int} for {string}', async ({ page }, stepNumber, name) => {
    const fixture = getFixture(page, name);
    const approver = getFixtureApprover(fixture, stepNumber - 1);
    const candidateMemberIds = (fixture.approvers?.[stepNumber - 1]?.members ?? []).map((member) => member.id);
    runPhpJson(RESPOND_TO_RECOMMENDATION_APPROVAL_PHP, {
        recommendationId: fixture.id,
        memberId: approver.id,
        candidateMemberIds,
        decision: 'approve',
        comment: `E2E approve step ${stepNumber}`,
    });
});

When('I reject the pending workflow step {int} for {string}', async ({ page }, stepNumber, name) => {
    const fixture = getFixture(page, name);
    const approver = getFixtureApprover(fixture, stepNumber - 1);
    const candidateMemberIds = (fixture.approvers?.[stepNumber - 1]?.members ?? []).map((member) => member.id);
    runPhpJson(RESPOND_TO_RECOMMENDATION_APPROVAL_PHP, {
        recommendationId: fixture.id,
        memberId: approver.id,
        candidateMemberIds,
        decision: 'reject',
        comment: `E2E reject step ${stepNumber}`,
    });
});

Then('the {string} recommendation workflow run should have terminal reason {string}', async ({ page }, name, reason) => {
    const fixture = getFixture(page, name);
    const data = runPhpJson(GET_RECOMMENDATION_WORKFLOW_STATE_PHP, { recommendationId: fixture.id });
    expect(data.latestTerminalReason ?? data.runTerminalReason).toBe(reason);
});

Then('the {string} recommendation record should have state {string}', async ({ page }, name, state) => {
    const fixture = getFixture(page, name);
    const data = runPhpJson(GET_RECOMMENDATION_WORKFLOW_STATE_PHP, { recommendationId: fixture.id });
    expect(data.state).toBe(state);
});

When('I select all current fixture recommendations in the grid', async ({ page }) => {
    await selectFixtureRows(page);
});

When('I open the bulk edit modal', async ({ page }) => {
    await page.locator('button[data-bulk-action-key="bulk-edit"]').click();
    await page.waitForSelector('#bulkEditRecommendationModal.show', { state: 'visible', timeout: 10000 });
    await page.locator('#bulkEditRecommendationModal select[name="newState"]').waitFor({ state: 'visible', timeout: 10000 });
});

When('I change the bulk edit state to {string}', async ({ page }, state) => {
    const modal = page.locator('#bulkEditRecommendationModal');
    await modal.locator('select[name="newState"]').selectOption({ label: state });
    await page.waitForTimeout(500);
});

When('I fill in the bulk edit close reason with {string}', async ({ page }, reason) => {
    await page.locator('#bulkEditRecommendationModal input[name="close_reason"]').fill(reason);
});

When('I fill in the bulk edit note with {string}', async ({ page }, note) => {
    await page.locator('#bulkEditRecommendationModal textarea[name="note"]').fill(note);
});

When('I submit the bulk edit modal', async ({ page }) => {
    const modal = page.locator('#bulkEditRecommendationModal');
    await modal.locator('button[type="submit"]').click();
    await page.waitForTimeout(1500);
    if (await modal.isVisible()) {
        await expect(modal).toBeHidden({ timeout: 10000 });
    }
    await page.waitForLoadState('networkidle');
});

Then('the bulk edit modal should show the {string} field', async ({ page }, fieldLabel) => {
    const modal = page.locator('#bulkEditRecommendationModal');
    await expect(getRecommendationFieldLocator(modal, fieldLabel)).toBeVisible();
});

Then('each current fixture recommendation row should contain {string}', async ({ page }, text) => {
    const fixtures = ensureFixtureSet(page);
    for (const id of fixtures.ids) {
        await expect(page.locator(`table tbody tr[data-id="${id}"]`)).toContainText(text);
    }
});

Then('the {string} recommendation row should contain {string}', async ({ page }, name, text) => {
    const fixture = getFixture(page, name);
    await expect(page.locator(`table tbody tr[data-id="${fixture.id}"]`)).toContainText(text);
});

When('I open the group recommendations modal', async ({ page }) => {
    await page.locator('button[data-bulk-action-key="group-recs"]').click();
    await page.waitForSelector('#groupRecommendationsModal.show', { state: 'visible', timeout: 10000 });
});

Then('the group recommendations modal should describe grouping the selected recommendations', async ({ page }) => {
    await expect(page.locator('#groupRecommendationsModal')).toContainText(
        'will be grouped together. The first selected recommendation will become the group head.',
    );
});

When('I submit the group recommendations modal', async ({ page }) => {
    await page.locator('#groupRecommendationsModal button[type="submit"]').click();
    await page.waitForTimeout(1500);
    await expect(page.locator('#groupRecommendationsModal')).toBeHidden({ timeout: 10000 });
    await page.waitForLoadState('networkidle');
});

Then('the recommendation detail page should show the {string} tab', async ({ page }, tabLabel) => {
    // Feedback/grouping tabs are server-rendered from the detail view; modal submits
    // refresh the grid frame (Turbo stream) but not the detail tab list. Reload so the
    // freshly-created records surface their tabs before asserting.
    await page.reload();
    await page.waitForLoadState('networkidle');
    await expect(page.getByRole('tab', { name: new RegExp(`^${tabLabel}`) })).toBeVisible();
});

Then('the recommendation detail page should not show the {string} tab', async ({ page }, tabLabel) => {
    await expect(page.getByRole('tab', { name: new RegExp(`^${tabLabel}`) })).toHaveCount(0);
});

Then('the recommendation group head should list {int} grouped recommendations', async ({ page }, count) => {
    await page.getByRole('tab', { name: /^Grouped/ }).click();
    await expect(page.locator('#nav-grouped tbody tr')).toHaveCount(count);
});

When('I remove the grouped recommendation {string} from the detail view', async ({ page }, name) => {
    const fixture = getFixture(page, name);
    await page.getByRole('tab', { name: /^Grouped/ }).click();
    const row = page.locator('#nav-grouped tbody tr').filter({
        has: page.locator(`a[href="/awards/recommendations/view/${fixture.id}"]`),
    });
    await expect(row).toHaveCount(1);
    await row.locator('[title="Remove from group"], .btn-outline-danger').first().click();
    const confirmDialog = page.getByRole('dialog', { name: 'Confirm action' });
    await expect(confirmDialog).toBeVisible({ timeout: 10000 });
    await confirmDialog.getByRole('button', { name: 'Confirm' }).click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);
});

When('I ungroup all recommendations from the detail view', async ({ page }) => {
    await page.getByRole('tab', { name: /^Grouped/ }).click();
    await page.locator('#nav-grouped').getByText('Ungroup All').click();
    const confirmDialog = page.getByRole('dialog', { name: 'Confirm action' });
    await expect(confirmDialog).toBeVisible({ timeout: 10000 });
    await confirmDialog.getByRole('button', { name: 'Confirm' }).click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);
});

After(async ({ page }) => {
    if (!page.__awardRecommendationFixtures?.ids?.length) {
        if (page.__temporaryAwardIds?.length) {
            runPhpJson(`
require 'vendor/autoload.php';
require 'config/bootstrap.php';
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$awards = \\Cake\\ORM\\TableRegistry::getTableLocator()->get('Awards.Awards');
foreach (array_values(array_unique(array_map('intval', $input['awardIds'] ?? []))) as $id) {
    if ($awards->exists(['id' => $id])) {
        $awards->delete($awards->get($id));
    }
}
echo json_encode(['deleted' => true], JSON_THROW_ON_ERROR);
`, { awardIds: page.__temporaryAwardIds });
        }
        return;
    }

    runPhpJson(CLEANUP_FIXTURES_PHP, {
        ids: page.__awardRecommendationFixtures.ids,
    });
    if (page.__temporaryAwardIds?.length) {
        runPhpJson(`
require 'vendor/autoload.php';
require 'config/bootstrap.php';
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$awards = \\Cake\\ORM\\TableRegistry::getTableLocator()->get('Awards.Awards');
foreach (array_values(array_unique(array_map('intval', $input['awardIds'] ?? []))) as $id) {
    if ($awards->exists(['id' => $id])) {
        $awards->delete($awards->get($id));
    }
}
echo json_encode(['deleted' => true], JSON_THROW_ON_ERROR);
`, { awardIds: page.__temporaryAwardIds });
    }
});

async function commitComboboxByTyping(page, inputSelector, hiddenSelector, text) {
    const input = page.locator(inputSelector);
    await input.fill(text);
    await input.press('Tab');
    await expect(page.locator(hiddenSelector)).toHaveValue(/.+/);
}

When('I enter {string} as an unmatched recommendation recipient', async ({ page }, recipient) => {
    const input = page.locator('#member-sca-name-disp');
    await input.fill(recipient);
    await page.waitForResponse(resp =>
        resp.url().includes('/members/auto-complete')
        && resp.status() === 200
    );
    await input.press('Tab');
});

Then('the submit recommendation form should mark the recipient as not registered', async ({ page }) => {
    await expect(page.locator('#not-found')).toBeChecked();
});

Then('the submit recommendation form should enable the local group field', async ({ page }) => {
    await expect(page.locator('[data-awards-rec-add-target="branch"]')).toHaveJSProperty('hidden', false);
    await expect(page.locator('#branch_name-disp')).toBeEnabled();
});

When('I submit a public recommendation for the unmatched recipient {string}', async ({ page }, recipient) => {
    await page.locator('#recommendation__requester_sca_name').fill('External Recommender');
    await page.locator('#contact-email').fill('external@example.com');

    const recipientInput = page.locator('#member-sca-name-disp');
    await recipientInput.fill(recipient);
    await page.waitForResponse(resp =>
        resp.url().includes('/members/auto-complete')
        && resp.status() === 200
    );
    await recipientInput.press('Tab');
    await expect(page.locator('[data-awards-rec-add-target="branch"]')).toHaveJSProperty('hidden', false);

    await commitComboboxByTyping(page, '#branch_name-disp', '[name="branch_id"]', 'Out of Kingdom');
    const awardResponse = page.waitForResponse(resp =>
        resp.url().includes('/awards/awards/awards-by-domain/')
        && resp.status() === 200
    );
    await commitComboboxByTyping(page, '#domain_name-disp', '[name="domain_id"]', 'General');
    await awardResponse;
    await expect(page.locator('#award_name-disp')).toBeEnabled();

    const firstAward = page.locator('#award_descriptions button[data-award-id]').first();
    const firstAwardText = normalizeText(await firstAward.textContent());
    await commitComboboxByTyping(page, '#award_name-disp', '[name="award_id"]', firstAwardText);

    await page.locator('#recommendation_reason').fill('Public submission regression coverage for a non-member recipient.');
    await page.getByRole('button', { name: /submit/i }).click();
    await page.waitForLoadState('networkidle');
});

When('I submit a public feedback-lane recommendation for a unique unmatched recipient', async ({ page }) => {
    const token = `E2E-FEEDBACK-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
    const recipient = `BDD Feedback Recipient ${token}`;
    const reason = `${token} feedback and notes workflow coverage`;

    await page.locator('#recommendation__requester_sca_name').fill('External Feedback Recommender');
    await page.locator('#contact-email').fill('external-feedback@example.com');

    const recipientInput = page.locator('#member-sca-name-disp');
    await recipientInput.fill(recipient);
    await page.waitForResponse(resp =>
        resp.url().includes('/members/auto-complete')
        && resp.status() === 200
    );
    await recipientInput.press('Tab');
    await expect(page.locator('[data-awards-rec-add-target="branch"]')).toHaveJSProperty('hidden', false);

    await commitComboboxByTyping(page, '#branch_name-disp', '[name="branch_id"]', 'Out of Kingdom');
    const awardResponse = page.waitForResponse(resp =>
        resp.url().includes('/awards/awards/awards-by-domain/')
        && resp.status() === 200
    );
    await commitComboboxByTyping(page, '#domain_name-disp', '[name="domain_id"]', 'General');
    await awardResponse;
    await expect(page.locator('#award_name-disp')).toBeEnabled();

    const firstAward = page.locator('#award_descriptions button[data-award-id]').first();
    const awardName = normalizeText(await firstAward.textContent());
    await commitComboboxByTyping(page, '#award_name-disp', '[name="award_id"]', awardName);

    await page.locator('#recommendation_reason').fill(reason);
    page.__awardPublicFeedbackFixture = {
        token,
        recipient,
        awardName,
        reason,
        feedbackComment: `${token} returned feedback note`,
    };

    await page.getByRole('button', { name: /submit/i }).click();
    await page.waitForLoadState('networkidle');
});

Then('there should be an award recommendation submitted email to {string} for the public feedback-lane recommendation', async ({ page }, recipientEmail) => {
    const subject = publicFeedbackSubmissionSubject(page);
    const query = `to:${recipientEmail} subject:"${subject}"`;
    await waitForScopedMailpitMessageCount(page, query);
    expect(await scopedMailpitMessageCount(page, query)).toBeGreaterThanOrEqual(1);
});

Then('the public feedback-lane recommendation should have a workflow run', async ({ page }) => {
    const fixture = publicFeedbackFixture(page);
    const recommendation = runPhpJson(`
require 'vendor/autoload.php';
require 'config/bootstrap.php';

$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$locator = \\Cake\\ORM\\TableRegistry::getTableLocator();
$recommendation = $locator->get('Awards.Recommendations')->find()
    ->select(['id', 'reason'])
    ->where(['reason' => (string)$input['reason']])
    ->firstOrFail();
$run = $locator->get('Awards.RecommendationApprovalRuns')->find()
    ->select(['id', 'status'])
    ->where(['recommendation_id' => (int)$recommendation->id])
    ->orderByDesc('id')
    ->first();

echo json_encode([
    'recommendationId' => (int)$recommendation->id,
    'runId' => $run === null ? null : (int)$run->id,
    'runStatus' => $run?->status,
], JSON_THROW_ON_ERROR);
`, { reason: fixture.reason });
    expect(recommendation.recommendationId).toBeGreaterThan(0);
    expect(recommendation.runId).toBeGreaterThan(0);
    fixture.recommendationId = recommendation.recommendationId;
    ensureFeedbackRequesterCanManageRecommendations();
});

Then('there should be no award recommendation submitted email to {string} for the public feedback-lane recommendation', async ({ page }, recipientEmail) => {
    const subject = publicFeedbackSubmissionSubject(page);
    const query = `to:${recipientEmail} subject:"${subject}"`;
    flushWorkflowsAndQueue();
    await waitForQueueSettled();
    expect(await waitForStableMailpitSearchTotal(page.request, query)).toBe(0);
});

Then('the recommendation row for {string} should not link to a member profile', async ({ page }, recipient) => {
    const row = page.locator('table tbody tr', { hasText: recipient }).first();
    await expect(row).toContainText(recipient);
    await expect(row.locator('a').filter({ hasText: recipient })).toHaveCount(0);
});

When('I search the recommendations grid for the current public feedback-lane recipient', async ({ page }) => {
    await searchRecommendationsGrid(page, publicFeedbackFixture(page).recipient, {
        allowAuditFallback: true,
        allowServerFragmentFallback: true,
    });
});

When('I open the current public feedback-lane recommendation detail view from the grid', async ({ page }) => {
    const fixture = publicFeedbackFixture(page);
    const row = page.locator(GRID_ROWS_SELECTOR, { hasText: fixture.recipient }).first();
    try {
        await expect(row).toBeVisible({ timeout: 30000 });
    } catch (error) {
        if (fixture.recommendationId > 0) {
            await page.goto(`/awards/recommendations/view/${fixture.recommendationId}`, { waitUntil: 'networkidle' });
            return;
        }

        throw error;
    }

    const id = Number(await row.getAttribute('data-id'));
    expect(id).toBeGreaterThan(0);
    fixture.recommendationId = id;
    page.__awardRecommendationFixtures = {
        token: fixture.token,
        fixtureMap: {
            'public-feedback': {
                id,
                awardName: fixture.awardName,
                memberScaName: fixture.recipient,
                reason: fixture.reason,
            },
        },
        ids: [id],
        headId: id,
        headName: 'public-feedback',
    };

    await page.goto(`/awards/recommendations/view/${id}`, { waitUntil: 'networkidle' });
});

When('I open the current public feedback-lane recommendation detail view', async ({ page }) => {
    const fixture = publicFeedbackFixture(page);
    expect(fixture.recommendationId).toBeGreaterThan(0);
    await page.goto(`/awards/recommendations/view/${fixture.recommendationId}`, { waitUntil: 'networkidle' });
});

When('I request recommendation feedback from {string} with message {string}', async ({ page }, recipientName, message) => {
    const fixture = publicFeedbackFixture(page);
    if (fixture.recommendationId > 0 && !page.url().includes(`/awards/recommendations/view/${fixture.recommendationId}`)) {
        await page.goto(`/awards/recommendations/view/${fixture.recommendationId}`, { waitUntil: 'networkidle' });
    }
    await expect(page).toHaveURL(/\/awards\/recommendations\/view\/\d+/, { timeout: 15000 });

    await page.locator('main').getByRole('button', { name: /Request Feedback/ }).click();
    const feedbackForm = page.locator('#recommendation_feedback_root');
    const modal = page.locator('#requestRecommendationFeedbackModal');
    await expect(modal).toBeVisible({ timeout: 10000 });
    await expect(feedbackForm.locator('input[name="ids"]')).toHaveValue(String(fixture.recommendationId));
    const contextInput = feedbackForm.locator('input[name="page_context_url"]');
    await expect(contextInput).toHaveValue(`/awards/recommendations/view/${fixture.recommendationId}`);
    const originInput = feedbackForm.locator('input[name="feedback_origin"]');
    if (await originInput.count() > 0) {
        await expect(originInput).toHaveValue('detail');
    }

    const comboBox = modal.locator('[data-controller="ac"]').filter({
        has: page.getByLabel('Find recipient member'),
    }).first();
    await selectAutocompleteOption(page, comboBox, recipientName);
    await expect(modal.getByRole('button', { name: 'Add Recipient' })).toBeEnabled({ timeout: 10000 });
    await modal.getByRole('button', { name: 'Add Recipient' }).click();
    await expect(modal.locator('[data-recommendation-feedback-modal-target="recipientList"]')).toContainText(recipientName);

    await modal.getByLabel('Message to recipients').fill(message);
    await expect(modal.getByRole('button', { name: 'Send Feedback Request' })).toBeEnabled({ timeout: 10000 });
    const [response] = await Promise.all([
        page.waitForResponse((res) => {
            const resUrl = new URL(res.url());
            return resUrl.pathname.endsWith('/awards/recommendations/request-feedback');
        }, { timeout: 30000 }),
        modal.getByRole('button', { name: 'Send Feedback Request' }).click(),
    ]);
    const responseBody = response.status() >= 300 && response.status() < 400
        ? ''
        : await response.text();
    const redirectLocation = response.headers()['location'] ?? '';
    expect(
        response.status(),
        `Feedback request response status for ${response.url()}`
            + (redirectLocation ? ` redirect=${redirectLocation}` : '')
            + ` currentPage=${page.url()}: ${responseBody.slice(0, 500)}`,
    ).toBe(200);
    expect(
        responseBody,
        `Feedback request response body for ${response.url()}`,
    ).toContain('Feedback request sent.');
    await expect(modal).toBeHidden({ timeout: 15000 });
    await page.waitForLoadState('networkidle');
});

Then('the recommendation feedback tab should show {string} as {string}', async ({ page }, recipientName, status) => {
    await page.getByRole('tab', { name: /^Feedback/ }).click();
    const row = page.locator('#nav-feedback tbody tr', { hasText: recipientName }).first();
    await expect(row).toBeVisible({ timeout: 15000 });
    await expect(row).toContainText(status);
});

Then('the recommendation feedback tab should show the current feedback response', async ({ page }) => {
    const fixture = publicFeedbackFixture(page);
    await page.getByRole('tab', { name: /^Feedback/ }).click();
    const row = page.locator('#nav-feedback tbody tr', { hasText: FEEDBACK_RECIPIENT_NAME }).first();
    await expect(row).toContainText('Responded');
    await expect(row).toContainText(fixture.feedbackComment);
});

When('I search the approvals grid for the current public feedback-lane recipient', async ({ page }) => {
    const fixture = publicFeedbackFixture(page);
    // The approvals grid renders the request/requester columns from workflow context
    // (searchable:false), so a server-side search can't match the recipient name. The
    // approver may hold many pending approvals, so narrow the grid via the searchable
    // workflow-name column, then locate the specific feedback row by its rendered title.
    const expectedTitle = `Feedback: ${fixture.recipient}`;
    const searchResult = await searchCurrentGrid(page, 'Award Recommendation Feedback Request');
    // Distinguish a genuine server-side miss (approval never created / not eligible /
    // not visible to this approver) from a client-side render/race: inspect the actual
    // grid-data fragment the server returned for this search. A timeout below with a
    // populated server body means a DOM race; an absent title here is a real bug.
    if (searchResult.response) {
        if (searchResult.body && !searchResult.body.includes(expectedTitle)) {
            throw new Error(
                `approvals grid-data response did not include "${expectedTitle}" `
                + 'for the feedback approver — the feedback workflow_approval was not '
                + 'created/visible server-side (not a client race).',
            );
        }
    }
    const row = page.locator('table tbody tr')
        .filter({ hasText: expectedTitle })
        .first();
    await expect(row).toBeVisible({ timeout: 30000 });
});

Then('I should see one recommendation feedback request for the current public feedback-lane recommendation from {string}', async ({ page }, requesterName) => {
    const fixture = publicFeedbackFixture(page);
    const row = page.locator('table tbody tr')
        .filter({ hasText: `Feedback: ${fixture.recipient}` })
        .filter({ hasText: requesterName })
        .first();
    await expect(row).toBeVisible({ timeout: 30000 });
});

When('I send the current recommendation feedback response', async ({ page }) => {
    const fixture = publicFeedbackFixture(page);
    const row = page.locator('table tbody tr')
        .filter({ hasText: `Feedback: ${fixture.recipient}` })
        .first();
    await expect(row).toBeVisible({ timeout: 30000 });

    const responseButton = row.getByRole('button', { name: /Send Feedback|Respond/ }).first();
    await responseButton.click();

    const modal = page.locator('#approvalResponseModal');
    await expect(modal).toBeVisible({ timeout: 10000 });
    await modal.locator('#approvalComment').fill(fixture.feedbackComment);
    await expect(modal.locator('button[type="submit"]')).toBeEnabled({ timeout: 15000 });
    await modal.locator('button[type="submit"]').click();
    await expect(modal).toBeHidden({ timeout: 15000 });
    await page.waitForLoadState('networkidle');
});

Then('the recommendation notes tab should show the current feedback response', async ({ page }) => {
    const fixture = publicFeedbackFixture(page);
    await page.getByRole('tab', { name: /^Notes/ }).click();
    await expect(page.locator('#nav-notes')).toContainText(fixture.feedbackComment, { timeout: 15000 });
});

Then('there should be no recommendation feedback request email to {string}', async ({ page }, recipientEmail) => {
    const query = `to:${recipientEmail} subject:"Award Recommendation Feedback Request"`;
    flushWorkflowsAndQueue();
    await waitForQueueSettled();
    expect(await waitForStableMailpitSearchTotal(page.request, query)).toBe(0);
});

Then('there should be a fallback submission email to crown for {string}', async ({ page }, name) => {
    const fixture = getFixture(page, name);
    const query = `to:crown@ansteorra.org ${fixture.reason.split(' ')[0]}`;
    await waitForScopedMailpitMessageCount(page, query);
});

// ── Award Bestowals ─────────────────────────────────────────────────

const CREATE_HANDOFF_FIXTURE_PHP = `
require 'vendor/autoload.php';
require 'config/bootstrap.php';

$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$locator = \\Cake\\ORM\\TableRegistry::getTableLocator();
$recommendations = $locator->get('Awards.Recommendations');
$members = $locator->get('Members');
$awards = $locator->get('Awards.Awards');

$requester = $members->find()
    ->select(['id', 'sca_name', 'email_address'])
    ->where(['email_address' => $input['requesterEmail']])
    ->firstOrFail();
$member = $members->find()
    ->select(['id', 'public_id', 'sca_name'])
    ->where(['sca_name' => $input['memberName']])
    ->firstOrFail();
$award = $awards->find()->select(['id', 'name'])->firstOrFail();

$service = new \\Awards\\Services\\RecommendationSubmissionService();
$result = $service->submitAuthenticated(
    $recommendations,
    [
        'award_id' => (int)$award->id,
        'member_sca_name' => (string)$member->sca_name,
        'member_public_id' => (string)$member->public_id,
        'reason' => (string)$input['reason'],
        'specialty' => 'No specialties available',
    ],
    [
        'id' => (int)$requester->id,
        'sca_name' => (string)$requester->sca_name,
        'email_address' => (string)$requester->email_address,
        'phone_number' => '',
    ],
);

if (!($result['success'] ?? false)) {
    throw new \\RuntimeException('Handoff fixture creation failed: ' . json_encode($result, JSON_THROW_ON_ERROR));
}

$recommendation = $result['recommendation'];
$recommendation->state = 'King Approved';
$recommendation->status = 'In Progress';
$recommendations->saveOrFail($recommendation);

echo json_encode([
    'recommendationId' => (int)$recommendation->id,
    'memberScaName' => (string)$member->sca_name,
    'token' => (string)$input['token'],
], JSON_THROW_ON_ERROR);
`;

const CREATE_HANDOFF_WITH_BESTOWAL_PHP = `
require 'vendor/autoload.php';
require 'config/bootstrap.php';

$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$locator = \\Cake\\ORM\\TableRegistry::getTableLocator();
$recommendations = $locator->get('Awards.Recommendations');
$members = $locator->get('Members');
$awards = $locator->get('Awards.Awards');

$requester = $members->find()
    ->select(['id', 'sca_name', 'email_address'])
    ->where(['email_address' => $input['requesterEmail']])
    ->firstOrFail();
$member = $members->find()
    ->select(['id', 'public_id', 'sca_name'])
    ->where(['sca_name' => $input['memberName']])
    ->firstOrFail();
$award = $awards->find()->select(['id', 'name'])->firstOrFail();

$service = new \\Awards\\Services\\RecommendationSubmissionService();
$result = $service->submitAuthenticated(
    $recommendations,
    [
        'award_id' => (int)$award->id,
        'member_sca_name' => (string)$member->sca_name,
        'member_public_id' => (string)$member->public_id,
        'reason' => (string)$input['reason'],
        'specialty' => 'No specialties available',
    ],
    [
        'id' => (int)$requester->id,
        'sca_name' => (string)$requester->sca_name,
        'email_address' => (string)$requester->email_address,
        'phone_number' => '',
    ],
);

if (!($result['success'] ?? false)) {
    throw new \\RuntimeException('Handoff fixture creation failed: ' . json_encode($result, JSON_THROW_ON_ERROR));
}

$recommendation = $result['recommendation'];
$recommendation->state = 'Need to Schedule';
$recommendation->status = 'In Progress';
$recommendations->saveOrFail($recommendation);

$bestowalService = new \\Awards\\Services\\BestowalCreationService();
$bestowalResult = $bestowalService->createFromRecommendation((int)$recommendation->id, (int)$requester->id);
if (!($bestowalResult['success'] ?? false)) {
    throw new \\RuntimeException('Bestowal fixture creation failed: ' . json_encode($bestowalResult, JSON_THROW_ON_ERROR));
}

$bestowals = $locator->get('Awards.Bestowals');
$bestowal = $bestowals->get((int)$bestowalResult['data']['bestowalId']);
$bestowal->herald_notes = (string)$input['token'];
$bestowals->saveOrFail($bestowal);

$originalAward = $awards->get((int)$recommendation->award_id, contain: ['Domains']);
$alternateAward = $awards->find()
    ->contain(['Domains'])
    ->where([
        'Awards.id !=' => (int)$originalAward->id,
        'Awards.domain_id !=' => (int)$originalAward->domain_id,
    ])
    ->first();
if ($alternateAward === null) {
    $alternateAward = $awards->find()
        ->contain(['Domains'])
        ->where(['Awards.id !=' => (int)$originalAward->id])
        ->firstOrFail();
}

echo json_encode([
    'recommendationId' => (int)$recommendation->id,
    'bestowalId' => (int)$bestowalResult['data']['bestowalId'],
    'memberScaName' => (string)$member->sca_name,
    'token' => (string)$input['token'],
    'originalAwardId' => (int)$originalAward->id,
    'originalAwardName' => (string)$originalAward->name,
    'originalDomainName' => (string)$originalAward->domain->name,
    'alternateAwardId' => (int)$alternateAward->id,
    'alternateAwardName' => (string)$alternateAward->name,
    'alternateDomainName' => (string)$alternateAward->domain->name,
], JSON_THROW_ON_ERROR);
`;

const CLEANUP_BESTOWAL_FIXTURE_PHP = `
require 'vendor/autoload.php';
require 'config/bootstrap.php';

$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$bestowals = \\Cake\\ORM\\TableRegistry::getTableLocator()->get('Awards.Bestowals');
$recommendations = \\Cake\\ORM\\TableRegistry::getTableLocator()->get('Awards.Recommendations');

foreach (array_values(array_unique(array_map('intval', $input['bestowalIds'] ?? []))) as $id) {
    if ($bestowals->exists(['id' => $id])) {
        $bestowals->delete($bestowals->get($id));
    }
}

foreach (array_values(array_unique(array_map('intval', $input['recommendationIds'] ?? []))) as $id) {
    if ($recommendations->exists(['id' => $id])) {
        $recommendations->delete($recommendations->get($id));
    }
}

echo json_encode(['deleted' => true], JSON_THROW_ON_ERROR);
`;

const LOOKUP_BESTOWAL_AWARD_IDS_PHP = `
require 'vendor/autoload.php';
require 'config/bootstrap.php';

$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$recommendations = \\Cake\\ORM\\TableRegistry::getTableLocator()->get('Awards.Recommendations');
$bestowals = \\Cake\\ORM\\TableRegistry::getTableLocator()->get('Awards.Bestowals');

$recommendation = $recommendations->get((int)$input['recommendationId']);
$bestowal = $bestowals->get((int)$input['bestowalId']);

echo json_encode([
    'recommendationAwardId' => (int)$recommendation->award_id,
    'bestowalAwardId' => (int)$bestowal->award_id,
], JSON_THROW_ON_ERROR);
`;

const ensureBestowalFixture = (page) => {
    const fixture = page.__bestowalFixture;
    if (!fixture) {
        throw new Error('Bestowal fixture has not been created for this scenario.');
    }

    return fixture;
};

const getOpenBestowalEditModal = async (page) => {
    const modal = page.locator('#editBestowalModal.show').first();
    await expect(modal).toBeVisible();
    return modal;
};

const waitForBestowalEditForm = async (modal) => {
    const frame = modal.locator('turbo-frame#editBestowalQuick');
    await frame.locator('input[name="domain_id"]').waitFor({ state: 'attached', timeout: 30000 });
    await expect(frame.locator('input[name="domain_id"]')).not.toHaveValue('', { timeout: 30000 });
    await expect(frame.locator('input[name="award_id"]')).not.toHaveValue('', { timeout: 30000 });
};

const getBestowalEditSubmitButton = (page) => page.locator('#editBestowalModal.show #bestowal_submit');

const getBestowalCombo = (modal, dispField, hiddenField) => {
    const frame = modal.locator('turbo-frame#editBestowalQuick');
    const input = frame.locator(`input[name="${dispField}-Disp"]`);
    const combo = input.locator('xpath=ancestor::div[@data-controller="ac"][1]');
    const hidden = frame.locator(`input[name="${hiddenField}"]`);

    return { frame, input, combo, hidden };
};

const selectBestowalComboOption = async (comboBox, optionText) => {
    await waitForBestowalComboOption(comboBox, optionText);

    await comboBox.evaluate((element, label) => {
        const controller = window.Stimulus?.getControllerForElementAndIdentifier(element, 'ac');
        const options = Array.isArray(controller?.options)
            ? controller.options
            : JSON.parse(element.querySelector('[data-ac-target="dataList"]')?.textContent ?? '[]');
        const match = options.find((option) => option.text === label && option.enabled !== false);

        if (!controller || !match) {
            throw new Error(`Unable to find combo-box option "${label}".`);
        }

        const selected = document.createElement('li');
        selected.setAttribute('data-ac-value', String(match.value));
        selected.textContent = match.text;
        controller.commit(selected);
        element.dispatchEvent(new Event('change', { bubbles: true }));
    }, optionText);
};

const waitForBestowalComboOption = async (comboBox, optionText) => {
    await expect.poll(async () => comboBox.evaluate((element, label) => {
        const controller = window.Stimulus?.getControllerForElementAndIdentifier(element, 'ac');
        const options = Array.isArray(controller?.options)
            ? controller.options
            : JSON.parse(element.querySelector('[data-ac-target="dataList"]')?.textContent ?? '[]');

        return options.some((option) => option.text === label && option.enabled !== false);
    }, optionText), {
        timeout: 15000,
    }).toBe(true);
};

const clearBestowalCombo = async (comboBox) => {
    const clearBtn = comboBox.locator('[data-ac-target="clearBtn"]');
    await expect(clearBtn).toBeEnabled({ timeout: 5000 });
    await clearBtn.click();
    await comboBox.locator('[data-ac-target="input"]').evaluate((input) => {
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });
};

const searchBestowalsGrid = async (page, query) => {
    const gridFrame = page.locator('turbo-frame#bestowals-grid');
    await gridFrame.locator('table.table tbody tr').first().waitFor({ state: 'visible', timeout: 30000 });
    const filterBtn = page.locator('#filterDropdown, button:has-text("Filter")').first();
    await filterBtn.click();
    await page.waitForTimeout(300);

    const searchInput = page.locator('[data-grid-view-target="searchInput"]');
    await searchInput.fill(query);
    await Promise.all([
        page.waitForResponse(
            (response) => {
                const responseUrl = new URL(response.url());
                return response.status() === 200
                    && responseUrl.pathname.includes('/awards/bestowals/grid-data')
                    && responseUrl.searchParams.has('search');
            },
            { timeout: 30000 },
        ).catch(() => null),
        searchInput.press('Enter'),
    ]);
    await expect.poll(async () => {
        const bodyText = await gridFrame.innerText().catch(() => '');
        return !bodyText.includes('Content missing');
    }, { timeout: 15000 }).toBe(true);
    await page.keyboard.press('Escape').catch(() => {});
    await page.waitForTimeout(300);
};

Given('I create a bestowal handoff recommendation fixture', async ({ page }) => {
    const token = `E2E-BESTOWAL-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
    const result = runPhpJson(CREATE_HANDOFF_FIXTURE_PHP, {
        requesterEmail: FIXTURE_REQUESTER_EMAIL,
        memberName: FIXTURE_MEMBER_NAME,
        token,
        reason: `${token} bestowal handoff coverage`,
    });

    page.__bestowalFixture = {
        ...result,
        token,
    };
});

Given('I create a bestowal handoff recommendation fixture with an active bestowal', async ({ page }) => {
    const token = `E2E-BESTOWAL-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
    const result = runPhpJson(CREATE_HANDOFF_WITH_BESTOWAL_PHP, {
        requesterEmail: FIXTURE_REQUESTER_EMAIL,
        memberName: FIXTURE_MEMBER_NAME,
        token,
        reason: `${token} bestowal cancel coverage`,
    });

    page.__bestowalFixture = {
        ...result,
        token,
    };
});

When('I search the recommendations grid for the current bestowal fixture token', async ({ page }) => {
    await searchRecommendationsGrid(page, ensureBestowalFixture(page).token);
});

When('I search the bestowals grid for the current bestowal fixture token', async ({ page }) => {
    const fixture = ensureBestowalFixture(page);
    await searchBestowalsGrid(page, fixture.token);
});

When('I select the bestowal handoff recommendation in the grid', async ({ page }) => {
    const fixture = ensureBestowalFixture(page);
    const checkbox = page.locator(
        `table tbody tr[data-id="${fixture.recommendationId}"] input[data-grid-view-target="rowCheckbox"]`,
    );
    await expect(checkbox).toBeVisible();
    await checkbox.check();
});

Then('the bestowal handoff recommendation row should contain {string}', async ({ page }, text) => {
    const fixture = ensureBestowalFixture(page);
    await expect(page.locator(`table tbody tr[data-id="${fixture.recommendationId}"]`)).toContainText(text);
});

const waitForBestowalsGrid = async (page) => {
    await page.waitForSelector('turbo-frame#bestowals-grid', { state: 'attached', timeout: 30000 });
    await expect(page.locator('body')).not.toContainText('Database Error');
    await expect(page.locator('body')).not.toContainText('TypeError');
    await expect(page.locator('body')).not.toContainText('Content missing');
    const grid = page.locator('turbo-frame#bestowals-grid');
    await expect(grid.locator('table.table')).toBeVisible({ timeout: 30000 });
    await expect(grid).toContainText(/showing \d+ record\(s\)/i, { timeout: 30000 });
};

Then('the bestowals grid should load successfully', async ({ page }) => {
    await waitForBestowalsGrid(page);
});

When('the bestowals grid should load successfully', async ({ page }) => {
    await waitForBestowalsGrid(page);
});

Then('the handoff recommendation should have a linked bestowal', async ({ page }) => {
    const fixture = ensureBestowalFixture(page);
    const lookup = runPhpJson(`
require 'vendor/autoload.php';
require 'config/bootstrap.php';
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$rec = \\Cake\\ORM\\TableRegistry::getTableLocator()->get('Awards.Recommendations')->get((int)$input['recommendationId']);
if ((int)($rec->bestowal_id ?? 0) <= 0) {
    throw new \\RuntimeException('Expected recommendation to be linked to a bestowal.');
}
$bestowal = \\Cake\\ORM\\TableRegistry::getTableLocator()->get('Awards.Bestowals')->get((int)$rec->bestowal_id);
echo json_encode([
    'bestowalId' => (int)$bestowal->id,
    'state' => (string)$bestowal->state,
], JSON_THROW_ON_ERROR);
`, { recommendationId: fixture.recommendationId });

    expect(lookup.bestowalId).toBeGreaterThan(0);
    expect(lookup.state).toBe('Created');
    fixture.bestowalId = lookup.bestowalId;
});

Then('the bestowals grid should contain the fixture member name', async ({ page }) => {
    const fixture = ensureBestowalFixture(page);
    const grid = page.locator('turbo-frame#bestowals-grid table.table').first();
    await expect(grid).toContainText(fixture.memberScaName);
});

When('I open the bestowal detail for the handoff fixture', async ({ page }) => {
    const fixture = ensureBestowalFixture(page);
    let bestowalId = fixture.bestowalId;

    if (!bestowalId) {
        const lookup = runPhpJson(`
require 'vendor/autoload.php';
require 'config/bootstrap.php';
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$rec = \\Cake\\ORM\\TableRegistry::getTableLocator()->get('Awards.Recommendations')->get((int)$input['recommendationId']);
echo json_encode(['bestowalId' => (int)$rec->bestowal_id], JSON_THROW_ON_ERROR);
`, { recommendationId: fixture.recommendationId });
        bestowalId = lookup.bestowalId;
        fixture.bestowalId = bestowalId;
    }

    await page.goto(`/awards/bestowals/view/${bestowalId}`, { waitUntil: 'networkidle' });
});

When('I open the bestowal handoff recommendation detail view', async ({ page }) => {
    const fixture = ensureBestowalFixture(page);
    await page.goto(`/awards/recommendations/view/${fixture.recommendationId}`, { waitUntil: 'networkidle' });
});

When('I open the bestowal detail linked to recommendation {string}', async ({ page }, name) => {
    const fixture = getFixture(page, name);
    const data = runPhpJson(GET_RECOMMENDATION_WORKFLOW_STATE_PHP, { recommendationId: fixture.id });
    if (!data.bestowalId) {
        throw new Error(`Recommendation ${name} is not linked to a bestowal.`);
    }

    await page.goto(`/awards/bestowals/view/${data.bestowalId}`, { waitUntil: 'networkidle' });
});

Then('the bestowal detail page should show {string} in the state row', async ({ page }, text) => {
    const stateRow = page.locator('tr')
        .filter({ has: page.locator('th').filter({ hasText: /^(State|Lifecycle Status)$/ }) })
        .locator('td');
    await expect(stateRow).toContainText(text);
});

Then('the bestowal detail page should show {string} in the source row', async ({ page }, text) => {
    const sourceRow = page.locator('tr').filter({ has: page.locator('th', { hasText: 'Source' }) }).locator('td');
    await expect(sourceRow).toContainText(text);
});

When('I cancel the open bestowal from the detail page', async ({ page }) => {
    await page.getByRole('link', { name: 'Cancel Bestowal' }).click();
    const confirmDialog = page.getByRole('dialog', { name: 'Confirm action' });
    await expect(confirmDialog).toBeVisible({ timeout: 10000 });
    await confirmDialog.getByRole('button', { name: 'Confirm' }).click();
    await page.waitForLoadState('networkidle');
});

When('I open the bestowal to-dos tab', async ({ page }) => {
    const tab = page.getByRole('tab', { name: /^To-Dos/ });
    const panel = page.locator('#nav-bestowalTodos');
    await tab.click();
    await expect(tab).toHaveClass(/active/, { timeout: 10000 });
    await expect(panel).toBeVisible({ timeout: 10000 });
});

Then('the bestowal to-dos should include {string}', async ({ page }, title) => {
    await expect(getBestowalTodoItem(page, title)).toBeVisible({ timeout: 15000 });
});

Then('the bestowal to-do {string} should require a gathering', async ({ page }, title) => {
    const item = getBestowalTodoItem(page, title);
    await expect(item).toBeVisible({ timeout: 15000 });
    await expect(item).toContainText('Gathering required');
    await expect(item.getByLabel('Bestowal Gathering')).toBeVisible({ timeout: 15000 });
    await expect(item.getByRole('button', { name: 'Assign Gathering and Complete' })).toBeVisible();
});

Then('the bestowal to-do {string} should show a gathering assigned', async ({ page }, title) => {
    const item = getBestowalTodoItem(page, title);
    await expect(item).toBeVisible({ timeout: 15000 });
    await expect(item).toContainText('Gathering assigned');
});

Then('the bestowal mark-given action should be disabled', async ({ page }) => {
    const todoPanel = page.locator('#nav-bestowalTodos');
    await expect(todoPanel.getByRole('button', { name: 'Mark Given' })).toBeDisabled();
    await expect(todoPanel).toContainText('Complete all required checks before the bestowal can be marked given.');
});

When('I assign the first available gathering and complete the bestowal to-do {string}', async ({ page }, title) => {
    const item = getBestowalTodoItem(page, title);
    await expect(item).toBeVisible({ timeout: 15000 });

    const input = item.getByLabel('Bestowal Gathering');
    const combo = input.locator('xpath=ancestor::div[@data-controller="ac"][1]');
    await expect(input).toBeVisible({ timeout: 15000 });
    await expect(input).toBeEnabled({ timeout: 10000 });
    await input.fill('Scale Future Gathering');
    const option = combo.locator('[data-ac-target="results"] [role="option"]:not([aria-disabled="true"])').first();
    await expect(option).toBeVisible({ timeout: 15000 });
    await option.click();
    await expect(item.locator('input[name="bestowal_gathering_id"]')).not.toHaveValue('', { timeout: 5000 });

    await item.getByRole('button', { name: 'Assign Gathering and Complete' }).click();
    await page.waitForLoadState('networkidle');
});

When('I complete the bestowal to-do {string}', async ({ page }, title) => {
    const item = getBestowalTodoItem(page, title);
    await expect(item).toBeVisible({ timeout: 15000 });
    const directComplete = item.getByRole('link', { name: /Complete|Mark complete:/ });
    if (await directComplete.count()) {
        await directComplete.click();
    } else {
        const courtAssignment = item.getByLabel('Court Assignment');
        if (await courtAssignment.count()) {
            await courtAssignment.selectOption({ index: 1 });
            await item.getByRole('button', { name: 'Assign Court and Complete' }).click();
            await page.waitForLoadState('networkidle');

            return;
        }

        throw new Error(`No completion control found for bestowal to-do "${title}".`);
    }
    const confirmDialog = page.getByRole('dialog', { name: 'Confirm action' });
    await expect(confirmDialog).toBeVisible({ timeout: 10000 });
    await confirmDialog.getByRole('button', { name: 'Confirm' }).click();
    await page.waitForLoadState('networkidle');
});

When('I open the bestowal edit modal', async ({ page }) => {
    const fixture = ensureBestowalFixture(page);
    let bestowalId = fixture.bestowalId;
    if (!bestowalId) {
        const lookup = runPhpJson(`
require 'vendor/autoload.php';
require 'config/bootstrap.php';
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$rec = \\Cake\\ORM\\TableRegistry::getTableLocator()->get('Awards.Recommendations')->get((int)$input['recommendationId']);
echo json_encode(['bestowalId' => (int)$rec->bestowal_id], JSON_THROW_ON_ERROR);
`, { recommendationId: fixture.recommendationId });
        bestowalId = lookup.bestowalId;
        fixture.bestowalId = bestowalId;
    }

    const visibleModal = page.locator('#editBestowalModal.show').first();
    const turboPath = `/awards/bestowals/turbo-edit-form/${bestowalId}`;
    await Promise.all([
        page.waitForResponse(
            (response) => response.url().includes(turboPath) && response.ok(),
            { timeout: 30000 },
        ).catch(() => null),
        page.locator('button.edit-bestowal').first().click(),
    ]);
    await expect(visibleModal).toBeVisible();
    await waitForBestowalEditForm(visibleModal);
});

When('I clear the bestowal edit award type field', async ({ page }) => {
    const modal = await getOpenBestowalEditModal(page);
    const { combo } = getBestowalCombo(modal, 'domain_name', 'domain_id');
    await clearBestowalCombo(combo);
    await page.waitForTimeout(300);
});

When('I clear the bestowal edit award to bestow field', async ({ page }) => {
    const modal = await getOpenBestowalEditModal(page);
    const { combo } = getBestowalCombo(modal, 'award_name', 'award_id');
    await clearBestowalCombo(combo);
    await page.waitForTimeout(300);
});

When('I select the original bestowal award type in the edit modal', async ({ page }) => {
    const fixture = ensureBestowalFixture(page);
    const modal = await getOpenBestowalEditModal(page);
    const { combo } = getBestowalCombo(modal, 'domain_name', 'domain_id');
    await Promise.all([
        page.waitForResponse(
            (response) => response.url().includes('/awards/awards-by-domain/')
                && response.ok(),
            { timeout: 15000 },
        ),
        selectBestowalComboOption(combo, fixture.originalDomainName),
    ]).catch(async () => {
        await selectBestowalComboOption(combo, fixture.originalDomainName);
        await page.waitForTimeout(1000);
    });
    await page.waitForTimeout(500);
});

When('I select the original bestowal award in the edit modal', async ({ page }) => {
    const fixture = ensureBestowalFixture(page);
    const modal = await getOpenBestowalEditModal(page);
    const awardCombo = getBestowalCombo(modal, 'award_name', 'award_id');
    await expect(awardCombo.input).toBeEnabled({ timeout: 10000 });
    await waitForBestowalComboOption(awardCombo.combo, fixture.originalAwardName);
    await selectBestowalComboOption(awardCombo.combo, fixture.originalAwardName);
    await expect(awardCombo.hidden).not.toHaveValue('', { timeout: 15000 });
    await expect(awardCombo.input).not.toHaveValue('', { timeout: 15000 });
    await page.waitForTimeout(300);
});

When('I change the bestowal edit award to the alternate award', async ({ page }) => {
    const fixture = ensureBestowalFixture(page);
    const modal = await getOpenBestowalEditModal(page);
    const domainCombo = getBestowalCombo(modal, 'domain_name', 'domain_id');
    await clearBestowalCombo(domainCombo.combo);
    await Promise.all([
        page.waitForResponse(
            (response) => response.url().includes('/awards/awards-by-domain/')
                && response.ok(),
            { timeout: 15000 },
        ),
        selectBestowalComboOption(domainCombo.combo, fixture.alternateDomainName),
    ]).catch(async () => {
        await selectBestowalComboOption(domainCombo.combo, fixture.alternateDomainName);
        await page.waitForTimeout(1000);
    });
    await page.waitForTimeout(500);

    const awardCombo = getBestowalCombo(modal, 'award_name', 'award_id');
    await expect(awardCombo.input).toBeEnabled({ timeout: 10000 });
    await selectBestowalComboOption(awardCombo.combo, fixture.alternateAwardName);
    await expect(awardCombo.hidden).not.toHaveValue('', { timeout: 15000 });
    await page.waitForTimeout(300);
});

When('I submit the bestowal edit modal', async ({ page }) => {
    const modal = await getOpenBestowalEditModal(page);
    const submitButton = getBestowalEditSubmitButton(page);
    await expect(submitButton).toBeEnabled();
    await Promise.all([
        page.waitForResponse(
            (response) => response.url().includes('/awards/bestowals/edit/')
                && response.request().method() === 'POST',
            { timeout: 30000 },
        ),
        submitButton.click(),
    ]);
    await expect(modal).toBeHidden({ timeout: 15000 });
    await page.waitForLoadState('networkidle');
});

When('I select the handoff bestowal in the grid', async ({ page }) => {
    const fixture = ensureBestowalFixture(page);
    let bestowalId = fixture.bestowalId;
    if (!bestowalId) {
        const lookup = runPhpJson(`
require 'vendor/autoload.php';
require 'config/bootstrap.php';
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$rec = \\Cake\\ORM\\TableRegistry::getTableLocator()->get('Awards.Recommendations')->get((int)$input['recommendationId']);
echo json_encode(['bestowalId' => (int)$rec->bestowal_id], JSON_THROW_ON_ERROR);
`, { recommendationId: fixture.recommendationId });
        bestowalId = lookup.bestowalId;
        fixture.bestowalId = bestowalId;
    }

    const row = page.locator(
        `turbo-frame#bestowals-grid turbo-frame#bestowals-grid-table table tbody tr[data-id="${bestowalId}"]`,
    );
    await expect.poll(async () => row.isVisible(), { timeout: 15000 }).toBe(true);
    const checkbox = row.locator('input[data-grid-view-target="rowCheckbox"]');
    await checkbox.check();
});

Then('the bestowal edit modal submit button should be enabled', async ({ page }) => {
    const submit = getBestowalEditSubmitButton(page);
    await expect(submit).toBeEnabled({ timeout: 15000 });
});

Then('the bestowal edit modal submit button should be disabled', async ({ page }) => {
    const submit = getBestowalEditSubmitButton(page);
    await expect(submit).toBeDisabled({ timeout: 15000 });
});

Then('the bestowal edit award to bestow field should be disabled', async ({ page }) => {
    const modal = await getOpenBestowalEditModal(page);
    const { input } = getBestowalCombo(modal, 'award_name', 'award_id');
    await expect(input).toBeDisabled();
});

Then('the bestowal edit award to bestow field should be empty', async ({ page }) => {
    const modal = await getOpenBestowalEditModal(page);
    const { hidden, input } = getBestowalCombo(modal, 'award_name', 'award_id');
    await expect(hidden).toHaveValue('');
    await expect(input).toHaveValue('');
});

Then('the bestowal edit award type field should be empty', async ({ page }) => {
    const modal = await getOpenBestowalEditModal(page);
    const { hidden, input } = getBestowalCombo(modal, 'domain_name', 'domain_id');
    await expect(hidden).toHaveValue('');
    await expect(input).toHaveValue('');
});

Then('the linked recommendation should keep its original award', async ({ page }) => {
    const fixture = ensureBestowalFixture(page);
    const lookup = runPhpJson(LOOKUP_BESTOWAL_AWARD_IDS_PHP, {
        recommendationId: fixture.recommendationId,
        bestowalId: fixture.bestowalId,
    });
    expect(lookup.recommendationAwardId).toBe(fixture.originalAwardId);
});

Then('the bestowal should have the alternate award', async ({ page }) => {
    const fixture = ensureBestowalFixture(page);
    const lookup = runPhpJson(LOOKUP_BESTOWAL_AWARD_IDS_PHP, {
        recommendationId: fixture.recommendationId,
        bestowalId: fixture.bestowalId,
    });
    expect(lookup.bestowalAwardId).toBe(fixture.alternateAwardId);
    expect(lookup.recommendationAwardId).not.toBe(fixture.alternateAwardId);
});

When('I submit the open recommendation edit with a turbo stream response', async ({ page }) => {
    const modal = page.locator('#editRecommendationModal');
    await expect(modal).toBeVisible();
    await expect(page.locator('form#recommendation_form')).toBeAttached({ timeout: 15000 });
    await expect(modal.locator('turbo-frame#editRecommendation textarea[name="note"]')).toBeVisible({ timeout: 15000 });

    await waitForTurboStreamResponse(page, async () => {
        await modal.locator('button#recommendation_submit').click();
    });

    await expect(modal).toBeHidden({ timeout: 15000 });
    await page.locator('turbo-frame#recommendations-grid-table table.table tbody tr').first().waitFor({
        state: 'visible',
        timeout: 30000,
    });
});

Then('the recommendations URL should include the current fixture token', async ({ page }) => {
    const token = page.__awardRecommendationFixtures?.token;
    expect(token).toBeTruthy();
    await assertUrlContainsQuery(page, encodeURIComponent(token));
});

Then('the recommendations grid shell should remain connected', async ({ page }) => {
    await assertGridShellPreserved(page, '[data-controller*="grid-view"]');
});

Then('the recommendations grid state script should be present', async ({ page }) => {
    const state = await waitForGridStateJson(page, 'recommendations-grid-table');
    expect(state).toHaveProperty('config');
});

After(async ({ page }) => {
    if (!page.__bestowalFixture) {
        return;
    }

    const fixture = page.__bestowalFixture;
    runPhpJson(CLEANUP_BESTOWAL_FIXTURE_PHP, {
        bestowalIds: fixture.bestowalId ? [fixture.bestowalId] : [],
        recommendationIds: fixture.recommendationId ? [fixture.recommendationId] : [],
    });
});
