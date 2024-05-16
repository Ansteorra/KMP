<?php
declare(strict_types=1);

namespace App\Model\Entity;

use ArrayAccess;

use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Entity;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;


use Authentication\PasswordHasher\DefaultPasswordHasher;
use Authentication\IdentityInterface as AuthenticationIdentity;

use Authorization\IdentityInterface as AuthorizationIdentity;
use Authorization\AuthorizationServiceInterface;
use Authorization\Policy\ResultInterface;

use JeremyHarris\LazyLoad\ORM\LazyLoadEntityTrait;

use App\KMP\PermissionsLoader;


/**
 * Participant Entity
 *
 * @property int $id
 * @property \Cake\I18n\DateTime $last_updated
 * @property string $password
 * @property string|null $sca_name
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $last_name
 * @property string $street_address
 * @property string $city
 * @property string $state
 * @property string $zip
 * @property string $phone_number
 * @property string $email_address
 * @property int|null $membership_number
 * @property \Cake\I18n\Date|null $membership_expires_on
 * @property string|null $branch_name
 * @property string|null $notes
 * @property string|null $parent_name
 * @property \Cake\I18n\Date|null $background_check_expires_on
 * @property bool $hidden
 * @property string|null $password_token
 * @property \Cake\I18n\DateTime|null $password_token_expires_on
 * @property \Cake\I18n\DateTime|null $last_login
 * @property \Cake\I18n\DateTime|null $last_failed_login
 * @property int|null $failed_login_attempts
 * @property int|null $birth_month
 * @property int|null $birth_year
 * @property \Cake\I18n\DateTime|null $deleted_date
 *
 * @property \App\Model\Entity\ParticipantAuthorizationType[] $participant_authorization_types
 * @property \App\Model\Entity\PendingAuthorization[] $pending_authorizations
 * @property \App\Model\Entity\PendingAuthorization[] $pending_authorizations_to_approve
 * @property \App\Model\Entity\Role[] $roles
 */
class Participant extends Entity implements AuthorizationIdentity, AuthenticationIdentity
{
    use LazyLoadEntityTrait;

    protected ?array $_permissions = null;
    protected ?DateTime $_last_permissions_update = null;

    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'last_updated' => true,
        'password' => true,
        'sca_name' => true,
        'first_name' => true,
        'middle_name' => true,
        'last_name' => true,
        'street_address' => true,
        'city' => true,
        'state' => true,
        'zip' => true,
        'phone_number' => true,
        'email_address' => true,
        'membership_number' => true,
        'membership_expires_on' => true,
        'branch_name' => true,
        'notes' => true,
        'parent_name' => true,
        'background_check_expires_on' => true,
        'hidden' => true,
        'password_token' => true,
        'password_token_expires_on' => true,
        'last_login' => true,
        'last_failed_login' => true,
        'failed_login_attempts' => true,
        'birth_month' => true,
        'birth_year' => true,
        'deleted_date' => true,
        'participant_authorization_types' => true,
        'pending_authorizations' => true,
        'pending_authorizations_to_approve' => true,
        'roles' => true,
    ];


    /**
     * Authorization\IdentityInterface method
     */
    public function can(string $action, mixed $resource): bool
    {
        return $this->authorization->can($this, $action, $resource);
    }

    /**
     * Authorization\IdentityInterface method
     */
    public function canResult(string $action, mixed $resource): ResultInterface
    {
        return $this->authorization->canResult($this, $action, $resource);
    }

    /**
     * Authorization\IdentityInterface method
     */
    public function applyScope(string $action, mixed $resource, mixed ...$optionalArgs): mixed
    {
        return $this->authorization->applyScope($this, $action, $resource);
    }

    /**
     * Authorization\IdentityInterface method
     */
    public function getOriginalData(): ArrayAccess|array
    {
        return $this;
    }

    /**
     * Setter to be used by the middleware.
     */
    public function setAuthorization(AuthorizationServiceInterface $service)
    {
        $this->authorization = $service;

        return $this;
    }

    /**
     * Authentication\IdentityInterface method
     *
     * @return string
     */
    public function getIdentifier(): array|string|int|null
    {
        return $this->id;
    }

    /**
     * get permissions for the participant based on their roles
     * @return Permission[]
     */
    public function getPermissions(): array {
        if($this->_last_permissions_update == null || !$this->_last_permissions_update->isWithinNext('1 minute')){
            $this->_permissions = PermissionsLoader::getPermissions($this->id);
            $this->_last_permissions_update = DateTime::now();
            Log::write('debug', 'load permissions' );
        }
        return $this->_permissions;
    }


    /**
     * Fields that are excluded from JSON versions of the entity.
     *
     * @var list<string>
     */
    protected array $_hidden = [
        'password'
    ];

    protected function _setPassword($value)
    {
        if(strlen($value) > 0){
            $hasher = new DefaultPasswordHasher();
            return $hasher->hash($value);
        }else{
           return $this->password; 
        }
    }

    protected function _getBirthdate(){
        $date = new DateTime();
        $date->setDate($this->birth_year, $this->birth_month, 1);
        Log::write('debug', $date);
        return($date);
    }

    protected function _getAge()
    {
        $now = new DateTime();
        $date = new DateTime();
        $date->setDate($this->birth_year, $this->birth_month, 1);
        $interval = $now->diff($date);
        Log::write('debug', $date);
        Log::write('debug', $interval->y);
        return $interval->y;
    }
}
