<?php

declare(strict_types=1);

namespace App\Identifier;

use ArrayAccess;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Authentication\Identifier\PasswordIdentifier;
use App\Model\Entity\Member;

class KMPBruteForcePasswordIdentifier extends PasswordIdentifier
{
    public const MAX_ATTEMPTS = "5";
    public const TIMEOUT = "300";

    public function identify(array $credentials): ArrayAccess|array|null
    {
        $maxAttempts = (int) $this->getConfig('maxAttempts', self::MAX_ATTEMPTS);
        $timeoutSeconds = (int) $this->getConfig('timeout', self::TIMEOUT);
        $identity = $this->_findIdentity($credentials["username"]);

        if ($identity === null) {
            return null;
        }

        $MembersTable = TableRegistry::getTableLocator()->get("Members");

        //@var Membe $user
        $user = $identity;
        $failedLoginAttempts = $user->failed_login_attempts;
        $timeout = DateTime::now()->subSeconds($timeoutSeconds);

        //check if the user has reached the maximum number of failed login attempts
        if (
            $failedLoginAttempts >= $maxAttempts &&
            $user->last_failed_login > $timeout
        ) {
            $this->_addFailedLoginAttempt($user, $MembersTable);
            $this->_errors[] = "Account Locked";
            return null;
        }
        //case statement to check the user status
        switch ($user->status) {
            case Member::STATUS_DEACTIVATED:
                $this->_errors[] = "Account Disabled";
                return null;
                break;
            case Member::STATUS_MINOR_MEMBERSHIP_VERIFIED:
            case Member::STATUS_UNVERIFIED_MINOR:
                $this->_errors[] = "Account Not Verified";
                return null;
                break;
        }
        //check if the password is correct
        if (array_key_exists("password", $credentials)) {
            $password = $credentials["password"];
            if (!$this->_checkPassword($identity, $password)) {
                $this->_addFailedLoginAttempt($user, $MembersTable);
                return null;
            }
        }
        //rehash password if needed
        if ($this->_needsPasswordRehash) {
            $user->password = $credentials["password"];
        }
        $this->_logSuccessfulLogin($user, $MembersTable);
        return $identity;
    }

    protected function _findIdentity($username): ArrayAccess|array|null
    {
        $finder = $this->getConfig("finder", 'all');
        $MembersTable = TableRegistry::getTableLocator()->get("Members");
        $user = $MembersTable
            ->find($finder)
            ->where(["email_address" => $username])
            ->first();
        return $user;
    }

    protected function _logSuccessfulLogin($user, $MembersTable)
    {
        $user->failed_login_attempts = 0;
        $user->last_failed_login = null;
        $user->password_token = null;
        $user->password_token_expires_on = null;
        $user->last_login = DateTime::now();
        $user->setDirty("modified", true);
        $user->setDirty("modified_by", true);
        $MembersTable->save($user);
    }

    protected function _addFailedLoginAttempt($user, $MembersTable)
    {
        $user->failed_login_attempts++;
        $user->last_failed_login = DateTime::now();
        $user->setDirty("modified", true);
        $user->setDirty("modified_by", true);
        $MembersTable->save($user);
    }
}