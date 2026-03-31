<?php
declare(strict_types=1);

namespace App\Services;

use App\Form\ResetPasswordForm;
use App\KMP\StaticHelpers;
use App\Model\Entity\Member;
use Authentication\Authenticator\Result;
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
        $errors = $result->getErrors();
        if (
            isset($errors['KMPBruteForcePassword']) &&
            count($errors['KMPBruteForcePassword']) > 0
        ) {
            $message = $errors['KMPBruteForcePassword'][0];
            switch ($message) {
                case 'Account Locked':
                    return (string)__(
                        'Your account has been locked. Please try again later.',
                    );
                case 'Account Not Verified':
                    $contactAddress = StaticHelpers::getAppSetting(
                        'Members.AccountVerificationContactEmail',
                    );

                    return (string)__(
                        'Your account is being verified. This '
                        . 'process may take several days after '
                        . 'you have verified your email address. '
                        . 'Please contact ' . $contactAddress
                        . ' if you have not been verified '
                        . 'within a week.',
                    );
                case 'Account Disabled':
                    $contactAddress = StaticHelpers::getAppSetting(
                        'Members.AccountDisabledContactEmail',
                    );

                    return (string)__(
                        'Your account deactivated. Please contact '
                        . $contactAddress . ' if you feel this is in error.',
                    );
                default:
                    return (string)__('Your email or password is incorrect.');
            }
        }

        return (string)__('Your email or password is incorrect.');
    }

    /**
     * Look up a member by email and generate a password-reset token.
     *
     * @param string $emailAddress Email address submitted by the user.
     * @return array{found:bool,email?:string,resetUrl?:string,secretaryEmail?:string}
     */
    public function generatePasswordResetToken(string $emailAddress): array
    {
        $member = $this->Members
            ->find()
            ->where(['email_address' => $emailAddress])
            ->first();

        if (!$member) {
            return [
                'found' => false,
                'secretaryEmail' => StaticHelpers::getAppSetting(
                    'Activity.SecretaryEmail',
                ),
            ];
        }

        $member->password_token = StaticHelpers::generateToken(32);
        $member->password_token_expires_on = DateTime::now()->addDays(1);
        $this->Members->save($member);

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
        $member = $this->Members
            ->find()
            ->where(['password_token' => $token])
            ->first();

        if (!$member) {
            return ['valid' => false];
        }

        if ($member->password_token_expires_on < DateTime::now()) {
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
        $member->password = $newPassword;
        $member->password_token = null;
        $member->password_token_expires_on = null;
        $member->failed_login_attempts = 0;

        return (bool)$this->Members->save($member);
    }
}
