<?php
declare(strict_types=1);

namespace App\Services\WorkflowEngine\Providers;

use App\Model\Table\MembersTable;
use App\Services\WorkflowRegistry\WorkflowActionRegistry;
use App\Services\WorkflowRegistry\WorkflowConditionRegistry;
use App\Services\WorkflowRegistry\WorkflowEntityRegistry;
use App\Services\WorkflowRegistry\WorkflowTriggerRegistry;

/**
 * Registers member lifecycle workflow triggers, actions, conditions, and entities.
 */
class MembersWorkflowProvider
{
    private const SOURCE = 'Members';

    /**
     * Register all member workflow components.
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
                'event' => 'Members.Registered',
                'label' => 'Member Registered',
                'description' => 'When a new member completes registration',
                'payloadSchema' => [
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID'],
                    'status' => ['type' => 'string', 'label' => 'Assigned Status'],
                    'isMinor' => ['type' => 'boolean', 'label' => 'Is Minor'],
                    'source' => ['type' => 'string', 'label' => 'Registration Source'],
                ],
            ],
            [
                'event' => 'Members.PasswordResetRequested',
                'label' => 'Password Reset Requested',
                'description' => 'When a member requests a password reset',
                'payloadSchema' => [
                    'emailAddress' => ['type' => 'string', 'label' => 'Email Address'],
                    'resetUrl' => ['type' => 'string', 'label' => 'Reset URL'],
                ],
            ],
            [
                'event' => 'Members.MembershipVerified',
                'label' => 'Membership Verified',
                'description' => 'When a member\'s membership is verified by a secretary',
                'payloadSchema' => [
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID'],
                    'status' => ['type' => 'string', 'label' => 'New Status'],
                    'verifiedBy' => ['type' => 'integer', 'label' => 'Verified By Member ID'],
                ],
            ],
            [
                'event' => 'Members.AgeUpTriggered',
                'label' => 'Age-Up Triggered (Scheduled)',
                'description' => 'Scheduled trigger to transition minors who have turned 18',
                'payloadSchema' => [
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID'],
                    'previousStatus' => ['type' => 'string', 'label' => 'Previous Status'],
                ],
            ],
            [
                'event' => 'Members.WarrantableSyncTriggered',
                'label' => 'Warrantable Sync Triggered (Scheduled)',
                'description' => 'Scheduled trigger to recalculate warrantable eligibility',
                'payloadSchema' => [
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID'],
                ],
            ],
        ]);
    }

    /**
     * @return void
     */
    private static function registerActions(): void
    {
        $actionsClass = MembersWorkflowActions::class;

        WorkflowActionRegistry::register(self::SOURCE, [
            [
                'action' => 'Members.Register',
                'label' => 'Register Member',
                'description' => 'Create and register a new member with age-based status assignment',
                'inputSchema' => [
                    'scaName' => ['type' => 'string', 'label' => 'SCA Name'],
                    'firstName' => ['type' => 'string', 'label' => 'First Name'],
                    'lastName' => ['type' => 'string', 'label' => 'Last Name'],
                    'emailAddress' => ['type' => 'string', 'label' => 'Email Address', 'required' => true],
                    'branchId' => ['type' => 'integer', 'label' => 'Branch ID'],
                    'birthMonth' => ['type' => 'integer', 'label' => 'Birth Month', 'required' => true],
                    'birthYear' => ['type' => 'integer', 'label' => 'Birth Year', 'required' => true],
                    'phoneNumber' => ['type' => 'string', 'label' => 'Phone Number'],
                    'streetAddress' => ['type' => 'string', 'label' => 'Street Address'],
                    'city' => ['type' => 'string', 'label' => 'City'],
                    'state' => ['type' => 'string', 'label' => 'State'],
                    'zip' => ['type' => 'string', 'label' => 'Zip Code'],
                    'middleName' => ['type' => 'string', 'label' => 'Middle Name'],
                ],
                'outputSchema' => [
                    'memberId' => ['type' => 'integer', 'label' => 'Created Member ID'],
                    'status' => ['type' => 'string', 'label' => 'Assigned Status'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'register',
                'isAsync' => false,
            ],
            [
                'action' => 'Members.Activate',
                'label' => 'Activate Member',
                'description' => 'Activate a member account (set status to active)',
                'inputSchema' => [
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID', 'required' => true],
                ],
                'outputSchema' => [
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID'],
                    'status' => ['type' => 'string', 'label' => 'New Status'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'activate',
                'isAsync' => false,
            ],
            [
                'action' => 'Members.Deactivate',
                'label' => 'Deactivate Member',
                'description' => 'Deactivate a member account and mask email address',
                'inputSchema' => [
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID', 'required' => true],
                ],
                'outputSchema' => [
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID'],
                    'status' => ['type' => 'string', 'label' => 'New Status'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'deactivate',
                'isAsync' => false,
            ],
            [
                'action' => 'Members.SendPasswordReset',
                'label' => 'Send Password Reset',
                'description' => 'Generate a password reset token and prepare reset email variables',
                'inputSchema' => [
                    'emailAddress' => ['type' => 'string', 'label' => 'Email Address', 'required' => true],
                ],
                'outputSchema' => [
                    'email' => ['type' => 'string', 'label' => 'Member Email'],
                    'resetUrl' => ['type' => 'string', 'label' => 'Password Reset URL'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'sendPasswordReset',
                'isAsync' => false,
            ],
            [
                'action' => 'Members.ValidatePasswordReset',
                'label' => 'Validate Password Reset Token',
                'description' => 'Validate a password reset token and return member info',
                'inputSchema' => [
                    'token' => ['type' => 'string', 'label' => 'Reset Token', 'required' => true],
                ],
                'outputSchema' => [
                    'valid' => ['type' => 'boolean', 'label' => 'Token Valid'],
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'validatePasswordReset',
                'isAsync' => false,
            ],
            [
                'action' => 'Members.AgeUpMember',
                'label' => 'Age Up Member',
                'description' => 'Transition a single minor member to adult status if eligible',
                'inputSchema' => [
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID', 'required' => true],
                ],
                'outputSchema' => [
                    'transitioned' => ['type' => 'boolean', 'label' => 'Status Changed'],
                    'previousStatus' => ['type' => 'string', 'label' => 'Previous Status'],
                    'status' => ['type' => 'string', 'label' => 'New Status'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'ageUpMember',
                'isAsync' => false,
            ],
            [
                'action' => 'Members.SyncWarrantableStatus',
                'label' => 'Sync Warrantable Status',
                'description' => 'Recalculate warrant eligibility flag for a member',
                'inputSchema' => [
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID', 'required' => true],
                ],
                'outputSchema' => [
                    'warrantable' => ['type' => 'boolean', 'label' => 'Is Warrantable'],
                    'changed' => ['type' => 'boolean', 'label' => 'Status Changed'],
                    'reasons' => ['type' => 'array', 'label' => 'Non-Warrantable Reasons'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'syncWarrantableStatus',
                'isAsync' => false,
            ],
            [
                'action' => 'Members.VerifyMembership',
                'label' => 'Verify Membership',
                'description' => 'Process membership and/or parent verification for a member',
                'inputSchema' => [
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID', 'required' => true],
                    'verifyMembership' => ['type' => 'boolean', 'label' => 'Verify Membership', 'default' => false],
                    'verifyParent' => ['type' => 'boolean', 'label' => 'Verify Parent', 'default' => false],
                    'membershipNumber' => ['type' => 'string', 'label' => 'Membership Number'],
                    'membershipExpiresOn' => ['type' => 'datetime', 'label' => 'Membership Expiration Date'],
                    'parentId' => ['type' => 'integer', 'label' => 'Parent Member ID'],
                    'verifiedBy' => ['type' => 'integer', 'label' => 'Verified By Member ID'],
                ],
                'outputSchema' => [
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID'],
                    'status' => ['type' => 'string', 'label' => 'New Status'],
                    'verified' => ['type' => 'boolean', 'label' => 'Verification Successful'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'verifyMembership',
                'isAsync' => false,
            ],
            [
                'action' => 'Members.UpdateMemberField',
                'label' => 'Update Member Field',
                'description' => 'Generic field update on a member entity',
                'inputSchema' => [
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID', 'required' => true],
                    'fields' => [
                        'type' => 'object',
                        'label' => 'Fields to Update',
                        'required' => true,
                        'description' => 'Key-value pairs of field names and values',
                    ],
                ],
                'outputSchema' => [
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID'],
                    'updatedFields' => ['type' => 'array', 'label' => 'Updated Field Names'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'updateMemberField',
                'isAsync' => false,
            ],
            [
                'action' => 'Members.AssignStatusAndTokens',
                'label' => 'Assign Status and Tokens',
                'description' => 'Assign age-based status and generate auth tokens for an existing member',
                'inputSchema' => [
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID', 'required' => true],
                ],
                'outputSchema' => [
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID'],
                    'status' => ['type' => 'string', 'label' => 'Assigned Status'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'assignStatusAndTokens',
                'isAsync' => false,
            ],
            [
                'action' => 'Members.PrepareRegistrationEmailVars',
                'label' => 'Prepare Registration Email Vars',
                'description' => 'Build all vars needed for registration email templates'
                    . ' (welcome, secretary, minor-secretary)'
                    . ' and resolve secretary addresses from app settings',
                'inputSchema' => [
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID', 'required' => true],
                ],
                'outputSchema' => [
                    'email' => ['type' => 'string', 'label' => 'Member Email'],
                    'passwordResetUrl' => ['type' => 'string', 'label' => 'Password Reset URL'],
                    'memberScaName' => ['type' => 'string', 'label' => 'Member SCA Name'],
                    'memberViewUrl' => ['type' => 'string', 'label' => 'Member Profile URL'],
                    'memberCardPresent' => ['type' => 'string', 'label' => 'Membership Card Status'],
                    'adultSecretaryEmail' => ['type' => 'string', 'label' => 'Adult Secretary Email'],
                    'minorSecretaryEmail' => ['type' => 'string', 'label' => 'Minor Secretary Email'],
                    'siteAdminSignature' => ['type' => 'string', 'label' => 'Site Admin Signature'],
                    'portalName' => ['type' => 'string', 'label' => 'Portal Name'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'prepareRegistrationEmailVars',
                'isAsync' => false,
            ],
            [
                'action' => 'Members.SendRegistrationNotifications',
                'label' => 'Send Registration Notifications',
                'description' => 'Queue the registration emails appropriate to the saved member'
                    . ' and registration source',
                'inputSchema' => [
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID', 'required' => true],
                    'source' => ['type' => 'string', 'label' => 'Registration Source'],
                ],
                'outputSchema' => [
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID'],
                    'source' => ['type' => 'string', 'label' => 'Registration Source'],
                    'queuedTemplates' => ['type' => 'array', 'label' => 'Queued Template Slugs'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'sendRegistrationNotifications',
                'isAsync' => false,
            ],
        ]);
    }

    /**
     * @return void
     */
    private static function registerConditions(): void
    {
        $conditionsClass = MembersWorkflowConditions::class;

        WorkflowConditionRegistry::register(self::SOURCE, [
            [
                'condition' => 'Members.IsMinor',
                'label' => 'Member Is Minor',
                'description' => 'Check if a member is under 18 years old',
                'inputSchema' => [
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID', 'required' => true],
                ],
                'evaluatorClass' => $conditionsClass,
                'evaluatorMethod' => 'isMinor',
            ],
            [
                'condition' => 'Members.IsAdult',
                'label' => 'Member Is Adult',
                'description' => 'Check if a member is 18 years old or older',
                'inputSchema' => [
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID', 'required' => true],
                ],
                'evaluatorClass' => $conditionsClass,
                'evaluatorMethod' => 'isAdult',
            ],
            [
                'condition' => 'Members.HasValidMembership',
                'label' => 'Has Valid Membership',
                'description' => 'Check if a member\'s membership is current (not expired)',
                'inputSchema' => [
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID', 'required' => true],
                ],
                'evaluatorClass' => $conditionsClass,
                'evaluatorMethod' => 'hasValidMembership',
            ],
            [
                'condition' => 'Members.IsWarrantable',
                'label' => 'Is Warrantable',
                'description' => 'Check if a member meets all warrant eligibility requirements',
                'inputSchema' => [
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID', 'required' => true],
                ],
                'evaluatorClass' => $conditionsClass,
                'evaluatorMethod' => 'isWarrantable',
            ],
            [
                'condition' => 'Members.IsActive',
                'label' => 'Member Is Active',
                'description' => 'Check if a member has an active status (can log in)',
                'inputSchema' => [
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID', 'required' => true],
                ],
                'evaluatorClass' => $conditionsClass,
                'evaluatorMethod' => 'isActive',
            ],
            [
                'condition' => 'Members.HasEmailAddress',
                'label' => 'Has Email Address',
                'description' => 'Check if a member has an email address set',
                'inputSchema' => [
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID', 'required' => true],
                ],
                'evaluatorClass' => $conditionsClass,
                'evaluatorMethod' => 'hasEmailAddress',
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
                'entityType' => 'Members.Members',
                'label' => 'Member',
                'description' => 'KMP member with lifecycle status and warrant eligibility',
                'tableClass' => MembersTable::class,
                'fields' => [
                    'id' => ['type' => 'integer', 'label' => 'ID'],
                    'sca_name' => ['type' => 'string', 'label' => 'SCA Name'],
                    'email_address' => ['type' => 'string', 'label' => 'Email'],
                    'status' => ['type' => 'string', 'label' => 'Status'],
                    'branch_id' => ['type' => 'integer', 'label' => 'Branch ID'],
                    'warrantable' => ['type' => 'boolean', 'label' => 'Warrantable'],
                    'membership_number' => ['type' => 'string', 'label' => 'Membership Number'],
                    'membership_expires_on' => ['type' => 'datetime', 'label' => 'Membership Expires'],
                    'birth_month' => ['type' => 'integer', 'label' => 'Birth Month'],
                    'birth_year' => ['type' => 'integer', 'label' => 'Birth Year'],
                ],
            ],
        ]);
    }
}
