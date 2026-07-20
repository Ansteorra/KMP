<?php

declare(strict_types=1);

use Migrations\AbstractMigration;
use App\Migrations\CrossEngineMigrationTrait;

/**
 * Seed default workflow definitions and their initial published versions.
 *
 * Creates the warrant-roster approval workflow.
 */
class SeedWorkflowDefinitions extends AbstractMigration
{
    use CrossEngineMigrationTrait;

    public function up(): void
    {
        $now = date('Y-m-d H:i:s');

        // Warrant Roster Approval workflow
        $name = 'Warrant Roster Approval';
        $slug = 'warrant-roster';
        $desc = 'Default workflow for warrant roster approval process';
        $triggerConfig = $this->sqlEscape(json_encode(['event' => 'Warrants.RosterCreated']));
        $entityType = 'Warrants';
        $definition = $this->sqlEscape(json_encode($this->getWarrantRosterDefinition()));

        $this->execute(
            "INSERT INTO workflow_definitions (name, slug, description, trigger_type, trigger_config, entity_type, is_active, current_version_id, created_by, modified_by, created, modified) " .
                "VALUES ('{$name}', '{$slug}', '{$desc}', 'event', '{$triggerConfig}', '{$entityType}', TRUE, NULL, 1, 1, '{$now}', '{$now}')"
        );

        $this->execute(
            "INSERT INTO workflow_versions (workflow_definition_id, version_number, definition, canvas_layout, status, published_at, published_by, created_by, created, modified) " .
                "VALUES ((SELECT id FROM workflow_definitions WHERE slug = '{$slug}'), 1, '{$definition}', '{}', 'published', '{$now}', 1, 1, '{$now}', '{$now}')"
        );

        $this->execute(
            "UPDATE workflow_definitions SET current_version_id = (" .
                "SELECT wv.id FROM workflow_versions wv " .
                "INNER JOIN workflow_definitions wd ON wv.workflow_definition_id = wd.id " .
                "WHERE wd.slug = '{$slug}' AND wv.version_number = 1" .
                ") WHERE slug = '{$slug}'"
        );

        // Officer Hire workflow
        $name2 = 'Officer Hire';
        $slug2 = 'officer-hire';
        $desc2 = 'Default workflow for officer hiring: validates warrantability, creates officer record, sends notification, and requests warrant if required';
        $triggerConfig2 = $this->sqlEscape(json_encode(['event' => 'Officers.HireRequested']));
        $entityType2 = 'Officers';
        $definition2 = $this->sqlEscape(json_encode($this->getOfficerHireDefinition()));

        $this->execute(
            "INSERT INTO workflow_definitions (name, slug, description, trigger_type, trigger_config, entity_type, is_active, current_version_id, created_by, modified_by, created, modified) " .
                "VALUES ('{$name2}', '{$slug2}', '{$desc2}', 'event', '{$triggerConfig2}', '{$entityType2}', TRUE, NULL, 1, 1, '{$now}', '{$now}')"
        );

        $this->execute(
            "INSERT INTO workflow_versions (workflow_definition_id, version_number, definition, canvas_layout, status, published_at, published_by, created_by, created, modified) " .
                "VALUES ((SELECT id FROM workflow_definitions WHERE slug = '{$slug2}'), 1, '{$definition2}', '{}', 'published', '{$now}', 1, 1, '{$now}', '{$now}')"
        );

        $this->execute(
            "UPDATE workflow_definitions SET current_version_id = (" .
                "SELECT wv.id FROM workflow_versions wv " .
                "INNER JOIN workflow_definitions wd ON wv.workflow_definition_id = wd.id " .
                "WHERE wd.slug = '{$slug2}' AND wv.version_number = 1" .
                ") WHERE slug = '{$slug2}'"
        );

        // Activities Authorization Approval workflow
        $name3 = 'Activities Authorization Approval';
        $slug3 = 'activities-authorization';
        $desc3 = 'Default workflow for activity authorization approval with serial pick-next approver chain';
        $triggerConfig3 = $this->sqlEscape(json_encode(['event' => 'Activities.AuthorizationRequested']));
        $entityType3 = 'Activities';
        $definition3 = $this->sqlEscape(json_encode($this->getActivitiesAuthorizationDefinition()));

        $this->execute(
            "INSERT INTO workflow_definitions (name, slug, description, trigger_type, trigger_config, entity_type, is_active, current_version_id, created_by, modified_by, created, modified) " .
                "VALUES ('{$name3}', '{$slug3}', '{$desc3}', 'event', '{$triggerConfig3}', '{$entityType3}', TRUE, NULL, 1, 1, '{$now}', '{$now}')"
        );

        $this->execute(
            "INSERT INTO workflow_versions (workflow_definition_id, version_number, definition, canvas_layout, status, published_at, published_by, created_by, created, modified) " .
                "VALUES ((SELECT id FROM workflow_definitions WHERE slug = '{$slug3}'), 1, '{$definition3}', '{}', 'published', '{$now}', 1, 1, '{$now}', '{$now}')"
        );

        $this->execute(
            "UPDATE workflow_definitions SET current_version_id = (" .
                "SELECT wv.id FROM workflow_versions wv " .
                "INNER JOIN workflow_definitions wd ON wv.workflow_definition_id = wd.id " .
                "WHERE wd.slug = '{$slug3}' AND wv.version_number = 1" .
                ") WHERE slug = '{$slug3}'"
        );
    }

    public function down(): void
    {
        foreach (['warrant-roster', 'officer-hire', 'activities-authorization'] as $slug) {
            $this->execute("UPDATE workflow_definitions SET current_version_id = NULL WHERE slug = '{$slug}'");
            $this->execute("DELETE FROM workflow_versions WHERE workflow_definition_id IN (SELECT id FROM workflow_definitions WHERE slug = '{$slug}')");
            $this->execute("DELETE FROM workflow_definitions WHERE slug = '{$slug}'");
        }
    }

    private function getWarrantRosterDefinition(): array
    {
        return [
            'nodes' => [
                'trigger-1' => [
                    'type' => 'trigger',
                    'label' => 'Warrant Roster Created',
                    'config' => [
                        'event' => 'Warrants.RosterCreated',
                        'entityIdField' => 'rosterId',
                        'inputMapping' => [
                            'rosterId' => '$.event.rosterId',
                            'rosterName' => '$.event.rosterName',
                            'approvalsRequired' => '$.event.approvalsRequired',
                        ],
                    ],
                    'position' => ['x' => 50, 'y' => 200],
                    'outputs' => [
                        ['port' => 'next', 'target' => 'approval-1'],
                    ],
                ],
                'approval-1' => [
                    'type' => 'approval',
                    'label' => 'Warrant Roster Approval',
                    'config' => [
                        'approverType' => 'policy',
                        'permission' => 'Can Approve Warrant Rosters',
                        'policyClass' => 'App\\Policy\\WarrantRosterPolicy',
                        'policyAction' => 'canApprove',
                        'entityTable' => 'WarrantRosters',
                        'entityIdKey' => 'trigger.rosterId',
                        'requiredCount' => ['type' => 'app_setting', 'key' => 'Warrant.RosterApprovalsRequired'],
                        'parallel' => true,
                        'deadline' => '14d',
                        'allowComments' => true,
                    ],
                    'position' => ['x' => 350, 'y' => 200],
                    'outputs' => [
                        ['port' => 'approved', 'target' => 'action-activate'],
                        ['port' => 'rejected', 'target' => 'action-decline'],
                    ],
                ],
                'action-activate' => [
                    'type' => 'action',
                    'label' => 'Activate Warrants',
                    'config' => [
                        'action' => 'Warrants.ActivateWarrants',
                        'params' => ['rosterId' => '$.trigger.rosterId'],
                    ],
                    'position' => ['x' => 650, 'y' => 100],
                    'outputs' => [
                        ['port' => 'next', 'target' => 'action-notify-approved'],
                    ],
                ],
                'action-notify-approved' => [
                    'type' => 'action',
                    'label' => 'Notify Warrant Issued',
                    'config' => [
                        'action' => 'Warrants.NotifyWarrantIssued',
                        'params' => [
                            'rosterId' => '$.trigger.rosterId',
                        ],
                    ],
                    'position' => ['x' => 950, 'y' => 100],
                    'outputs' => [
                        ['port' => 'next', 'target' => 'end-1'],
                    ],
                ],
                'action-decline' => [
                    'type' => 'action',
                    'label' => 'Decline Warrants',
                    'config' => [
                        'action' => 'Warrants.DeclineRoster',
                        'params' => [
                            'rosterId' => '$.trigger.rosterId',
                            'reason' => '$.nodes.approval-1.comment',
                            'rejecterId' => '$.nodes.approval-1.approverId',
                        ],
                    ],
                    'position' => ['x' => 650, 'y' => 300],
                    'outputs' => [
                        ['port' => 'next', 'target' => 'end-1'],
                    ],
                ],
                'end-1' => [
                    'type' => 'end',
                    'label' => 'Complete',
                    'config' => (object)[],
                    'position' => ['x' => 1200, 'y' => 200],
                    'outputs' => [],
                ],
            ],
        ];
    }

    private function getActivitiesAuthorizationDefinition(): array
    {
        return [
            'nodes' => [
                'trigger-auth' => [
                    'type' => 'trigger',
                    'label' => 'Authorization Requested',
                    'config' => [
                        'event' => 'Activities.AuthorizationRequested',
                        'entityIdField' => 'authorizationId',
                        'inputMapping' => [
                            'authorizationId' => '$.event.authorizationId',
                            'memberId' => '$.event.memberId',
                            'activityId' => '$.event.activityId',
                            'activityName' => '$.event.activityName',
                            'approverId' => '$.event.approverId',
                            'isRenewal' => '$.event.isRenewal',
                            'requiredApprovals' => '$.event.requiredApprovals',
                        ],
                    ],
                    'position' => ['x' => 50, 'y' => 200],
                    'outputs' => [
                        ['port' => 'next', 'target' => 'approval-gate'],
                    ],
                ],
                'approval-gate' => [
                    'type' => 'approval',
                    'label' => 'Authorization Approval Gate',
                    'config' => [
                        'approverType' => 'dynamic',
                        'approverConfig' => [
                            'service' => 'Activities.AuthorizationApproverResolver',
                            'method' => 'getEligibleApproverIds',
                            'activity_id' => '$.trigger.activityId',
                        ],
                        'requiredCount' => '$.trigger.requiredApprovals',
                        'serialPickNext' => true,
                        'allowParallel' => false,
                        'allowComments' => true,
                        'deadline' => '30d',
                    ],
                    'position' => ['x' => 350, 'y' => 200],
                    'outputs' => [
                        ['port' => 'approved', 'target' => 'action-activate'],
                        ['port' => 'rejected', 'target' => 'action-deny'],
                        ['port' => 'on_each_approval', 'target' => 'action-notify-step'],
                    ],
                ],
                'action-notify-step' => [
                    'type' => 'action',
                    'label' => 'Notify Step Approved',
                    'config' => [
                        'action' => 'Activities.NotifyRequester',
                        'params' => [
                            'activityId' => '$.trigger.activityId',
                            'requesterId' => '$.trigger.memberId',
                            'approverId' => '$.nodes.approval-gate.approverId',
                            'status' => 'pending',
                            'nextApproverId' => '$.nodes.approval-gate.nextApproverId',
                        ],
                    ],
                    'position' => ['x' => 350, 'y' => 450],
                    'outputs' => [],
                ],
                'action-activate' => [
                    'type' => 'action',
                    'label' => 'Activate Authorization',
                    'config' => [
                        'action' => 'Activities.ActivateAuthorization',
                        'params' => [
                            'authorizationId' => '$.trigger.authorizationId',
                            'approverId' => '$.resumeData.approverId',
                        ],
                    ],
                    'position' => ['x' => 650, 'y' => 100],
                    'outputs' => [
                        ['port' => 'next', 'target' => 'action-notify-approved'],
                    ],
                ],
                'action-notify-approved' => [
                    'type' => 'action',
                    'label' => 'Notify Requester (Approved)',
                    'config' => [
                        'action' => 'Activities.NotifyRequester',
                        'params' => [
                            'activityId' => '$.trigger.activityId',
                            'requesterId' => '$.trigger.memberId',
                            'approverId' => '$.resumeData.approverId',
                            'status' => 'approved',
                        ],
                    ],
                    'position' => ['x' => 950, 'y' => 100],
                    'outputs' => [
                        ['port' => 'next', 'target' => 'end-approved'],
                    ],
                ],
                'action-deny' => [
                    'type' => 'action',
                    'label' => 'Handle Denial',
                    'config' => [
                        'action' => 'Activities.HandleDenial',
                        'params' => [
                            'authorizationId' => '$.trigger.authorizationId',
                            'approverId' => '$.resumeData.approverId',
                            'denyReason' => '$.resumeData.comment',
                        ],
                    ],
                    'position' => ['x' => 650, 'y' => 300],
                    'outputs' => [
                        ['port' => 'next', 'target' => 'action-notify-denied'],
                    ],
                ],
                'action-notify-denied' => [
                    'type' => 'action',
                    'label' => 'Notify Requester (Denied)',
                    'config' => [
                        'action' => 'Activities.NotifyRequester',
                        'params' => [
                            'activityId' => '$.trigger.activityId',
                            'requesterId' => '$.trigger.memberId',
                            'approverId' => '$.resumeData.approverId',
                            'status' => 'denied',
                        ],
                    ],
                    'position' => ['x' => 950, 'y' => 300],
                    'outputs' => [
                        ['port' => 'next', 'target' => 'end-denied'],
                    ],
                ],
                'end-approved' => [
                    'type' => 'end',
                    'label' => 'Approved Complete',
                    'config' => (object)[],
                    'position' => ['x' => 1250, 'y' => 100],
                    'outputs' => [],
                ],
                'end-denied' => [
                    'type' => 'end',
                    'label' => 'Denied Complete',
                    'config' => (object)[],
                    'position' => ['x' => 1250, 'y' => 300],
                    'outputs' => [],
                ],
            ],
        ];
    }

    private function getOfficerHireDefinition(): array
    {
        return [
            'nodes' => [
                'trigger-hire' => [
                    'type' => 'trigger',
                    'label' => 'Officer Hire Requested',
                    'config' => [
                        'event' => 'Officers.HireRequested',
                        'entityIdField' => 'officerId',
                        'inputMapping' => [
                            'memberId' => '$.event.memberId',
                            'officeId' => '$.event.officeId',
                            'branchId' => '$.event.branchId',
                            'startOn' => '$.event.startOn',
                            'expiresOn' => '$.event.expiresOn',
                            'deputyToId' => '$.event.deputyToId',
                            'officerId' => '$.event.officerId',
                            'emailAddress' => '$.event.emailAddress',
                            'deputyDescription' => '$.event.deputyDescription',
                        ],
                    ],
                    'position' => ['x' => 50, 'y' => 250],
                    'outputs' => [
                        ['port' => 'next', 'target' => 'condition-requires-warrant'],
                    ],
                ],
                'condition-requires-warrant' => [
                    'type' => 'condition',
                    'label' => 'Office Requires Warrant?',
                    'config' => [
                        'condition' => 'Officers.OfficeRequiresWarrant',
                        'officeId' => '$.trigger.officeId',
                    ],
                    'position' => ['x' => 350, 'y' => 250],
                    'outputs' => [
                        ['port' => 'true', 'target' => 'condition-warrantable', 'label' => 'Yes'],
                        ['port' => 'false', 'target' => 'action-create-officer', 'label' => 'No'],
                    ],
                ],
                'condition-warrantable' => [
                    'type' => 'condition',
                    'label' => 'Member Warrantable?',
                    'config' => [
                        'condition' => 'Officers.IsMemberWarrantable',
                        'memberId' => '$.trigger.memberId',
                    ],
                    'position' => ['x' => 650, 'y' => 150],
                    'outputs' => [
                        ['port' => 'true', 'target' => 'action-create-officer', 'label' => 'Yes'],
                        ['port' => 'false', 'target' => 'end-rejected', 'label' => 'No'],
                    ],
                ],
                'action-create-officer' => [
                    'type' => 'action',
                    'label' => 'Create Officer Record',
                    'config' => [
                        'action' => 'Officers.CreateOfficerRecord',
                        'params' => [
                            'memberId' => '$.trigger.memberId',
                            'officeId' => '$.trigger.officeId',
                            'branchId' => '$.trigger.branchId',
                            'startOn' => '$.trigger.startOn',
                            'expiresOn' => '$.trigger.expiresOn',
                            'emailAddress' => '$.trigger.emailAddress',
                            'deputyDescription' => '$.trigger.deputyDescription',
                        ],
                    ],
                    'position' => ['x' => 950, 'y' => 250],
                    'outputs' => [
                        ['port' => 'next', 'target' => 'action-send-email'],
                    ],
                ],
                'action-send-email' => [
                    'type' => 'action',
                    'label' => 'Send Hire Notification',
                    'config' => [
                        'action' => 'Officers.SendHireNotification',
                        'params' => [
                            'officerId' => '$.nodes.action-create-officer.result.officerId',
                        ],
                    ],
                    'position' => ['x' => 1250, 'y' => 250],
                    'outputs' => [
                        ['port' => 'next', 'target' => 'condition-warrant-needed'],
                    ],
                ],
                'condition-warrant-needed' => [
                    'type' => 'condition',
                    'label' => 'Warrant Required?',
                    'config' => [
                        'condition' => 'Officers.OfficeRequiresWarrant',
                        'officeId' => '$.trigger.officeId',
                    ],
                    'position' => ['x' => 1550, 'y' => 250],
                    'outputs' => [
                        ['port' => 'true', 'target' => 'action-request-warrant', 'label' => 'Yes'],
                        ['port' => 'false', 'target' => 'end-complete', 'label' => 'No'],
                    ],
                ],
                'action-request-warrant' => [
                    'type' => 'action',
                    'label' => 'Request Warrant Roster',
                    'config' => [
                        'action' => 'Officers.RequestWarrantRoster',
                        'params' => [
                            'officerId' => '$.nodes.action-create-officer.result.officerId',
                        ],
                    ],
                    'position' => ['x' => 1850, 'y' => 150],
                    'outputs' => [
                        ['port' => 'next', 'target' => 'end-complete'],
                    ],
                ],
                'end-rejected' => [
                    'type' => 'end',
                    'label' => 'Rejected — Not Warrantable',
                    'config' => (object)[],
                    'position' => ['x' => 950, 'y' => 50],
                    'outputs' => [],
                ],
                'end-complete' => [
                    'type' => 'end',
                    'label' => 'Complete',
                    'config' => (object)[],
                    'position' => ['x' => 2100, 'y' => 250],
                    'outputs' => [],
                ],
            ],
        ];
    }
}