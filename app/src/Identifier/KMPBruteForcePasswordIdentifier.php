<?php
declare(strict_types=1);


namespace App\Identifier;

use ArrayAccess;
use Authentication\Identifier\Resolver\ResolverAwareTrait;
use Authentication\Identifier\Resolver\ResolverInterface;
use Authentication\PasswordHasher\PasswordHasherFactory;
use Authentication\PasswordHasher\PasswordHasherInterface;
use Authentication\PasswordHasher\PasswordHasherTrait;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Entity\Member;

use Authentication\Identifier\PasswordIdentifier;


class KMPBruteForcePasswordIdentifier extends PasswordIdentifier
{

    public const MAX_ATTEMPTS = '5';
    public const TIMEOUT = '300';



    public function identify(array $credentials): ArrayAccess|array|null
    {
        $identity = $this->_findIdentity($credentials['username']);
        
        if ($identity === null) {
            return null;
        }

        $MembersTable = TableRegistry::getTableLocator()->get('Members');

        //@var Membe $user
        $user = $identity;
        $failedLoginAttempts = $user->failed_login_attempts;
        $maxAttempts = (int)self::MAX_ATTEMPTS;
        $timeout = DateTime::now()->subSeconds((int)self::TIMEOUT);
        $time = DateTime::now();

        //check if the user has reached the maximum number of failed login attempts
        if($failedLoginAttempts >= $maxAttempts && $user->last_failed_login > $timeout){
            $this->_addFailedLoginAttempt($user, $MembersTable);
            $this->_errors[] = "Account Locked";
            return null;
        }

        //check if the password is correct
        if (array_key_exists('password', $credentials)) {
            $password = $credentials['password'];
            if (!$this->_checkPassword($identity, $password)) {
                $this->_addFailedLoginAttempt($user, $MembersTable);
                return null;
            }
        }
        //rehash password if needed
        if($this->_needsPasswordRehash){
            $user->password = $credentials['password'];
        }
        $this->_logSuccessfulLogin($user, $MembersTable);
        return $identity;
        
    }

    protected function _findIdentity($username): ArrayAccess|array|null
    {
        $MembersTable = TableRegistry::getTableLocator()->get('Members');
        $user = $MembersTable->find()
            ->where(['email_address' => $username])
            ->first();
        return $user;
    }

    protected function _logSuccessfulLogin($user, $MembersTable){
        $user->failed_login_attempts = 0;
        $user->last_failed_login = null;
        $user->password_token = null;
        $user->password_token_expires_on = null;
        $user->last_login = DateTime::now();
        $MembersTable->save($user);
    }

    protected function _addFailedLoginAttempt($user, $MembersTable){
        $user->failed_login_attempts++;
        $user->last_failed_login = DateTime::now();
        $MembersTable->save($user);
    }
}
