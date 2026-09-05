<?php
declare(strict_types=1);

namespace App\Services;

use App\Form\ResetPasswordForm;
use App\KMP\CaseInsensitiveQuery;
use App\Model\Entity\Member;
use App\Services\Security\MemberSessionState;
use App\Services\Security\RequestRateLimiter;
use Authentication\Authenticator\Result;
use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Routing\Router;

/**
 * Handles authentication-related business logic for members.
 *
 * Covers login error categorization, forgot-password token generation,
 * password-reset token validation, and post-login redirect resolution.
 * Controller-layer concerns (request parsing, flash, redirect responses)
 * remain in MembersController.
 *
 * @property \App\Model\Table\MembersTable $Members
 */
class MemberAuthenticationService
{
    use LocatorAwareTrait;

    /**
     * @var \App\Model\Table\MembersTable
     */
    private $Members;

    /**
     * @param \App\Model\Table\MembersTable $Members Members table instance.
     */
    public function __construct()
    {
        /** @var \App\Model\Table\MembersTable $members */
        $members = $this->fetchTable('Members');
        $this->Members = $members;
    }

    /**
     * Categorize an authentication failure into a user-facing error message.
     *
     * @param \Authentication\Authenticator\Result $result Failed authentication result.
     * @return string Translated error message for the user.
     */
    public function categorizeLoginError(Result $result): string
    {
        return (string)__('Your email or password is incorrect, or the account is unavailable.');
    }

    /**
     * Look up a member by email and generate a password-reset token.
     *
     * @param string $emailAddress Email address submitted by the user.
     * @return array{found:bool,email?:string,resetUrl?:string,secretaryEmail?:string}
     */
    public function generatePasswordResetToken(string $emailAddress): array
    {
        $emailAddress = strtolower(trim($emailAddress));
        $limiter = new RequestRateLimiter();
        $account = $limiter->attempt($limiter::BUCKET_RESET_ACCOUNT, $emailAddress);
        $cooldown = $limiter->attempt($limiter::BUCKET_RESET_COOLDOWN, $emailAddress);
        if (!$account->allowed || !$cooldown->allowed) {
            return ['found' => false];
        }
        $member = $this->Members->find()
            ->where(CaseInsensitiveQuery::equals('email_address', $emailAddress))->first();
        if (!$member || !MemberSessionState::eligible($member)) {
            return ['found' => false];
        }
        $now = DateTime::now();
        $token = bin2hex(random_bytes(32));
        // Conditional update makes the cooldown and issuance atomic across replicas.
        $changed = $this->Members->updateAll([
            'password_token' => $token,
            'password_token_expires_on' => $now->addHours(1),
            'password_reset_requested_at' => $now,
        ], [
            'id' => $member->id,
            'auth_version' => (string)$member->auth_version,
            'OR' => [
                'password_reset_requested_at IS' => null,
                'password_reset_requested_at <' => $now->subSeconds(300),
            ],
        ]);
        if ($changed !== 1) {
            return ['found' => false];
        }
        $member->password_token = $token;

        $url = Router::url([
            'controller' => 'Members',
            'action' => 'resetPassword',
            'plugin' => null,
            '_full' => true,
            $member->password_token,
        ]);

        return [
            'found' => true,
            'email' => $member->email_address,
            'resetUrl' => $url,
        ];
    }

    /**
     * Validate a password-reset token and return the member if valid.
     *
     * @param string|null $token Password reset token from URL.
     * @return array{valid:bool,member?:\App\Model\Entity\Member,expired?:bool,form?:\App\Form\ResetPasswordForm}
     */
    public function validateResetToken(?string $token): array
    {
        if ($token === null || !preg_match('/^(?:[a-f0-9]{32}|[a-f0-9]{64})$/i', $token)) {
            return ['valid' => false];
        }

        $member = $this->Members
            ->find()
            ->where(['password_token' => $token])
            ->first();

        if (!$member) {
            return ['valid' => false];
        }

        if ($member->password_token_expires_on === null || $member->password_token_expires_on < DateTime::now()) {
            return ['valid' => false, 'expired' => true];
        }

        return [
            'valid' => true,
            'member' => $member,
            'form' => new ResetPasswordForm(),
        ];
    }

    /**
     * Apply a new password and clear the reset token.
     *
     * @param \App\Model\Entity\Member $member Member resetting their password.
     * @param string $newPassword New password value.
     * @return bool True when save succeeds.
     */
    public function resetPassword(Member $member, string $newPassword): bool
    {
        $token = (string)$member->password_token;
        if ($token === '') {
            return false;
        }
        $hash = (new DefaultPasswordHasher())->hash($newPassword);
        // Token consumption and credential revocation are a single conditional write.
        return $this->Members->updateAll([
            'password' => $hash,
            'password_token' => null,
            'password_token_expires_on' => null,
            'failed_login_attempts' => 0,
            'last_failed_login' => null,
            'auth_version' => bin2hex(random_bytes(32)),
        ], [
            'id' => $member->id,
            'auth_version' => (string)$member->auth_version,
            'password_token' => $token,
            'password_token_expires_on >' => DateTime::now(),
        ]) === 1;
    }

    /** Revoke every session, pending reset and PIN credential for one member. */
    public function revokeCredentials(Member $member): bool
    {
        return $this->Members->updateAll([
            'auth_version' => bin2hex(random_bytes(32)),
            'password_token' => null,
            'password_token_expires_on' => null,
        ], ['id' => $member->id]) === 1;
    }
}
