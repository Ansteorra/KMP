<?php

declare(strict_types=1);

namespace App\Model\Entity;

use ArrayAccess;

use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Entity;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use Cake\ORM\Exception\MissingTableClassException;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Authentication\IdentityInterface as AuthenticationIdentity;

use Authorization\IdentityInterface as AuthorizationIdentity;
use Authorization\AuthorizationServiceInterface;
use Authorization\Policy\ResultInterface;

use JeremyHarris\LazyLoad\ORM\LazyLoadEntityTrait;

use App\KMP\PermissionsLoader;

/**
 * Member Entity
 *
 * @property int $id
 * @property \Cake\I18n\DateTime $modified
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
 * @property \App\Model\Entity\MemberActivity[] $Member_activities
 * @property \App\Model\Entity\PendingAuthorization[] $pending_authorizations
 * @property \App\Model\Entity\PendingAuthorization[] $pending_authorizations_to_approve
 * @property \App\Model\Entity\Role[] $roles
 * @property \App\Model\Entity\Notes[] $notes
 */
class Member extends Entity implements
    AuthorizationIdentity,
    AuthenticationIdentity
{
    use LazyLoadEntityTrait;

    protected ?array $_permissions = null;
    protected ?DateTime $_last_permissions_update = null;

    const STATUS_ACTIVE = "active"; //Can login
    const STATUS_DEACTIVATED = "deactivated"; //Cannot Login
    const STATUS_VERIFIED_MEMBERSHIP = "verified"; //Can Login
    const STATUS_UNVERIFIED_MINOR = "unverified minor"; //Cannot Login
    const STATUS_MINOR_MEMBERSHIP_VERIFIED = "< 18 member verified"; //Cannot Login
    const STATUS_MINOR_PARENT_VERIFIED = "< 18 parent verified"; //Can Login
    const STATUS_VERIFIED_MINOR = "verified < 18"; //Can Login

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
        "modified" => true,
        "password" => true,
        "sca_name" => true,
        "first_name" => true,
        "middle_name" => true,
        "last_name" => true,
        "street_address" => true,
        "city" => true,
        "state" => true,
        "zip" => true,
        "phone_number" => true,
        "email_address" => true,
        "membership_number" => true,
        "membership_expires_on" => true,
        "branch_id" => true,
        "parent_name" => true,
        "background_check_expires_on" => true,
        "password_token" => true,
        "password_token_expires_on" => true,
        "last_login" => true,
        "last_failed_login" => true,
        "failed_login_attempts" => true,
        "birth_month" => true,
        "birth_year" => true,
        "deleted_date" => true,
        "status" => true,
    ];

    protected array $_hidden = [
        "password",
        "password_token",
        "password_token_expires_on",
    ];

    /**
     * Authorization\IdentityInterface method
     */
    public function can(string $action, mixed $resource): bool
    {
        if (is_string($resource)) {
            $resource = TableRegistry::getTableLocator()
                ->get($resource)
                ->newEmptyEntity();
        }
        return $this->authorization->can($this, $action, $resource);
    }

    public function canAccessUrl($url): bool
    {
        try {
            // try this path to see if the url is to a controller that maps to a table
            $table = TableRegistry::getTableLocator()->get($url["controller"]);
            if (isset($url[0])) {
                $entity = $table->get($url[0]);
            } else {
                $entity = $table->newEmptyEntity();
            }
            Log::write(
                "debug",
                "Checking if " .
                    $this->sca_name .
                    " can access " .
                    $url["controller"] .
                    " " .
                    $url["action"],
            );
            return $this->authorization->can($this, $url["action"], $entity);
        } catch (MissingTableClassException $ex) {
            // if the above fails, then the url is not to a controller that maps to a table
            // so we will just check if the user can access the controller via the request authorization.
            return $this->authorization->can($this, $url["action"], $url);
            //return true;
        }
    }

    public function canAuthorizeType(int $activity_id): bool
    {
        $permission = $this->getPermissions();
        $activities = Hash::extract(
            $permission,
            "{n}.activity_id",
        );
        return in_array($activity_id, $activities);
    }

    public function canHaveAuthorizationQueue(): bool
    {
        $permission = $this->getPermissions();
        $activities = Hash::extract(
            $permission,
            "{n}.activity_id",
        );
        // filter out all null's and 0's
        $activities = array_filter($activities, function (
            $var,
        ) {
            return $var !== null;
        });
        return count($activities) > 0;
    }

    /**
     * Authorization\IdentityInterface method
     */
    public function canResult(string $action, mixed $resource): ResultInterface
    {
        if (is_string($resource)) {
            $resource = TableRegistry::getTableLocator()
                ->get($resource)
                ->newEmptyEntity();
        }
        return $this->authorization->canResult($this, $action, $resource);
    }

    /**
     * Authorization\IdentityInterface method
     */
    public function applyScope(
        string $action,
        mixed $resource,
        mixed ...$optionalArgs,
    ): mixed {
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
     * get permissions for the Member based on their roles
     * @return Permission[]
     */
    public function getPermissions(): array
    {
        if (
            $this->_last_permissions_update == null ||
            !$this->_last_permissions_update->isWithinNext("1 minute")
        ) {
            $this->_permissions = PermissionsLoader::getPermissions($this->id);
            $this->_last_permissions_update = DateTime::now();
        }
        return $this->_permissions;
    }

    public function isSuperUser(): bool
    {
        $permissions = $this->getPermissions();
        foreach ($permissions as $permission) {
            if ($permission->is_super_user) {
                return true;
            }
        }
        return false;
    }
    public function ageUpReview(): void
    {
        if (
            $this->status !== self::STATUS_ACTIVE
            && $this->status !== self::STATUS_VERIFIED_MEMBERSHIP
            && $this->status !==  self::STATUS_DEACTIVATED && $this->age >= 17
        ) {
            //the member has aged up and is no longer a minor
            $this->parent_id = null;
            switch ($this->status) {
                case self::STATUS_UNVERIFIED_MINOR:
                case self::STATUS_MINOR_PARENT_VERIFIED:
                    $this->status = self::STATUS_ACTIVE;
                    break;
                case self::STATUS_VERIFIED_MINOR:
                case self::STATUS_MINOR_MEMBERSHIP_VERIFIED:
                    $this->status = self::STATUS_VERIFIED_MEMBERSHIP;
                    break;
            }
        }
    }
    public function getPendingApprovalsCount(): int
    {
        $count = 0;
        $approvalsTable = TableRegistry::getTableLocator()->get("AuthorizationApprovals");
        $query = $approvalsTable->find()
            ->where([
                "approver_id" => $this->id,
                "responded_on is" => null,
            ]);
        $count = $query->count();
        return $count;
    }

    protected function _setPassword($value)
    {
        if (strlen($value) > 0) {
            $hasher = new DefaultPasswordHasher();
            return $hasher->hash($value);
        } else {
            return $this->password;
        }
    }

    protected function _getBirthdate()
    {
        $date = new DateTime();
        if ($this->birth_month == null) {
            return null;
        }
        if ($this->birth_year == null) {
            return null;
        }
        $date = $date->setDate($this->birth_year, $this->birth_month, 1);
        return $date;
    }

    protected function _setStatus($value)
    {
        //the status must be one of the constants defined in this class
        switch ($value) {
            case self::STATUS_ACTIVE:
            case self::STATUS_DEACTIVATED:
            case self::STATUS_VERIFIED_MEMBERSHIP:
            case self::STATUS_UNVERIFIED_MINOR:
            case self::STATUS_VERIFIED_MINOR:
            case self::STATUS_MINOR_MEMBERSHIP_VERIFIED:
            case self::STATUS_MINOR_PARENT_VERIFIED:
                return $value;
            default:
                throw new \InvalidArgumentException("Invalid status");
        }
    }

    protected function _getAge()
    {
        $now = new DateTime();
        $date = new DateTime();
        if ($this->birth_month == null) {
            return null;
        }
        if ($this->birth_year == null) {
            return null;
        }
        $date = $date->setDate($this->birth_year, $this->birth_month, 1);
        $interval = $now->diff($date);
        return $interval->y;
    }
}
