<?php
declare(strict_types=1);

namespace App\Services\WorkflowEngine\Providers;

use App\KMP\StaticHelpers;
use App\Mailer\QueuedMailerAwareTrait;
use App\Model\Entity\Member;
use App\Services\MemberAuthenticationService;
use App\Services\MemberRegistrationService;
use App\Services\WorkflowEngine\WorkflowContextAwareTrait;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use Throwable;

/**
 * Workflow action implementations for member lifecycle operations.
 *
 * Delegates to MemberAuthenticationService and Member entity methods
 * to avoid duplicating business logic.
 *
 * @property \App\Model\Table\MembersTable $Members
 */
class MembersWorkflowActions
{
    use WorkflowContextAwareTrait;
    use LocatorAwareTrait;
    use QueuedMailerAwareTrait;

    private MemberAuthenticationService $authService;
    private MemberRegistrationService $regService;

    /**
     * Initialize workflow member action collaborators.
     *
     * @param \App\Services\MemberAuthenticationService|null $authService Optional auth service override
     * @param \App\Services\MemberRegistrationService|null $regService Optional registration service override
     */
    public function __construct(
        ?MemberAuthenticationService $authService = null,
        ?MemberRegistrationService $regService = null,
    ) {
        $this->authService = $authService ?? new MemberAuthenticationService();
        $this->regService = $regService ?? new MemberRegistrationService();
    }

    /**
     * Create and register a new member.
     *
     * @param array $context Current workflow context
     * @param array $config Config with registration data fields
     * @return array Output with memberId
     */
    public function register(array $context, array $config): array
    {
        try {
            $membersTable = $this->fetchTable('Members');
            $member = $membersTable->newEmptyEntity();

            $member->sca_name = $this->resolveValue($config['scaName'] ?? null, $context);
            $member->first_name = $this->resolveValue($config['firstName'] ?? null, $context);
            $member->middle_name = $this->resolveValue($config['middleName'] ?? null, $context);
            $member->last_name = $this->resolveValue($config['lastName'] ?? null, $context);
            $member->email_address = $this->resolveValue($config['emailAddress'] ?? null, $context);
            $member->branch_id = $this->resolveValue($config['branchId'] ?? null, $context);
            $member->phone_number = $this->resolveValue($config['phoneNumber'] ?? null, $context);
            $member->street_address = $this->resolveValue($config['streetAddress'] ?? null, $context);
            $member->city = $this->resolveValue($config['city'] ?? null, $context);
            $member->state = $this->resolveValue($config['state'] ?? null, $context);
            $member->zip = $this->resolveValue($config['zip'] ?? null, $context);
            $member->birth_month = (int)$this->resolveValue($config['birthMonth'] ?? 0, $context);
            $member->birth_year = (int)$this->resolveValue($config['birthYear'] ?? 0, $context);

            // Assign age-based status and generate tokens
            if ($member->age !== null && $member->age > 17) {
                $member->password_token = StaticHelpers::generateToken(32);
                $member->password_token_expires_on = DateTime::now()->addDays(1);
                $member->status = Member::STATUS_ACTIVE;
            } else {
                $member->status = Member::STATUS_UNVERIFIED_MINOR;
            }
            $member->password = StaticHelpers::generateToken(12);

            if (!$membersTable->save($member)) {
                Log::warning('Workflow Register: failed to save member');

                return ['success' => false, 'error' => 'Failed to save member', 'memberId' => null];
            }

            return ['success' => true, 'data' => ['memberId' => $member->id, 'status' => $member->status]];
        } catch (Throwable $e) {
            Log::error('Workflow Register failed: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage(), 'memberId' => null];
        }
    }

    /**
     * Activate a member account by setting status to active.
     *
     * @param array $context Current workflow context
     * @param array $config Config with memberId
     * @return array Output with activated boolean
     */
    public function activate(array $context, array $config): array
    {
        try {
            $memberId = (int)$this->resolveValue($config['memberId'], $context);
            $membersTable = $this->fetchTable('Members');
            $member = $membersTable->get($memberId);

            $member->status = Member::STATUS_ACTIVE;

            if (!$membersTable->save($member)) {
                return ['success' => false, 'error' => 'Failed to activate member'];
            }

            return ['success' => true, 'data' => ['memberId' => $memberId, 'status' => Member::STATUS_ACTIVE]];
        } catch (Throwable $e) {
            Log::error('Workflow Activate failed: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Deactivate a member account.
     *
     * Prepends "Deleted: " to email and sets status to deactivated,
     * mirroring MembersController::delete() logic.
     *
     * @param array $context Current workflow context
     * @param array $config Config with memberId
     * @return array Output with deactivated boolean
     */
    public function deactivate(array $context, array $config): array
    {
        try {
            $memberId = (int)$this->resolveValue($config['memberId'], $context);
            $membersTable = $this->fetchTable('Members');
            $member = $membersTable->get($memberId);

            $member->status = Member::STATUS_DEACTIVATED;
            $member->email_address = 'Deleted: ' . $member->email_address;

            if (!$membersTable->save($member)) {
                return ['success' => false, 'error' => 'Failed to deactivate member'];
            }

            return ['success' => true, 'data' => ['memberId' => $memberId, 'status' => Member::STATUS_DEACTIVATED]];
        } catch (Throwable $e) {
            Log::error('Workflow Deactivate failed: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Generate a password reset token and prepare email variables.
     *
     * Delegates to MemberAuthenticationService::generatePasswordResetToken()
     * and queues the reset email.
     *
     * @param array $context Current workflow context
     * @param array $config Config with emailAddress
     * @return array Output with found, resetUrl
     */
    public function sendPasswordReset(array $context, array $config): array
    {
        try {
            $emailAddress = (string)$this->resolveValue($config['emailAddress'], $context);

            $result = $this->authService->generatePasswordResetToken($emailAddress);

            if (!$result['found']) {
                return [
                    'success' => false,
                    'error' => 'Member not found for email address',
                    'secretaryEmail' => $result['secretaryEmail'] ?? null,
                ];
            }

            $this->queueResetEmail($result['email'], $result['resetUrl']);

            return [
                'success' => true,
                'data' => [
                    'email' => $result['email'],
                    'resetUrl' => $result['resetUrl'],
                ],
            ];
        } catch (Throwable $e) {
            Log::error('Workflow SendPasswordReset failed: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Validate a password reset token.
     *
     * Delegates to MemberAuthenticationService::validateResetToken().
     *
     * @param array $context Current workflow context
     * @param array $config Config with token
     * @return array Output with valid boolean, memberId
     */
    public function validatePasswordReset(array $context, array $config): array
    {
        try {
            $token = (string)$this->resolveValue($config['token'], $context);

            $result = $this->authService->validateResetToken($token);

            if (!$result['valid']) {
                return [
                    'success' => false,
                    'error' => isset($result['expired']) && $result['expired'] ? 'Token expired' : 'Invalid token',
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'valid' => true,
                    'memberId' => $result['member']->id,
                ],
            ];
        } catch (Throwable $e) {
            Log::error('Workflow ValidatePasswordReset failed: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Transition a single member from minor to adult status.
     *
     * Calls Member::ageUpReview() which handles status transitions
     * and clears parent_id.
     *
     * @param array $context Current workflow context
     * @param array $config Config with memberId
     * @return array Output with transitioned boolean and new status
     */
    public function ageUpMember(array $context, array $config): array
    {
        try {
            $memberId = (int)$this->resolveValue($config['memberId'], $context);
            $membersTable = $this->fetchTable('Members');
            $member = $membersTable->get($memberId);

            $originalStatus = $member->status;
            $member->ageUpReview();

            if ($member->status === $originalStatus) {
                return [
                    'success' => true,
                    'data' => ['transitioned' => false, 'status' => $member->status, 'reason' => 'No age-up needed'],
                ];
            }

            if (!$membersTable->save($member, ['checkRules' => false, 'validate' => false])) {
                return ['success' => false, 'error' => 'Failed to save aged-up member'];
            }

            return [
                'success' => true,
                'data' => [
                    'transitioned' => true,
                    'previousStatus' => $originalStatus,
                    'status' => $member->status,
                    'memberId' => $memberId,
                ],
            ];
        } catch (Throwable $e) {
            Log::error('Workflow AgeUpMember failed: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Recalculate the warrantable flag for a member.
     *
     * Calls Member::warrantableReview() which checks age, status,
     * membership expiration, legal name, address, and phone.
     *
     * @param array $context Current workflow context
     * @param array $config Config with memberId
     * @return array Output with warrantable boolean and reasons
     */
    public function syncWarrantableStatus(array $context, array $config): array
    {
        try {
            $memberId = (int)$this->resolveValue($config['memberId'], $context);
            $membersTable = $this->fetchTable('Members');
            $member = $membersTable->get($memberId);

            $originalWarrantable = (bool)$member->warrantable;
            $member->warrantableReview();
            $currentWarrantable = (bool)$member->warrantable;

            $changed = $currentWarrantable !== $originalWarrantable;

            if ($changed) {
                if (
                    !$membersTable->save($member, [
                    'checkRules' => false,
                    'validate' => false,
                    'callbacks' => false,
                    ])
                ) {
                    return ['success' => false, 'error' => 'Failed to save warrantable status'];
                }
            }

            return [
                'success' => true,
                'data' => [
                    'warrantable' => $currentWarrantable,
                    'changed' => $changed,
                    'reasons' => $member->non_warrantable_reasons ?? [],
                ],
            ];
        } catch (Throwable $e) {
            Log::error('Workflow SyncWarrantableStatus failed: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process membership verification for a member.
     *
     * Handles status transitions based on age and verification type,
     * mirroring MembersController::verifyMembership() logic.
     *
     * @param array $context Current workflow context
     * @param array $config Config with memberId, verifyMembership, verifyParent, membershipNumber, membershipExpiresOn, parentId
     * @return array Output with verified boolean and new status
     */
    public function verifyMembership(array $context, array $config): array
    {
        try {
            $memberId = (int)$this->resolveValue($config['memberId'], $context);
            $verifyMembership = (bool)$this->resolveValue($config['verifyMembership'] ?? false, $context);
            $verifyParent = (bool)$this->resolveValue($config['verifyParent'] ?? false, $context);
            $membershipNumber = $this->resolveValue($config['membershipNumber'] ?? null, $context);
            $membershipExpiresOn = $this->resolveValue($config['membershipExpiresOn'] ?? null, $context);
            $parentId = $this->resolveValue($config['parentId'] ?? null, $context);
            $verifiedBy = $this->resolveValue($config['verifiedBy'] ?? null, $context)
                ?? $context['triggeredBy'] ?? null;

            $membersTable = $this->fetchTable('Members');
            $member = $membersTable->get($memberId);

            if ($verifyMembership) {
                $member->membership_number = $membershipNumber;
                if ($membershipExpiresOn !== null) {
                    $member->membership_expires_on = $membershipExpiresOn instanceof DateTime
                        ? $membershipExpiresOn
                        : new DateTime($membershipExpiresOn);
                }
            }

            if ($member->age < 18 && $verifyParent && $parentId) {
                $member->parent_id = (int)$parentId;
            }

            // Status transitions matching controller logic
            if ($member->age > 17 && $verifyMembership) {
                $member->status = Member::STATUS_VERIFIED_MEMBERSHIP;
            }
            if ($member->age < 18 && $verifyParent && $verifyMembership) {
                $member->status = Member::STATUS_VERIFIED_MINOR;
            }
            if ($member->age < 18 && $verifyParent && !$verifyMembership) {
                if ($member->status === Member::STATUS_MINOR_MEMBERSHIP_VERIFIED) {
                    $member->status = Member::STATUS_VERIFIED_MINOR;
                } else {
                    $member->status = Member::STATUS_MINOR_PARENT_VERIFIED;
                }
            }
            if ($member->age < 18 && !$verifyParent && $verifyMembership) {
                if ($member->status === Member::STATUS_MINOR_PARENT_VERIFIED) {
                    $member->status = Member::STATUS_VERIFIED_MINOR;
                } else {
                    $member->status = Member::STATUS_MINOR_MEMBERSHIP_VERIFIED;
                }
            }

            $member->verified_by = $verifiedBy;
            $member->verified_date = DateTime::now();

            if (!$membersTable->save($member)) {
                return ['success' => false, 'error' => 'Failed to save verified member'];
            }

            return [
                'success' => true,
                'data' => [
                    'memberId' => $memberId,
                    'status' => $member->status,
                    'verified' => true,
                ],
            ];
        } catch (Throwable $e) {
            Log::error('Workflow VerifyMembership failed: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Generic field update on a member entity.
     *
     * @param array $context Current workflow context
     * @param array $config Config with memberId and fields (key-value pairs)
     * @return array Output with updated boolean
     */
    public function updateMemberField(array $context, array $config): array
    {
        try {
            $memberId = (int)$this->resolveValue($config['memberId'], $context);
            $fields = $this->resolveValue($config['fields'] ?? [], $context);

            if (!is_array($fields) || empty($fields)) {
                return ['success' => false, 'error' => 'No fields specified for update'];
            }

            $membersTable = $this->fetchTable('Members');
            $member = $membersTable->get($memberId);

            foreach ($fields as $field => $value) {
                $resolvedValue = $this->resolveValue($value, $context);
                $member->set($field, $resolvedValue);
            }

            if (!$membersTable->save($member)) {
                return ['success' => false, 'error' => 'Failed to update member fields'];
            }

            return ['success' => true, 'data' => ['memberId' => $memberId, 'updatedFields' => array_keys($fields)]];
        } catch (Throwable $e) {
            Log::error('Workflow UpdateMemberField failed: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Assign age-based status and generate auth tokens for an existing member.
     *
     * Adults get STATUS_ACTIVE + password reset token; minors get STATUS_UNVERIFIED_MINOR.
     *
     * @param array $context Current workflow context
     * @param array $config Config with memberId
     * @return array Output with status
     */
    public function assignStatusAndTokens(array $context, array $config): array
    {
        try {
            $memberId = (int)$this->resolveValue($config['memberId'], $context);
            $membersTable = $this->fetchTable('Members');
            $member = $membersTable->get($memberId);

            $this->regService->assignStatusAndTokens($member);

            if (!$membersTable->save($member)) {
                Log::warning('Workflow AssignStatusAndTokens: failed to save member');

                return ['success' => false, 'error' => 'Failed to save member'];
            }

            return ['success' => true, 'data' => ['memberId' => $memberId, 'status' => $member->status]];
        } catch (Throwable $e) {
            Log::error('Workflow AssignStatusAndTokens failed: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Prepare all registration email variables for use by Core.SendEmail workflow nodes.
     *
     * Loads the member, builds registration vars, and resolves the secretary email
     * addresses from app settings. Outputs all vars needed by the three registration
     * email templates (member-registration-welcome, member-registration-secretary,
     * member-registration-secretary-minor) into workflow context.
     *
     * @param array $context Current workflow context
     * @param array $config Config with memberId
     * @return array Output with registration email vars and secretary addresses
     */
    public function prepareRegistrationEmailVars(array $context, array $config): array
    {
        try {
            $memberId = (int)$this->resolveValue($config['memberId'], $context);
            $membersTable = $this->fetchTable('Members');
            $member = $membersTable->get($memberId);

            $adultVars = $this->regService->buildAdultRegistrationEmailVars($member);
            $adultSecretaryEmail = StaticHelpers::getAppSetting('Members.NewMemberSecretaryEmail', '', null, true);
            $minorSecretaryEmail = StaticHelpers::getAppSetting('Members.NewMinorSecretaryEmail', '', null, true);
            $siteAdminSignature = StaticHelpers::getAppSetting('Email.SiteAdminSignature', '', null, true);
            $portalName = StaticHelpers::getAppSetting('KMP.LongSiteTitle', '', null, true);

            return [
                'success' => true,
                'data' => [
                    'email' => $member->email_address,
                    'passwordResetUrl' => $adultVars['registrationVars']['passwordResetUrl'],
                    'memberScaName' => $member->sca_name,
                    'memberViewUrl' => $adultVars['secretaryVars']['memberViewUrl'],
                    'memberCardPresent' => $adultVars['secretaryVars']['memberCardPresent'],
                    'adultSecretaryEmail' => $adultSecretaryEmail,
                    'minorSecretaryEmail' => $minorSecretaryEmail,
                    'siteAdminSignature' => $siteAdminSignature,
                    'portalName' => $portalName,
                ],
            ];
        } catch (Throwable $e) {
            Log::error('Workflow PrepareRegistrationEmailVars failed: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Queue the registration emails appropriate to the registration source and member age.
     *
     * Admin-created adult members do not receive registration workflow emails. Public
     * self-registration queues the adult welcome email plus the adult secretary notice.
     * Minor registrations queue the minor secretary notice for both sources.
     *
     * @param array $context Current workflow context
     * @param array $config Config with memberId and optional source
     * @return array Output with queued template slugs
     */
    public function sendRegistrationNotifications(array $context, array $config): array
    {
        try {
            $memberId = (int)$this->resolveValue($config['memberId'] ?? null, $context);
            if ($memberId <= 0) {
                return ['success' => false, 'error' => 'memberId is required'];
            }

            $source = (string)$this->resolveValue($config['source'] ?? 'self-register', $context);
            $membersTable = $this->fetchTable('Members');
            /** @var \App\Model\Entity\Member $member */
            $member = $membersTable->get($memberId);

            $queuedTemplates = [];

            if ($member->age !== null && $member->age > 17) {
                if ($source === 'self-register') {
                    $emailVars = $this->regService->buildAdultRegistrationEmailVars($member);
                    $this->queueMail('KMP', 'sendFromTemplate', $member->email_address, array_merge(
                        ['_templateId' => 'member-registration-welcome'],
                        $emailVars['registrationVars'],
                    ));
                    $queuedTemplates[] = 'member-registration-welcome';

                    $adultSecretaryEmail = StaticHelpers::getAppSetting(
                        'Members.NewMemberSecretaryEmail',
                        '',
                        null,
                        true,
                    );
                    if ($adultSecretaryEmail !== '') {
                        $this->queueMail('KMP', 'sendFromTemplate', $adultSecretaryEmail, array_merge(
                            ['_templateId' => 'member-registration-secretary'],
                            $emailVars['secretaryVars'],
                        ));
                        $queuedTemplates[] = 'member-registration-secretary';
                    }
                }
            } else {
                $minorSecretaryEmail = StaticHelpers::getAppSetting(
                    'Members.NewMinorSecretaryEmail',
                    '',
                    null,
                    true,
                );
                if ($minorSecretaryEmail !== '') {
                    $this->queueMail('KMP', 'sendFromTemplate', $minorSecretaryEmail, array_merge(
                        ['_templateId' => 'member-registration-secretary-minor'],
                        $this->regService->buildMinorRegistrationEmailVars($member),
                    ));
                    $queuedTemplates[] = 'member-registration-secretary-minor';
                }
            }

            return [
                'success' => true,
                'data' => [
                    'memberId' => $memberId,
                    'source' => $source,
                    'queuedTemplates' => $queuedTemplates,
                ],
            ];
        } catch (Throwable $e) {
            Log::error('Workflow SendRegistrationNotifications failed: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Queue the password reset email, logging on failure without breaking the action.
     *
     * @param string $email Recipient email
     * @param string $resetUrl Password reset URL
     * @return void
     */
    private function queueResetEmail(string $email, string $resetUrl): void
    {
        try {
            $this->queueMail('KMP', 'sendFromTemplate', $email, [
                '_templateId' => 'password-reset',
                'email' => $email,
                'passwordResetUrl' => $resetUrl,
                'siteAdminSignature' => StaticHelpers::getAppSetting('Email.SiteAdminSignature', '', null, true),
            ]);
        } catch (Throwable $e) {
            Log::warning('Workflow SendPasswordReset: email queuing failed: ' . $e->getMessage());
        }
    }
}
