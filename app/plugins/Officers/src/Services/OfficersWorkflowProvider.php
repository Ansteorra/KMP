<?php

declare(strict_types=1);

namespace Officers\Services;

use App\Services\WorkflowRegistry\WorkflowActionRegistry;
use App\Services\WorkflowRegistry\WorkflowConditionRegistry;
use App\Services\WorkflowRegistry\WorkflowEntityRegistry;
use App\Services\WorkflowRegistry\WorkflowTriggerRegistry;
use Officers\Model\Entity\Officer;

/**
 * Registers Officers plugin workflow triggers, actions, conditions, and entities.
 */
class OfficersWorkflowProvider
{
    private const SOURCE = 'Officers';

    /**
     * Register all Officers workflow components with the registries.
     *
     * @return void
     */
    public static function register(): void
    {
        self::registerTriggers();
        self::registerActions();
        self::registerConditions();
        self::registerEntities();
    }

    /**
     * @return void
     */
    private static function registerTriggers(): void
    {
        WorkflowTriggerRegistry::register(self::SOURCE, [
            [
                'event' => 'Officers.HireRequested',
                'label' => 'Officer Hire Requested',
                'description' => 'When a new officer assignment is initiated',
                'payloadSchema' => [
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID'],
                    'officeId' => ['type' => 'integer', 'label' => 'Office ID'],
                    'branchId' => ['type' => 'integer', 'label' => 'Branch ID'],
                    'startOn' => ['type' => 'datetime', 'label' => 'Start Date'],
                    'expiresOn' => ['type' => 'datetime', 'label' => 'Expires On'],
                    'deputyToId' => ['type' => 'integer', 'label' => 'Deputy To Officer ID'],
                    'emailAddress' => ['type' => 'string', 'label' => 'Email Address'],
                    'deputyDescription' => ['type' => 'string', 'label' => 'Deputy Description'],
                ],
            ],
            [
                'event' => 'Officers.Released',
                'label' => 'Officer Released',
                'description' => 'When an officer is released from their position',
                'payloadSchema' => [
                    'officerId' => ['type' => 'integer', 'label' => 'Officer ID'],
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID'],
                    'officeId' => ['type' => 'integer', 'label' => 'Office ID'],
                    'releasedById' => ['type' => 'integer', 'label' => 'Released By Member ID'],
                    'reason' => ['type' => 'string', 'label' => 'Reason'],
                    'expiresOn' => ['type' => 'datetime', 'label' => 'Release Date'],
                ],
            ],
            [
                'event' => 'Officers.WarrantRequired',
                'label' => 'Officer Warrant Required',
                'description' => 'When an officer hire requires a warrant',
                'payloadSchema' => [
                    'officerId' => ['type' => 'integer', 'label' => 'Officer ID'],
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID'],
                    'officeId' => ['type' => 'integer', 'label' => 'Office ID'],
                ],
            ],
        ]);
    }

    /**
     * @return void
     */
    private static function registerActions(): void
    {
        $actionsClass = OfficerWorkflowActions::class;

        WorkflowActionRegistry::register(self::SOURCE, [
            [
                'action' => 'Officers.CreateOfficerRecord',
                'label' => 'Create Officer Assignment',
                'description' => 'Create an officer record for a member in an office',
                'inputSchema' => [
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID', 'required' => true, 'description' => 'The ID of the member being assigned'],
                    'officeId' => ['type' => 'integer', 'label' => 'Office ID', 'required' => true, 'description' => 'The ID of the office to assign'],
                    'branchId' => ['type' => 'integer', 'label' => 'Branch ID', 'required' => true, 'description' => 'The branch where the assignment applies'],
                    'startOn' => ['type' => 'datetime', 'label' => 'Start Date'],
                    'expiresOn' => ['type' => 'datetime', 'label' => 'Expires On'],
                ],
                'outputSchema' => [
                    'officerId' => ['type' => 'integer', 'label' => 'Officer ID'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'createOfficerRecord',
                'isAsync' => false,
            ],
            [
                'action' => 'Officers.ReleaseOfficer',
                'label' => 'Release Officer',
                'description' => 'Release an officer from their position',
                'inputSchema' => [
                    'officerId' => ['type' => 'integer', 'label' => 'Officer ID', 'required' => true, 'description' => 'The officer record to release'],
                    'releasedById' => ['type' => 'integer', 'label' => 'Released By Member ID'],
                    'reason' => ['type' => 'string', 'label' => 'Reason', 'default' => ''],
                    'expiresOn' => ['type' => 'datetime', 'label' => 'Release Date'],
                    'releaseStatus' => ['type' => 'string', 'label' => 'Release Status', 'default' => Officer::RELEASED_STATUS],
                ],
                'outputSchema' => [
                    'released' => ['type' => 'boolean'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'releaseOfficer',
                'isAsync' => false,
            ],
            [
                'action' => 'Officers.RequestWarrantRoster',
                'label' => 'Request Warrant Roster',
                'description' => 'Create a warrant roster for the officer and start the warrant approval workflow',
                'inputSchema' => [
                    'officerId' => ['type' => 'integer', 'label' => 'Officer ID', 'required' => true, 'description' => 'The officer record needing a warrant roster'],
                ],
                'outputSchema' => [
                    'rosterId' => ['type' => 'integer', 'label' => 'Roster ID'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'requestWarrantRoster',
                'isAsync' => false,
            ],
            [
                'action' => 'Officers.CalculateReportingFields',
                'label' => 'Calculate Reporting Fields',
                'description' => 'Calculate reporting hierarchy for an officer based on office configuration',
                'inputSchema' => [
                    'officeId' => ['type' => 'integer', 'label' => 'Office ID', 'required' => true, 'description' => 'The office to calculate reporting for'],
                    'branchId' => ['type' => 'integer', 'label' => 'Branch ID', 'required' => true, 'description' => 'The branch the officer is assigned to'],
                    'deputyDescription' => ['type' => 'string', 'label' => 'Deputy Description', 'description' => 'Optional deputy description'],
                ],
                'outputSchema' => [
                    'reports_to_office_id' => ['type' => 'integer', 'label' => 'Reports To Office ID'],
                    'reports_to_branch_id' => ['type' => 'integer', 'label' => 'Reports To Branch ID'],
                    'deputy_to_office_id' => ['type' => 'integer', 'label' => 'Deputy To Office ID'],
                    'deputy_to_branch_id' => ['type' => 'integer', 'label' => 'Deputy To Branch ID'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'calculateReportingFieldsAction',
                'isAsync' => false,
            ],
            [
                'action' => 'Officers.ReleaseConflictingOfficers',
                'label' => 'Release Conflicting Officers',
                'description' => 'Release or reshape existing officers when a one-per-branch office gets a new assignment',
                'inputSchema' => [
                    'officeId' => ['type' => 'integer', 'label' => 'Office ID', 'required' => true, 'description' => 'The office to check for conflicts'],
                    'branchId' => ['type' => 'integer', 'label' => 'Branch ID', 'required' => true, 'description' => 'The branch to check for conflicts'],
                    'newOfficerStartDate' => ['type' => 'datetime', 'label' => 'New Officer Start Date', 'description' => 'Start date of new officer; used as release date for conflicts'],
                    'newOfficerEndDate' => ['type' => 'datetime', 'label' => 'New Officer End Date', 'description' => 'End date of new officer; used for overlap trimming and push-back decisions'],
                ],
                'outputSchema' => [
                    'releasedOfficerIds' => ['type' => 'array', 'label' => 'Released Officer IDs'],
                    'adjustedOfficerIds' => ['type' => 'array', 'label' => 'Adjusted Officer IDs'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'releaseConflictingOfficers',
                'isAsync' => false,
            ],
            [
                'action' => 'Officers.RecalculateOfficersForOffice',
                'label' => 'Recalculate Officers For Office',
                'description' => 'Batch recalculate reporting fields and roles for all officers when office config changes',
                'inputSchema' => [
                    'officeId' => ['type' => 'integer', 'label' => 'Office ID', 'required' => true, 'description' => 'The office whose officers need recalculation'],
                    'updaterId' => ['type' => 'integer', 'label' => 'Updater ID', 'description' => 'The user who triggered the recalculation'],
                ],
                'outputSchema' => [
                    'updatedCount' => ['type' => 'integer', 'label' => 'Updated Count'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'recalculateOfficersForOffice',
                'isAsync' => false,
            ],
            [
                'action' => 'Officers.PrepareHireNotificationVars',
                'label' => 'Prepare Hire Notification Vars',
                'description' => 'Load officer, member, and branch data and format all variables needed by the officer-hire-notification email template',
                'inputSchema' => [
                    'officerId' => ['type' => 'integer', 'label' => 'Officer ID', 'required' => true],
                ],
                'outputSchema' => [
                    'to' => ['type' => 'string', 'label' => 'Recipient Email'],
                    'memberScaName' => ['type' => 'string', 'label' => 'Member SCA Name'],
                    'officeName' => ['type' => 'string', 'label' => 'Office Name'],
                    'branchName' => ['type' => 'string', 'label' => 'Branch Name'],
                    'hireDate' => ['type' => 'string', 'label' => 'Hire Date (formatted)'],
                    'endDate' => ['type' => 'string', 'label' => 'End Date (formatted)'],
                    'requiresWarrantNotice' => ['type' => 'string', 'label' => 'Warrant Notice (empty if not required)'],
                    'siteAdminSignature' => ['type' => 'string', 'label' => 'Site Admin Signature'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'prepareHireNotificationVars',
                'isAsync' => false,
            ],
            [
                'action' => 'Officers.PrepareReleaseNotificationVars',
                'label' => 'Prepare Release Notification Vars',
                'description' => 'Load officer, member, and branch data and format all variables needed by the officer-release-notification email template',
                'inputSchema' => [
                    'officerId' => ['type' => 'integer', 'label' => 'Officer ID', 'required' => true],
                    'reason' => ['type' => 'string', 'label' => 'Release Reason'],
                ],
                'outputSchema' => [
                    'to' => ['type' => 'string', 'label' => 'Recipient Email'],
                    'memberScaName' => ['type' => 'string', 'label' => 'Member SCA Name'],
                    'officeName' => ['type' => 'string', 'label' => 'Office Name'],
                    'branchName' => ['type' => 'string', 'label' => 'Branch Name'],
                    'reason' => ['type' => 'string', 'label' => 'Release Reason'],
                    'releaseDate' => ['type' => 'string', 'label' => 'Release Date (formatted)'],
                    'siteAdminSignature' => ['type' => 'string', 'label' => 'Site Admin Signature'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'prepareReleaseNotificationVars',
                'isAsync' => false,
            ],
        ]);
    }

    /**
     * @return void
     */
    private static function registerConditions(): void
    {
        $conditionsClass = OfficerWorkflowConditions::class;

        WorkflowConditionRegistry::register(self::SOURCE, [
            [
                'condition' => 'Officers.OfficeRequiresWarrant',
                'label' => 'Office Requires Warrant',
                'description' => 'Check if the office requires a warrant for the officer',
                'inputSchema' => [
                    'officeId' => ['type' => 'integer', 'label' => 'Office ID', 'required' => true, 'description' => 'The office to check for warrant requirements'],
                ],
                'evaluatorClass' => $conditionsClass,
                'evaluatorMethod' => 'officeRequiresWarrant',
            ],
            [
                'condition' => 'Officers.IsOnlyOnePerBranch',
                'label' => 'Office Only One Per Branch',
                'description' => 'Check if this office allows only one officer per branch',
                'inputSchema' => [
                    'officeId' => ['type' => 'integer', 'label' => 'Office ID', 'required' => true, 'description' => 'The office to check for one-per-branch rule'],
                ],
                'evaluatorClass' => $conditionsClass,
                'evaluatorMethod' => 'isOnlyOnePerBranch',
            ],
            [
                'condition' => 'Officers.IsMemberWarrantable',
                'label' => 'Member Is Warrantable',
                'description' => 'Check if a member meets warrant eligibility requirements',
                'inputSchema' => [
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID', 'required' => true, 'description' => 'The member to check warrant eligibility for'],
                ],
                'evaluatorClass' => $conditionsClass,
                'evaluatorMethod' => 'isMemberWarrantable',
            ],
            [
                'condition' => 'Officers.HasConflictingOfficer',
                'label' => 'Has Conflicting Officer',
                'description' => 'Check if a branch already has a current or upcoming officer overlapping the incoming hire window',
                'inputSchema' => [
                    'officeId' => ['type' => 'integer', 'label' => 'Office ID', 'required' => true, 'description' => 'The office to check for conflicts'],
                    'branchId' => ['type' => 'integer', 'label' => 'Branch ID', 'required' => true, 'description' => 'The branch to check for conflicts'],
                    'newOfficerStartDate' => ['type' => 'datetime', 'label' => 'New Officer Start Date', 'required' => true, 'description' => 'Start date of the proposed hire'],
                    'newOfficerEndDate' => ['type' => 'datetime', 'label' => 'New Officer End Date', 'description' => 'Optional end date of the proposed hire'],
                ],
                'evaluatorClass' => $conditionsClass,
                'evaluatorMethod' => 'hasConflictingOfficer',
            ],
        ]);
    }

    /**
     * @return void
     */
    private static function registerEntities(): void
    {
        WorkflowEntityRegistry::register(self::SOURCE, [
            [
                'entityType' => 'Officers.Officers',
                'label' => 'Officer',
                'description' => 'Officer assignment record',
                'tableClass' => \Officers\Model\Table\OfficersTable::class,
                'fields' => [
                    'id' => ['type' => 'integer', 'label' => 'ID'],
                    'member_id' => ['type' => 'integer', 'label' => 'Member ID'],
                    'office_id' => ['type' => 'integer', 'label' => 'Office ID'],
                    'branch_id' => ['type' => 'integer', 'label' => 'Branch ID'],
                    'status' => ['type' => 'string', 'label' => 'Status'],
                    'start_on' => ['type' => 'datetime', 'label' => 'Start Date'],
                    'expires_on' => ['type' => 'datetime', 'label' => 'Expires On'],
                ],
            ],
            [
                'entityType' => 'Officers.Offices',
                'label' => 'Office',
                'description' => 'Office definition',
                'tableClass' => \Officers\Model\Table\OfficesTable::class,
                'fields' => [
                    'id' => ['type' => 'integer', 'label' => 'ID'],
                    'name' => ['type' => 'string', 'label' => 'Name'],
                    'requires_warrant' => ['type' => 'boolean', 'label' => 'Requires Warrant'],
                    'only_one_per_branch' => ['type' => 'boolean', 'label' => 'Only One Per Branch'],
                    'term_length' => ['type' => 'integer', 'label' => 'Term Length (months)'],
                ],
            ],
        ]);
    }
}
