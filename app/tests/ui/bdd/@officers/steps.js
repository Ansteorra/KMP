const { createBdd } = require('playwright-bdd');
const { expect } = require('@playwright/test');
const { execFileSync } = require('node:child_process');
const path = require('node:path');
const {
    runPhpJson,
    loginAs,
    waitForQueueSettled,
    flushWorkflowsAndQueue,
    dbQuery,
    assertNoQueuedEmailFor,
    mailpitSearchTotal,
} = require('../../support/ui-helpers.cjs');

const { Given, When, Then } = createBdd();

const APP_ROOT = path.resolve(__dirname, '../../../..');
const REPO_ROOT = path.resolve(APP_ROOT, '..');

const SETUP_FIXTURE_PHP = String.raw`
require 'vendor/autoload.php';
require 'config/bootstrap.php';

$input = json_decode((string)stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$locator = \Cake\ORM\TableRegistry::getTableLocator();
$definitions = $locator->get('WorkflowDefinitions');
$branches = $locator->get('Branches');
$offices = $locator->get('Officers.Offices');
$officers = $locator->get('Officers.Officers');
$members = $locator->get('Members');
$roles = $locator->get('Roles');
$warrantPeriods = $locator->get('WarrantPeriods');

foreach ([
    'officers-release' => true,
    'officer-hire' => true,
    'warrants-roster-approval' => true,
] as $slug => $isActive) {
    $definition = $definitions->find()->where(['slug' => $slug])->firstOrFail();
    $definition->is_active = $isActive;
    $definitions->saveOrFail($definition);
}

$selectedBranch = null;
$selectedOffice = null;
$branchCandidates = $branches->find()
    ->select(['id', 'public_id', 'name', 'type'])
    ->where(['can_have_officers' => true])
    ->orderBy(['id' => 'ASC'])
    ->all();
$officeCandidates = $offices->find()
    ->select(['id', 'name', 'grants_role_id', 'requires_warrant', 'only_one_per_branch', 'applicable_branch_types'])
    ->where([
        'requires_warrant' => true,
        'grants_role_id IS NOT' => null,
        'deleted IS' => null,
    ])
    ->orderBy(['id' => 'ASC'])
    ->all();

foreach ($branchCandidates as $branch) {
    foreach ($officeCandidates as $office) {
        $applicable = (string)($office->applicable_branch_types ?? '');
        if ($applicable !== '' && strpos($applicable, '"' . $branch->type . '"') === false) {
            continue;
        }
        if ($office->only_one_per_branch) {
            $hasCurrentOfficer = $officers->find()
                ->where([
                    'office_id' => $office->id,
                    'branch_id' => $branch->id,
                    'status' => \App\Model\Entity\ActiveWindowBaseEntity::CURRENT_STATUS,
                ])
                ->count() > 0;
            if ($hasCurrentOfficer) {
                continue;
            }
        }

        $selectedBranch = $branch;
        $selectedOffice = $office;
        break 2;
    }
}

if ($selectedBranch === null || $selectedOffice === null) {
    throw new \RuntimeException('Could not find a branch/office pair for the officer lifecycle fixture.');
}

$token = preg_replace('/[^a-z0-9]/', '', strtolower((string)($input['token'] ?? uniqid('officer', true))));
$member = $members->newEntity([
    'password' => 'TestPassword',
    'sca_name' => 'Officer Workflow ' . substr($token, -10),
    'first_name' => 'Officer',
    'middle_name' => '',
    'last_name' => 'Workflow',
    'street_address' => '123 Test Street',
    'city' => 'Austin',
    'state' => 'TX',
    'zip' => '78701',
    'phone_number' => '5551234567',
    'email_address' => substr($token, 0, 32) . '@ampdemo.com',
    'membership_number' => null,
    'membership_expires_on' => new \Cake\I18n\Date('+1 year'),
    'branch_id' => $selectedBranch->id,
    'parent_name' => '',
    'background_check_expires_on' => new \Cake\I18n\Date('+1 year'),
    'birth_month' => 1,
    'birth_year' => 1985,
    'status' => \App\Model\Entity\Member::STATUS_VERIFIED_MEMBERSHIP,
    'title' => '',
    'pronouns' => '',
    'pronunciation' => '',
    'timezone' => 'America/Chicago',
    'created_by' => 1,
    'modified_by' => 1,
], [
    'accessibleFields' => ['created_by' => true, 'modified_by' => true],
]);

if (!$members->save($member)) {
    throw new \RuntimeException('Fixture member creation failed: ' . json_encode($member->getErrors(), JSON_THROW_ON_ERROR));
}

$role = $roles->get($selectedOffice->grants_role_id, ['fields' => ['id', 'name']]);
$today = new \Cake\I18n\DateTime('today');
$fixturePeriod = $warrantPeriods->newEntity([
    'start_date' => (clone $today)->modify('-90 days'),
    'end_date' => (clone $today)->modify('+180 days'),
    'created_by' => 1,
], ['accessibleFields' => ['*' => true]]);
$warrantPeriods->saveOrFail($fixturePeriod);

echo json_encode([
    'branchId' => (int)$selectedBranch->id,
    'branchPublicId' => (string)$selectedBranch->public_id,
    'branchName' => (string)$selectedBranch->name,
    'officeId' => (int)$selectedOffice->id,
    'officeName' => (string)$selectedOffice->name,
    'roleId' => (int)$role->id,
    'roleName' => (string)$role->name,
    'memberId' => (int)$member->id,
    'memberName' => (string)$member->sca_name,
    'memberEmail' => (string)$member->email_address,
    'startDate' => $today->format('Y-m-d'),
], JSON_THROW_ON_ERROR);
`;

const INSPECT_FIXTURE_PHP = String.raw`
require 'vendor/autoload.php';
require 'config/bootstrap.php';

$input = json_decode((string)stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$locator = \Cake\ORM\TableRegistry::getTableLocator();
$members = $locator->get('Members');
$officers = $locator->get('Officers.Officers');
$memberRoles = $locator->get('MemberRoles');
$warrants = $locator->get('Warrants');
$workflowInstances = $locator->get('WorkflowInstances');
$workflowApprovals = $locator->get('WorkflowApprovals');

$formatDateTime = static function ($value): ?string {
    if ($value === null || $value === '') {
        return null;
    }
    if ($value instanceof \DateTimeInterface) {
        return $value->format('Y-m-d H:i:s');
    }

    return (string)$value;
};

$member = $members->find()
    ->select(['id', 'sca_name', 'email_address'])
    ->where(['email_address' => $input['memberEmail']])
    ->first();

if ($member === null) {
    echo json_encode(['memberFound' => false], JSON_THROW_ON_ERROR);
    return;
}

$officer = $officers->find()
    ->contain(['Offices'])
    ->where([
        'member_id' => $member->id,
        'office_id' => (int)$input['officeId'],
        'branch_id' => (int)$input['branchId'],
    ])
    ->orderBy(['Officers.id' => 'DESC'])
    ->first();

$memberRole = null;
if ($officer?->granted_member_role_id) {
    $memberRole = $memberRoles->find()
        ->contain(['Roles'])
        ->where(['MemberRoles.id' => $officer->granted_member_role_id])
        ->first();
}

$warrant = null;
if ($officer !== null) {
    $warrant = $warrants->find()
        ->where([
            'entity_type' => 'Officers.Officers',
            'entity_id' => $officer->id,
        ])
        ->orderBy(['Warrants.id' => 'DESC'])
        ->first();
}

$workflowInstance = null;
$workflowApproval = null;
$currentApprover = null;
if ($warrant?->warrant_roster_id) {
    $instances = $workflowInstances->find()
        ->orderBy(['WorkflowInstances.id' => 'DESC'])
        ->all();

    foreach ($instances as $instance) {
        $context = $instance->context ?? [];
        $triggerRosterId = $context['trigger']['rosterId'] ?? null;
        if ((int)$triggerRosterId !== (int)$warrant->warrant_roster_id) {
            continue;
        }

        $workflowInstance = $instance;
        $workflowApproval = $workflowApprovals->find()
            ->where(['workflow_instance_id' => $instance->id])
            ->orderBy(['WorkflowApprovals.id' => 'DESC'])
            ->first();
        if ($workflowApproval?->current_approver_id) {
            $currentApprover = $members->find()
                ->select(['id', 'email_address'])
                ->where(['id' => $workflowApproval->current_approver_id])
                ->first();
        }
        break;
    }
}

echo json_encode([
    'memberFound' => true,
    'memberId' => (int)$member->id,
    'memberName' => (string)$member->sca_name,
    'memberEmail' => (string)$member->email_address,
    'expectedHireSubject' => 'Appointment Notification: ' . $input['officeName'],
    'expectedReleaseSubject' => 'Release from Office Notification: ' . $input['officeName'],
    'expectedWarrantSubject' => $warrant ? 'Warrant Issued: ' . $warrant->name : null,
    'officer' => $officer ? [
        'id' => (int)$officer->id,
        'status' => (string)$officer->status,
        'startOn' => $formatDateTime($officer->start_on),
        'expiresOn' => $formatDateTime($officer->expires_on),
        'revokedReason' => $officer->revoked_reason,
        'revokerId' => $officer->revoker_id !== null ? (int)$officer->revoker_id : null,
        'grantedMemberRoleId' => $officer->granted_member_role_id !== null ? (int)$officer->granted_member_role_id : null,
    ] : null,
    'memberRole' => $memberRole ? [
        'id' => (int)$memberRole->id,
        'roleId' => (int)$memberRole->role_id,
        'roleName' => (string)($memberRole->role->name ?? ''),
        'startOn' => $formatDateTime($memberRole->start_on),
        'expiresOn' => $formatDateTime($memberRole->expires_on),
        'revokerId' => $memberRole->revoker_id !== null ? (int)$memberRole->revoker_id : null,
        'entityType' => $memberRole->entity_type ?? null,
    ] : null,
    'warrant' => $warrant ? [
        'id' => (int)$warrant->id,
        'name' => (string)$warrant->name,
        'status' => (string)$warrant->status,
        'startOn' => $formatDateTime($warrant->start_on),
        'expiresOn' => $formatDateTime($warrant->expires_on),
        'approvedDate' => $formatDateTime($warrant->approved_date),
        'revokedReason' => $warrant->revoked_reason,
        'revokerId' => $warrant->revoker_id !== null ? (int)$warrant->revoker_id : null,
        'memberRoleId' => $warrant->member_role_id !== null ? (int)$warrant->member_role_id : null,
        'warrantRosterId' => $warrant->warrant_roster_id !== null ? (int)$warrant->warrant_roster_id : null,
    ] : null,
    'workflowInstance' => $workflowInstance ? [
        'id' => (int)$workflowInstance->id,
        'status' => (string)$workflowInstance->status,
    ] : null,
    'workflowApproval' => $workflowApproval ? [
        'id' => (int)$workflowApproval->id,
        'status' => (string)$workflowApproval->status,
        'requiredCount' => (int)$workflowApproval->required_count,
        'approvedCount' => (int)$workflowApproval->approved_count,
        'currentApproverId' => $workflowApproval->current_approver_id !== null ? (int)$workflowApproval->current_approver_id : null,
        'currentApproverEmail' => $currentApprover ? (string)$currentApprover->email_address : null,
    ] : null,
], JSON_THROW_ON_ERROR);
`;

const SETUP_OVERLAP_FIXTURE_PHP = String.raw`
require 'vendor/autoload.php';
require 'config/bootstrap.php';

$input = json_decode((string)stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$case = (string)($input['case'] ?? '');
$locator = \Cake\ORM\TableRegistry::getTableLocator();
$definitions = $locator->get('WorkflowDefinitions');
$branches = $locator->get('Branches');
$offices = $locator->get('Officers.Offices');
$officers = $locator->get('Officers.Officers');
$members = $locator->get('Members');
$roles = $locator->get('Roles');
$memberRoles = $locator->get('MemberRoles');
$warrantRosters = $locator->get('WarrantRosters');
$warrants = $locator->get('Warrants');
$warrantPeriods = $locator->get('WarrantPeriods');

foreach ([
    'officers-release' => true,
    'officer-hire' => true,
    'warrants-roster-approval' => true,
] as $slug => $isActive) {
    $definition = $definitions->find()->where(['slug' => $slug])->firstOrFail();
    $definition->is_active = $isActive;
    $definitions->saveOrFail($definition);
}

$selectedBranch = null;
$selectedOffice = null;
$branchCandidates = $branches->find()
    ->select(['id', 'public_id', 'name', 'type'])
    ->where(['can_have_officers' => true])
    ->orderBy(['id' => 'ASC'])
    ->all();
$officeCandidates = $offices->find()
    ->select(['id', 'name', 'grants_role_id', 'requires_warrant', 'only_one_per_branch', 'deputy_to_id', 'applicable_branch_types'])
    ->where([
        'requires_warrant' => true,
        'only_one_per_branch' => true,
        'deputy_to_id IS NOT' => null,
        'grants_role_id IS NOT' => null,
        'deleted IS' => null,
    ])
    ->orderBy(['id' => 'ASC'])
    ->all();

foreach ($branchCandidates as $branch) {
    foreach ($officeCandidates as $office) {
        $applicable = (string)($office->applicable_branch_types ?? '');
        if ($applicable !== '' && strpos($applicable, '"' . $branch->type . '"') === false) {
            continue;
        }

        $existingCount = $officers->find()
            ->where([
                'office_id' => $office->id,
                'branch_id' => $branch->id,
                'status IN' => [
                    \App\Model\Entity\ActiveWindowBaseEntity::CURRENT_STATUS,
                    \App\Model\Entity\ActiveWindowBaseEntity::UPCOMING_STATUS,
                ],
            ])
            ->count();
        if ($existingCount > 0) {
            continue;
        }

        $selectedBranch = $branch;
        $selectedOffice = $office;
        break 2;
    }
}

if ($selectedBranch === null || $selectedOffice === null) {
    throw new \RuntimeException('Could not find a branch/office pair for the officer overlap fixture.');
}

$token = preg_replace('/[^a-z0-9]/', '', strtolower((string)($input['token'] ?? uniqid('overlap', true))));
$formatDateTime = static function (?\Cake\I18n\DateTime $value): ?string {
    return $value?->format('Y-m-d H:i:s');
};

$createMember = static function (string $label, string $emailLocal, int $branchId) use ($members) {
    $member = $members->newEntity([
        'password' => 'TestPassword',
        'sca_name' => $label,
        'first_name' => 'Officer',
        'middle_name' => '',
        'last_name' => 'Workflow',
        'street_address' => '123 Test Street',
        'city' => 'Austin',
        'state' => 'TX',
        'zip' => '78701',
        'phone_number' => '5551234567',
        'email_address' => $emailLocal . '@ampdemo.com',
        'membership_number' => null,
        'membership_expires_on' => new \Cake\I18n\Date('+1 year'),
        'branch_id' => $branchId,
        'parent_name' => '',
        'background_check_expires_on' => new \Cake\I18n\Date('+1 year'),
        'birth_month' => 1,
        'birth_year' => 1985,
        'status' => \App\Model\Entity\Member::STATUS_VERIFIED_MEMBERSHIP,
        'title' => '',
        'pronouns' => '',
        'pronunciation' => '',
        'timezone' => 'America/Chicago',
        'warrantable' => true,
        'created_by' => 1,
        'modified_by' => 1,
    ], [
        'accessibleFields' => ['created_by' => true, 'modified_by' => true],
    ]);

    $members->saveOrFail($member);

    return $member;
};

$today = new \Cake\I18n\DateTime('today');
$fixturePeriod = $warrantPeriods->newEntity([
    'start_date' => (clone $today)->modify('-90 days'),
    'end_date' => (clone $today)->modify('+180 days'),
    'created_by' => 1,
], ['accessibleFields' => ['*' => true]]);
$warrantPeriods->saveOrFail($fixturePeriod);

$reason = 'Replaced by new officer';

switch ($case) {
    case 'current-trim':
        $newStart = (clone $today)->modify('+10 days');
        $newEnd = (clone $today)->modify('+40 days');
        $oldStart = (clone $today)->modify('-30 days');
        $oldEnd = (clone $today)->modify('+120 days');
        $oldStatus = \Officers\Model\Entity\Officer::CURRENT_STATUS;
        $expectedOldStatus = \Officers\Model\Entity\Officer::CURRENT_STATUS;
        $expectedOldStart = clone $oldStart;
        $expectedOldEnd = (clone $newStart)->modify('-1 second');
        $expectedOldWarrantStatus = \App\Model\Entity\Warrant::CURRENT_STATUS;
        $expectedOldNotice = 'adjusted';
        $expectedNewStatus = \Officers\Model\Entity\Officer::UPCOMING_STATUS;
        break;
    case 'current-full-release':
        $newStart = clone $today;
        $newEnd = (clone $today)->modify('+30 days');
        $oldStart = clone $today;
        $oldEnd = (clone $today)->modify('+120 days');
        $oldStatus = \Officers\Model\Entity\Officer::CURRENT_STATUS;
        $expectedOldStatus = \Officers\Model\Entity\Officer::REPLACED_STATUS;
        $expectedOldStart = clone $oldStart;
        $expectedOldEnd = (clone $newStart)->modify('-1 second');
        $expectedOldWarrantStatus = \App\Model\Entity\Warrant::DEACTIVATED_STATUS;
        $expectedOldNotice = 'release';
        $expectedNewStatus = \Officers\Model\Entity\Officer::CURRENT_STATUS;
        break;
    case 'upcoming-push':
        $newStart = (clone $today)->modify('+10 days');
        $newEnd = (clone $today)->modify('+40 days');
        $oldStart = (clone $today)->modify('+30 days');
        $oldEnd = (clone $today)->modify('+90 days');
        $oldStatus = \Officers\Model\Entity\Officer::UPCOMING_STATUS;
        $expectedOldStatus = \Officers\Model\Entity\Officer::UPCOMING_STATUS;
        $expectedOldStart = (clone $newEnd)->modify('+1 second');
        $expectedOldEnd = clone $oldEnd;
        $expectedOldWarrantStatus = \App\Model\Entity\Warrant::PENDING_STATUS;
        $expectedOldNotice = 'adjusted';
        $expectedNewStatus = \Officers\Model\Entity\Officer::UPCOMING_STATUS;
        break;
    case 'upcoming-full-release':
        $newStart = (clone $today)->modify('+20 days');
        $newEnd = (clone $today)->modify('+60 days');
        $oldStart = (clone $today)->modify('+30 days');
        $oldEnd = (clone $today)->modify('+50 days');
        $oldStatus = \Officers\Model\Entity\Officer::UPCOMING_STATUS;
        $expectedOldStatus = \Officers\Model\Entity\Officer::REPLACED_STATUS;
        $expectedOldStart = clone $oldStart;
        $expectedOldEnd = (clone $newStart)->modify('-1 second');
        $expectedOldWarrantStatus = \App\Model\Entity\Warrant::PENDING_STATUS;
        $expectedOldNotice = 'release';
        $expectedNewStatus = \Officers\Model\Entity\Officer::UPCOMING_STATUS;
        break;
    case 'upcoming-middle-trim':
        $newStart = (clone $today)->modify('+20 days');
        $newEnd = (clone $today)->modify('+30 days');
        $oldStart = (clone $today)->modify('+5 days');
        $oldEnd = (clone $today)->modify('+60 days');
        $oldStatus = \Officers\Model\Entity\Officer::UPCOMING_STATUS;
        $expectedOldStatus = \Officers\Model\Entity\Officer::UPCOMING_STATUS;
        $expectedOldStart = clone $oldStart;
        $expectedOldEnd = (clone $newStart)->modify('-1 second');
        $expectedOldWarrantStatus = \App\Model\Entity\Warrant::PENDING_STATUS;
        $expectedOldNotice = 'adjusted';
        $expectedNewStatus = \Officers\Model\Entity\Officer::UPCOMING_STATUS;
        break;
    default:
        throw new \RuntimeException('Unknown overlap fixture case: ' . $case);
}

$oldMember = $createMember(
    'Officer Overlap Old ' . substr($token, -8),
    'ovlold' . substr(sha1($token . '-old'), 0, 20),
    (int)$selectedBranch->id,
);
$newMember = $createMember(
    'Officer Overlap New ' . substr($token, -8),
    'ovlnew' . substr(sha1($token . '-new'), 0, 20),
    (int)$selectedBranch->id,
);
$role = $roles->get($selectedOffice->grants_role_id, ['fields' => ['id', 'name']]);

$oldOfficer = $officers->newEntity([
    'member_id' => $oldMember->id,
    'office_id' => $selectedOffice->id,
    'branch_id' => $selectedBranch->id,
    'status' => $oldStatus,
    'start_on' => $oldStart,
    'expires_on' => $oldEnd,
    'approver_id' => 1,
    'approval_date' => new \Cake\I18n\DateTime('-1 day'),
    'email_address' => $oldMember->email_address,
    'created_by' => 1,
    'modified_by' => 1,
], ['accessibleFields' => ['created_by' => true, 'modified_by' => true]]);
$officers->saveOrFail($oldOfficer);

$oldRole = $memberRoles->newEntity([
    'member_id' => $oldMember->id,
    'role_id' => $role->id,
    'entity_type' => 'Officers.Officers',
    'entity_id' => $oldOfficer->id,
    'branch_id' => $selectedBranch->id,
    'status' => $oldStatus,
    'start_on' => $oldStart,
    'expires_on' => $oldEnd,
    'approver_id' => 1,
    'created_by' => 1,
    'modified_by' => 1,
], ['accessibleFields' => ['*' => true]]);
$memberRoles->saveOrFail($oldRole);

$oldOfficer->granted_member_role_id = $oldRole->id;
$officers->saveOrFail($oldOfficer);

$rosterStatus = $oldStatus === \Officers\Model\Entity\Officer::CURRENT_STATUS
    ? \App\Model\Entity\WarrantRoster::STATUS_APPROVED
    : \App\Model\Entity\WarrantRoster::STATUS_PENDING;
$oldRoster = $warrantRosters->newEntity([
    'name' => 'Officer Overlap Roster ' . substr($token, -8),
    'description' => 'Overlap fixture roster',
    'approvals_required' => 1,
    'approval_count' => $oldStatus === \Officers\Model\Entity\Officer::CURRENT_STATUS ? 1 : 0,
    'status' => $rosterStatus,
    'created_by' => 1,
    'modified_by' => 1,
], ['accessibleFields' => ['*' => true]]);
$warrantRosters->saveOrFail($oldRoster);

$oldWarrant = $warrants->newEntity([
    'name' => $selectedOffice->name . ' : ' . $oldMember->sca_name,
    'member_id' => $oldMember->id,
    'warrant_roster_id' => $oldRoster->id,
    'entity_type' => 'Officers.Officers',
    'entity_id' => $oldOfficer->id,
    'requester_id' => 1,
    'member_role_id' => $oldRole->id,
    'start_on' => $oldStart,
    'expires_on' => $oldEnd,
    'approved_date' => $oldStatus === \Officers\Model\Entity\Officer::CURRENT_STATUS ? new \Cake\I18n\DateTime('-1 day') : null,
    'status' => $oldStatus === \Officers\Model\Entity\Officer::CURRENT_STATUS
        ? \App\Model\Entity\Warrant::CURRENT_STATUS
        : \App\Model\Entity\Warrant::PENDING_STATUS,
    'created_by' => 1,
    'modified_by' => 1,
], ['accessibleFields' => ['*' => true]]);
$warrants->saveOrFail($oldWarrant);

echo json_encode([
    'case' => $case,
    'branchId' => (int)$selectedBranch->id,
    'branchPublicId' => (string)$selectedBranch->public_id,
    'branchName' => (string)$selectedBranch->name,
    'officeId' => (int)$selectedOffice->id,
    'officeName' => (string)$selectedOffice->name,
    'roleId' => (int)$role->id,
    'roleName' => (string)$role->name,
    'memberId' => (int)$newMember->id,
    'memberName' => (string)$newMember->sca_name,
    'memberEmail' => (string)$newMember->email_address,
    'oldMemberId' => (int)$oldMember->id,
    'oldMemberName' => (string)$oldMember->sca_name,
    'oldMemberEmail' => (string)$oldMember->email_address,
    'oldOfficerId' => (int)$oldOfficer->id,
    'oldRoleId' => (int)$oldRole->id,
    'oldWarrantId' => (int)$oldWarrant->id,
    'newStartDate' => $newStart->format('Y-m-d'),
    'newEndDate' => $newEnd->format('Y-m-d'),
    'expectedNewOfficerStatus' => $expectedNewStatus,
    'expectedOldOfficerStatus' => $expectedOldStatus,
    'expectedOldOfficerStartOn' => $formatDateTime($expectedOldStart),
    'expectedOldOfficerExpiresOn' => $formatDateTime($expectedOldEnd),
    'expectedOldWarrantStatus' => $expectedOldWarrantStatus,
    'expectedOldWarrantStartOn' => $formatDateTime($expectedOldStart),
    'expectedOldWarrantExpiresOn' => $expectedOldNotice === 'release'
        ? $formatDateTime(clone $newStart)
        : $formatDateTime($expectedOldEnd),
    'expectedOldNotification' => $expectedOldNotice,
    'expectedOldNotificationSubject' => $expectedOldNotice === 'release'
        ? 'Release from Office Notification: ' . $selectedOffice->name
        : 'Officer Assignment Dates Updated: ' . $selectedOffice->name,
    'expectedNewNotificationSubject' => 'Appointment Notification: ' . $selectedOffice->name,
    'reason' => $reason,
], JSON_THROW_ON_ERROR);
`;

const INSPECT_OVERLAP_FIXTURE_PHP = String.raw`
require 'vendor/autoload.php';
require 'config/bootstrap.php';

$input = json_decode((string)stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$locator = \Cake\ORM\TableRegistry::getTableLocator();
$members = $locator->get('Members');
$officers = $locator->get('Officers.Officers');
$memberRoles = $locator->get('MemberRoles');
$warrants = $locator->get('Warrants');
$workflowInstances = $locator->get('WorkflowInstances');
$workflowApprovals = $locator->get('WorkflowApprovals');

$formatDateTime = static function ($value): ?string {
    if ($value === null || $value === '') {
        return null;
    }
    if ($value instanceof \DateTimeInterface) {
        return $value->format('Y-m-d H:i:s');
    }

    return (string)$value;
};

$findMember = static function (string $email) use ($members) {
    return $members->find()
        ->select(['id', 'sca_name', 'email_address'])
        ->where(['email_address' => $email])
        ->first();
};

$findOfficer = static function (int $memberId, int $officeId, int $branchId) use ($officers) {
    return $officers->find()
        ->contain(['Offices'])
        ->where([
            'member_id' => $memberId,
            'office_id' => $officeId,
            'branch_id' => $branchId,
        ])
        ->orderBy(['Officers.id' => 'DESC'])
        ->first();
};

$loadRole = static function ($officer) use ($memberRoles) {
    if (!$officer?->granted_member_role_id) {
        return null;
    }

    return $memberRoles->find()
        ->contain(['Roles'])
        ->where(['MemberRoles.id' => $officer->granted_member_role_id])
        ->first();
};

$loadWarrant = static function ($officer) use ($warrants) {
    if ($officer === null) {
        return null;
    }

    return $warrants->find()
        ->where([
            'entity_type' => 'Officers.Officers',
            'entity_id' => $officer->id,
        ])
        ->orderBy(['Warrants.id' => 'DESC'])
        ->first();
};

$oldMember = $findMember($input['oldMemberEmail']);
$newMember = $findMember($input['memberEmail']);
if ($oldMember === null || $newMember === null) {
    echo json_encode(['membersFound' => false], JSON_THROW_ON_ERROR);
    return;
}

$oldOfficer = $findOfficer((int)$oldMember->id, (int)$input['officeId'], (int)$input['branchId']);
$newOfficer = $findOfficer((int)$newMember->id, (int)$input['officeId'], (int)$input['branchId']);
$oldRole = $loadRole($oldOfficer);
$newRole = $loadRole($newOfficer);
$oldWarrant = $loadWarrant($oldOfficer);
$newWarrant = $loadWarrant($newOfficer);

$workflowInstance = null;
$workflowApproval = null;
if ($newWarrant?->warrant_roster_id) {
    $instances = $workflowInstances->find()
        ->orderBy(['WorkflowInstances.id' => 'DESC'])
        ->all();

    foreach ($instances as $instance) {
        $context = $instance->context ?? [];
        $triggerRosterId = $context['trigger']['rosterId'] ?? null;
        if ((int)$triggerRosterId !== (int)$newWarrant->warrant_roster_id) {
            continue;
        }

        $workflowInstance = $instance;
        $workflowApproval = $workflowApprovals->find()
            ->where(['workflow_instance_id' => $instance->id])
            ->orderBy(['WorkflowApprovals.id' => 'DESC'])
            ->first();
        break;
    }
}

echo json_encode([
    'membersFound' => true,
    'oldOfficer' => $oldOfficer ? [
        'id' => (int)$oldOfficer->id,
        'status' => (string)$oldOfficer->status,
        'startOn' => $formatDateTime($oldOfficer->start_on),
        'expiresOn' => $formatDateTime($oldOfficer->expires_on),
        'revokedReason' => $oldOfficer->revoked_reason,
        'revokerId' => $oldOfficer->revoker_id !== null ? (int)$oldOfficer->revoker_id : null,
        'grantedMemberRoleId' => $oldOfficer->granted_member_role_id !== null ? (int)$oldOfficer->granted_member_role_id : null,
    ] : null,
    'oldRole' => $oldRole ? [
        'id' => (int)$oldRole->id,
        'roleId' => (int)$oldRole->role_id,
        'roleName' => (string)($oldRole->role->name ?? ''),
        'startOn' => $formatDateTime($oldRole->start_on),
        'expiresOn' => $formatDateTime($oldRole->expires_on),
        'revokerId' => $oldRole->revoker_id !== null ? (int)$oldRole->revoker_id : null,
    ] : null,
    'oldWarrant' => $oldWarrant ? [
        'id' => (int)$oldWarrant->id,
        'status' => (string)$oldWarrant->status,
        'startOn' => $formatDateTime($oldWarrant->start_on),
        'expiresOn' => $formatDateTime($oldWarrant->expires_on),
        'revokedReason' => $oldWarrant->revoked_reason,
        'revokerId' => $oldWarrant->revoker_id !== null ? (int)$oldWarrant->revoker_id : null,
        'memberRoleId' => $oldWarrant->member_role_id !== null ? (int)$oldWarrant->member_role_id : null,
    ] : null,
    'newOfficer' => $newOfficer ? [
        'id' => (int)$newOfficer->id,
        'status' => (string)$newOfficer->status,
        'startOn' => $formatDateTime($newOfficer->start_on),
        'expiresOn' => $formatDateTime($newOfficer->expires_on),
        'grantedMemberRoleId' => $newOfficer->granted_member_role_id !== null ? (int)$newOfficer->granted_member_role_id : null,
    ] : null,
    'newRole' => $newRole ? [
        'id' => (int)$newRole->id,
        'roleId' => (int)$newRole->role_id,
        'roleName' => (string)($newRole->role->name ?? ''),
        'startOn' => $formatDateTime($newRole->start_on),
        'expiresOn' => $formatDateTime($newRole->expires_on),
        'revokerId' => $newRole->revoker_id !== null ? (int)$newRole->revoker_id : null,
    ] : null,
    'newWarrant' => $newWarrant ? [
        'id' => (int)$newWarrant->id,
        'name' => (string)$newWarrant->name,
        'status' => (string)$newWarrant->status,
        'startOn' => $formatDateTime($newWarrant->start_on),
        'expiresOn' => $formatDateTime($newWarrant->expires_on),
        'memberRoleId' => $newWarrant->member_role_id !== null ? (int)$newWarrant->member_role_id : null,
        'warrantRosterId' => $newWarrant->warrant_roster_id !== null ? (int)$newWarrant->warrant_roster_id : null,
    ] : null,
    'workflowApproval' => $workflowApproval ? [
        'id' => (int)$workflowApproval->id,
        'status' => (string)$workflowApproval->status,
    ] : null,
    'workflowInstance' => $workflowInstance ? [
        'id' => (int)$workflowInstance->id,
        'status' => (string)$workflowInstance->status,
    ] : null,
], JSON_THROW_ON_ERROR);
`;

const normalizeText = (value) => value.replace(/[·\u00B7\u00A0]/g, ' ').replace(/\s+/g, ' ').trim();

const ensureFixture = (page) => {
    const fixture = page.__officerLifecycleFixture;
    if (!fixture) {
        throw new Error('Officer lifecycle fixture has not been prepared.');
    }

    return fixture;
};

const refreshFixtureState = (page) => {
    const fixture = ensureFixture(page);
    fixture.state = runPhpJson(INSPECT_FIXTURE_PHP, {
        memberEmail: fixture.memberEmail,
        officeId: fixture.officeId,
        officeName: fixture.officeName,
        branchId: fixture.branchId,
    });

    return fixture.state;
};

const waitForFixtureState = async (page, predicate, errorMessage, attempts = 10) => {
    let lastState = null;
    for (let attempt = 0; attempt < attempts; attempt += 1) {
        lastState = refreshFixtureState(page);
        if (predicate(lastState)) {
            return lastState;
        }
        await page.waitForTimeout(1000);
    }

    throw new Error(`${errorMessage}\nLast state: ${JSON.stringify(lastState)}`);
};

const ensureOverlapFixture = (page) => {
    const fixture = page.__officerOverlapFixture;
    if (!fixture) {
        throw new Error('Officer overlap fixture has not been prepared.');
    }

    return fixture;
};

const refreshOverlapFixtureState = (page) => {
    const fixture = ensureOverlapFixture(page);
    fixture.state = runPhpJson(INSPECT_OVERLAP_FIXTURE_PHP, {
        branchId: fixture.branchId,
        officeId: fixture.officeId,
        memberEmail: fixture.memberEmail,
        oldMemberEmail: fixture.oldMemberEmail,
    });

    return fixture.state;
};

const waitForOverlapFixtureState = async (page, predicate, errorMessage, attempts = 10) => {
    let lastState = null;
    for (let attempt = 0; attempt < attempts; attempt += 1) {
        lastState = refreshOverlapFixtureState(page);
        if (predicate(lastState)) {
            return lastState;
        }
        await page.waitForTimeout(1000);
    }

    throw new Error(`${errorMessage}\nLast state: ${JSON.stringify(lastState)}`);
};

const openBranchOfficersTab = async (page) => {
    const fixture = page.__officerLifecycleFixture ?? page.__officerOverlapFixture;
    if (!fixture) {
        throw new Error('No officer fixture has been prepared.');
    }
    await page.goto(`/branches/view/${fixture.branchPublicId}`, { waitUntil: 'networkidle' });
    await page.getByRole('tab', { name: 'Officers', exact: true }).click();
    await expect(page.getByRole('button', { name: /Assign Officer/i })).toBeVisible({ timeout: 15000 });
};

const chooseAutocompleteOption = async (scope, label, queryText, optionText) => {
    const input = scope.getByLabel(label, { exact: true });
    await input.click();
    await input.fill(queryText);
    const option = scope.locator('[role="option"]').filter({ hasText: optionText }).first();
    await expect(option).toBeVisible({ timeout: 15000 });
    await option.click();
};

const setOfficerAssignee = async (modal, fixture) => {
    const assignee = modal.locator('[data-officers-assign-officer-target="assignee"]');
    await assignee.evaluate((element, values) => {
        const hiddenId = element.querySelector('input[name="member_id"]');
        const hiddenText = element.querySelector('input[name="sca_name"]');
        const displayInput = element.querySelector('input[type="text"]');

        if (!hiddenId || !hiddenText || !displayInput) {
            throw new Error('Could not find assignee inputs in officer assignment modal.');
        }

        hiddenId.value = String(values.memberId);
        hiddenText.value = values.memberName;
        displayInput.value = values.memberName;

        for (const target of [hiddenId, hiddenText, displayInput, element]) {
            target.dispatchEvent(new Event('input', { bubbles: true }));
            target.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }, {
        memberId: fixture.memberId,
        memberName: fixture.memberName,
    });
};

const drainQueuedEmails = async () => {
    flushWorkflowsAndQueue();
    await waitForQueueSettled();
};

const resetDevDatabase = () => {
    // The full host-shell reset (~10 min) is redundant when the lane runner has
    // already reset+seeded the DB once up front, and it otherwise consumes the
    // entire per-test timeout. Skip it when PLAYWRIGHT_RESET_DB is explicitly
    // disabled; the fixture builds its own data via runPhpJson with a unique
    // timestamp token, so a pristine DB is not required.
    const resetFlag = (process.env.PLAYWRIGHT_RESET_DB ?? '').toLowerCase();
    if (resetFlag === '0' || resetFlag === 'false' || resetFlag === 'no') {
        return;
    }
    execFileSync(
        'bash',
        [path.join(REPO_ROOT, 'reset_dev_database.sh')],
        {
            cwd: REPO_ROOT,
            env: process.env,
            stdio: 'pipe',
        },
    );
};

const assertEmailMessage = async (page, subject, recipient, snippets) => {
    await page.goto('http://localhost:8025', { waitUntil: 'networkidle' });
    const emailRow = page.locator(`.subject b:has-text("${subject}")`).first();
    await expect(emailRow).toBeVisible({ timeout: 15000 });
    await emailRow.click();
    const toCell = page.locator('table tr').filter({ hasText: 'To' }).getByRole('link', { name: recipient, exact: true });
    await expect(toCell).toBeVisible();
    const emailBody = normalizeText(await page.locator('#nav-plain-text div').textContent());
    for (const snippet of snippets) {
        expect(emailBody).toContain(normalizeText(snippet));
    }
};

Given('I prepare the officer lifecycle fixture', async ({ page }) => {
    resetDevDatabase();
    const token = `officerworkflow${Date.now()}`;
    page.__officerLifecycleFixture = runPhpJson(SETUP_FIXTURE_PHP, { token });
    page.__officerLifecycleFixture.releaseReason = 'Workflow release regression coverage';
    refreshFixtureState(page);
});

When('I assign the officer lifecycle member', async ({ page }) => {
    const fixture = ensureFixture(page);
    await openBranchOfficersTab(page);
    await page.getByRole('button', { name: /Assign Officer/i }).click();

    const modal = page.locator('#assignOfficerModal');
    await expect(modal).toBeVisible({ timeout: 10000 });
    await chooseAutocompleteOption(modal, 'Office', fixture.officeName, fixture.officeName);
    await setOfficerAssignee(modal, fixture);
    await modal.getByLabel('Start Date', { exact: true }).fill(fixture.startDate);
    await expect(modal.getByRole('button', { name: /^Submit$/ })).toBeEnabled({ timeout: 10000 });

    await Promise.all([
        page.waitForLoadState('networkidle'),
        modal.getByRole('button', { name: /^Submit$/ }).click(),
    ]);
});

Then('the officer lifecycle should have a pending warrant approval', async ({ page }) => {
    const state = await waitForFixtureState(
        page,
        (current) => Boolean(
            current.officer &&
            current.officer.grantedMemberRoleId &&
            current.warrant &&
            current.warrant.status === 'Pending' &&
            current.workflowApproval &&
            current.workflowApproval.status === 'pending',
        ),
        'Officer lifecycle never reached a pending warrant approval state.',
    );

    expect(state.workflowApproval.requiredCount).toBe(1);
});

When('I approve the officer lifecycle warrant', async ({ page }) => {
    const state = await waitForFixtureState(
        page,
        (current) => Boolean(current.workflowApproval && current.workflowApproval.status === 'pending'),
        'No pending warrant approval was available to approve.',
    );

    if (state.workflowApproval.currentApproverEmail) {
        await loginAs(page, state.workflowApproval.currentApproverEmail);
    }
    await page.goto('/approvals', { waitUntil: 'networkidle' });
    const respondButton = page.locator(
        `button[data-outlet-btn-btn-data-value*='"id":${state.workflowApproval.id}']`,
    ).first();
    await expect(respondButton).toBeVisible({ timeout: 15000 });
    await respondButton.click();

    const modal = page.locator('#approvalResponseModal');
    await expect(modal).toBeVisible({ timeout: 10000 });
    await modal.locator('#decisionApprove').click();
    await Promise.all([
        page.waitForLoadState('networkidle'),
        modal.locator('button[type="submit"]').click(),
    ]);
});

Then('the officer lifecycle should have an active warrant', async ({ page }) => {
    const fixture = ensureFixture(page);
    const state = await waitForFixtureState(
        page,
        (current) => Boolean(
            current.officer &&
            current.officer.status === 'Current' &&
            current.warrant &&
            current.warrant.status === 'Current' &&
            current.warrant.approvedDate &&
            current.memberRole &&
            current.memberRole.id === current.officer.grantedMemberRoleId &&
            current.memberRole.id === current.warrant.memberRoleId,
        ),
        'Officer lifecycle never reached an active warrant state.',
    );

    expect(state.memberRole.roleId).toBe(fixture.roleId);
});

When('I decline the officer lifecycle warrant roster', async ({ page }) => {
    const state = await waitForFixtureState(
        page,
        (current) => Boolean(current.workflowApproval && current.workflowApproval.status === 'pending'),
        'No pending warrant approval was available to decline.',
    );

    page.__officerLifecycleDeclineReason = `Roster decline regression ${Date.now()}`;

    if (state.workflowApproval.currentApproverEmail) {
        await loginAs(page, state.workflowApproval.currentApproverEmail);
    }
    await page.goto('/approvals', { waitUntil: 'networkidle' });
    const respondButton = page.locator(
        `button[data-outlet-btn-btn-data-value*='"id":${state.workflowApproval.id}']`,
    ).first();
    await expect(respondButton).toBeVisible({ timeout: 15000 });
    await respondButton.click();

    const modal = page.locator('#approvalResponseModal');
    await expect(modal).toBeVisible({ timeout: 10000 });
    await modal.locator('#decisionReject').click();
    const comment = modal.locator('#approvalComment');
    await expect(comment).toBeVisible({ timeout: 10000 });
    await comment.fill(page.__officerLifecycleDeclineReason);
    await Promise.all([
        page.waitForLoadState('networkidle'),
        modal.locator('button[type="submit"]').click(),
    ]);
});

Then('the officer lifecycle warrant roster should be declined', async ({ page }) => {
    const state = await waitForFixtureState(
        page,
        (current) => Boolean(
            current.warrant &&
            current.warrant.status === 'Declined' &&
            current.warrant.warrantRosterId,
        ),
        'Officer lifecycle warrant never reached a declined state.',
    );

    const rosterId = state.warrant.warrantRosterId;
    const rosterStatus = dbQuery(`SELECT status FROM warrant_rosters WHERE id = ${rosterId};`).trim();
    expect(rosterStatus).toBe('Declined');

    const reason = page.__officerLifecycleDeclineReason ?? '';
    const noteBody = dbQuery(
        `SELECT body FROM notes WHERE entity_type = 'WarrantRosters' AND entity_id = ${rosterId} AND subject = 'Warrant Roster declined' ORDER BY id DESC LIMIT 1;`,
    ).trim();
    expect(noteBody).toContain(reason);
});

Then('no warrant-issued email should be queued for the officer lifecycle member', async ({ page }) => {
    const memberEmail = refreshFixtureState(page).memberEmail;
    expect(memberEmail).toBeTruthy();

    await waitForQueueSettled();
    assertNoQueuedEmailFor(memberEmail);

    const warrantIssued = await mailpitSearchTotal(
        page.request,
        `to:"${memberEmail}" subject:"Warrant Issued"`,
    );
    expect(warrantIssued).toBe(0);
});

When('I release the officer lifecycle member', async ({ page }) => {
    const fixture = ensureFixture(page);
    const currentState = refreshFixtureState(page);
    await openBranchOfficersTab(page);

    const row = page.locator('table tbody tr').filter({ hasText: fixture.memberName }).first();
    await expect(row).toBeVisible({ timeout: 15000 });
    await row.getByRole('button', { name: 'Release', exact: true }).click();

    const modal = page.locator('#releaseModal');
    await expect(modal).toBeVisible({ timeout: 10000 });
    await modal.getByLabel('Reason for Release', { exact: true }).fill(fixture.releaseReason);
    const requestPromise = page.waitForRequest((request) =>
        request.method() === 'POST' && request.url().includes('/officers/release'),
    );
    await Promise.all([
        requestPromise,
        page.waitForLoadState('networkidle'),
        modal.getByRole('button', { name: /^Submit$/ }).click(),
    ]);

    const request = await requestPromise;
    const payload = new URLSearchParams(request.postData() ?? '');
    expect(payload.get('id')).toBe(String(currentState.officer.id));
    expect(payload.get('revoked_reason')).toBe(fixture.releaseReason);
});

When('I process queued emails for the officer lifecycle', async ({ page }) => {
    await drainQueuedEmails();
});

Then('the officer lifecycle database records should show the full lifecycle', async ({ page }) => {
    const fixture = ensureFixture(page);
    const state = await waitForFixtureState(
        page,
        (current) => Boolean(
            current.officer &&
            current.officer.status === 'Released' &&
            current.officer.revokedReason === fixture.releaseReason &&
            current.warrant &&
            current.warrant.status === 'Deactivated' &&
            current.warrant.revokedReason === fixture.releaseReason &&
            current.memberRole &&
            current.memberRole.id === current.officer.grantedMemberRoleId &&
            current.memberRole.id === current.warrant.memberRoleId &&
            current.memberRole.expiresOn &&
            current.memberRole.revokerId,
        ),
        'Officer lifecycle did not finish with the expected released database state.',
        20,
    );

    expect(state.officer.startOn).toBeTruthy();
    expect(state.officer.expiresOn).toBeTruthy();
    expect(state.warrant.startOn).toBeTruthy();
    expect(state.warrant.expiresOn).toBeTruthy();
    expect(state.warrant.approvedDate).toBeTruthy();
    expect(state.memberRole.roleId).toBe(fixture.roleId);
    expect(state.memberRole.roleName).toBe(fixture.roleName);
});

Then('I should see the officer lifecycle emails', async ({ page }) => {
    const fixture = ensureFixture(page);
    const state = refreshFixtureState(page);

    await assertEmailMessage(
        page,
        state.expectedHireSubject,
        fixture.memberEmail,
        [fixture.officeName, fixture.branchName],
    );
    await assertEmailMessage(
        page,
        state.expectedWarrantSubject,
        fixture.memberEmail,
        [state.warrant.name],
    );
    await assertEmailMessage(
        page,
        state.expectedReleaseSubject,
        fixture.memberEmail,
        [fixture.releaseReason, fixture.officeName],
    );
});

Given('I prepare the officer overlap fixture for {string}', async ({ page }, caseId) => {
    resetDevDatabase();
    const token = `officeroverlap${Date.now()}${caseId.replace(/[^a-z]/g, '')}`;
    page.__officerOverlapFixture = runPhpJson(SETUP_OVERLAP_FIXTURE_PHP, { token, case: caseId });
    refreshOverlapFixtureState(page);
});

When('I assign the officer overlap replacement member', async ({ page }) => {
    const fixture = ensureOverlapFixture(page);
    await openBranchOfficersTab(page);
    await page.getByRole('button', { name: /Assign Officer/i }).click();

    const modal = page.locator('#assignOfficerModal');
    await expect(modal).toBeVisible({ timeout: 10000 });
    await chooseAutocompleteOption(modal, 'Office', fixture.officeName, fixture.officeName);
    await setOfficerAssignee(modal, fixture);
    const startDateInput = modal.getByLabel('Start Date', { exact: true });
    const endDateInput = modal.getByLabel('End Date', { exact: true });
    await startDateInput.fill(fixture.newStartDate);
    await expect(startDateInput).toHaveValue(fixture.newStartDate);
    await expect(endDateInput).toBeEnabled();
    await endDateInput.fill(fixture.newEndDate);
    await expect(endDateInput).toHaveValue(fixture.newEndDate);
    await expect(modal.getByRole('button', { name: /^Submit$/ })).toBeEnabled({ timeout: 10000 });

    const requestPromise = page.waitForRequest((request) =>
        request.method() === 'POST' && request.url().includes('/officers/assign'),
    );
    await Promise.all([
        requestPromise,
        page.waitForLoadState('networkidle'),
        modal.getByRole('button', { name: /^Submit$/ }).click(),
    ]);

    const request = await requestPromise;
    const payload = new URLSearchParams(request.postData() ?? '');
    expect(payload.get('start_on')).toBe(fixture.newStartDate);
    expect(payload.get('end_on')).toBe(fixture.newEndDate);
});

Then('the officer overlap replacement should have a pending warrant approval', async ({ page }) => {
    const state = await waitForOverlapFixtureState(
        page,
        (current) => Boolean(
            current.newOfficer &&
            current.newOfficer.grantedMemberRoleId &&
            current.newWarrant &&
            current.newWarrant.status === 'Pending' &&
            current.workflowApproval &&
            current.workflowApproval.status === 'pending',
        ),
        'Officer overlap replacement never reached a pending warrant approval state.',
    );

    expect(state.newRole.id).toBe(state.newOfficer.grantedMemberRoleId);
    expect(state.newWarrant.memberRoleId).toBe(state.newOfficer.grantedMemberRoleId);
});

When('I process queued emails for the officer overlap fixture', async ({ page }) => {
    await drainQueuedEmails();
});

Then('the officer overlap state for {string} should be correct', async ({ page }, caseId) => {
    const fixture = ensureOverlapFixture(page);
    expect(fixture.case).toBe(caseId);

    const state = await waitForOverlapFixtureState(
        page,
        (current) => Boolean(
            current.membersFound &&
            current.oldOfficer &&
            current.oldRole &&
            current.oldWarrant &&
            current.newOfficer &&
            current.newRole &&
            current.newWarrant,
        ),
        `Officer overlap fixture never produced both old and new records for ${caseId}.`,
    );

    expect(state.newOfficer.status).toBe(fixture.expectedNewOfficerStatus);
    expect(state.newOfficer.startOn).toBe(`${fixture.newStartDate} 00:00:00`);
    expect(state.newRole.id).toBe(state.newOfficer.grantedMemberRoleId);
    expect(state.newRole.roleId).toBe(fixture.roleId);
    expect(state.newWarrant.status).toBe('Pending');
    expect(state.newWarrant.memberRoleId).toBe(state.newOfficer.grantedMemberRoleId);
    expect(state.workflowApproval.status).toBe('pending');

    expect(state.oldOfficer.id).toBe(fixture.oldOfficerId);
    expect(state.oldOfficer.status).toBe(fixture.expectedOldOfficerStatus);
    expect(state.oldOfficer.startOn).toBe(fixture.expectedOldOfficerStartOn);
    expect(state.oldOfficer.expiresOn).toBe(fixture.expectedOldOfficerExpiresOn);
    expect(state.oldOfficer.revokedReason).toBe(fixture.reason);
    expect(state.oldOfficer.revokerId).toBe(1);

    expect(state.oldRole.id).toBe(fixture.oldRoleId);
    expect(state.oldRole.startOn).toBe(fixture.expectedOldOfficerStartOn);
    expect(state.oldRole.expiresOn).toBe(fixture.expectedOldOfficerExpiresOn);
    expect(state.oldRole.revokerId).toBe(1);

    expect(state.oldWarrant.id).toBe(fixture.oldWarrantId);
    expect(state.oldWarrant.status).toBe(fixture.expectedOldWarrantStatus);
    expect(state.oldWarrant.startOn).toBe(fixture.expectedOldWarrantStartOn);
    expect(state.oldWarrant.expiresOn).toBe(fixture.expectedOldWarrantExpiresOn);
    expect(state.oldWarrant.revokedReason).toBe(fixture.reason);
    expect(state.oldWarrant.revokerId).toBe(1);
    expect(state.oldWarrant.memberRoleId).toBe(fixture.oldRoleId);
});

Then('I should see the officer overlap emails for {string}', async ({ page }, caseId) => {
    const fixture = ensureOverlapFixture(page);
    expect(fixture.case).toBe(caseId);

    await assertEmailMessage(
        page,
        fixture.expectedNewNotificationSubject,
        fixture.memberEmail,
        [fixture.officeName, fixture.branchName],
    );

    if (fixture.expectedOldNotification === 'release') {
        await assertEmailMessage(
            page,
            fixture.expectedOldNotificationSubject,
            fixture.oldMemberEmail,
            [fixture.reason, fixture.officeName],
        );

        return;
    }

    await assertEmailMessage(
        page,
        fixture.expectedOldNotificationSubject,
        fixture.oldMemberEmail,
        ['scheduling adjustment', fixture.reason],
    );
});
