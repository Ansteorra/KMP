<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Activities\Model\Entity\MemberAuthorizationsTrait;
use App\KMP\KmpIdentityInterface;
use App\KMP\PermissionsLoader;
use App\KMP\StaticHelpers;
use ArrayAccess;
use Authentication\IdentityInterface as AuthenticationIdentity;
use Authentication\PasswordHasher\DefaultPasswordHasher;
use Authorization\AuthorizationServiceInterface;
use Authorization\Exception\ForbiddenException;
use Authorization\IdentityInterface as AuthorizationIdentity;
use Authorization\Policy\ResultInterface;
use Cake\I18n\DateTime;
use Cake\ORM\Exception\MissingTableClassException;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use InvalidArgumentException;
use JeremyHarris\LazyLoad\ORM\LazyLoadEntityTrait;

/**
 * Member Entity - Core KMP User Identity and Profile Management
 *
 * Implements authentication, authorization, and profile management for KMP users.
 * Provides warrant eligibility checking, permission management, and status lifecycle.
 *
 * Status constants: STATUS_ACTIVE, STATUS_DEACTIVATED, STATUS_VERIFIED_MEMBERSHIP,
 * STATUS_UNVERIFIED_MINOR, STATUS_MINOR_MEMBERSHIP_VERIFIED, STATUS_MINOR_PARENT_VERIFIED,
 * STATUS_VERIFIED_MINOR
 *
 * @property int $id Primary key
 * @property string $sca_name SCA name
 * @property string $first_name Legal first name
 * @property string $last_name Legal last name
 * @property string $email_address Primary email
 * @property string $status Member status (see STATUS_* constants)
 * @property int|null $branch_id Associated branch
 * @property bool $warrantable Calculated warrant eligibility
 * @property \App\Model\Entity\Role[] $roles Assigned roles
 */
class Member extends BaseEntity implements
    KmpIdentityInterface,
    AuthorizationIdentity,
    AuthenticationIdentity
{
    use LazyLoadEntityTrait;
    use MemberAuthorizationsTrait;

    /** @var array|null Cached permissions */
    protected ?array $_permissions = null;

    /** @var array|null Cached permission IDs */
    protected ?array $_permissionIDs = null;

    /** @var \Cake\I18n\DateTime|null Last permissions update */
    protected ?DateTime $_last_permissions_update = null;

    // Member Status Constants - Control login ability and system access

    /** Active adult member with full system access and login capability */
    public const STATUS_ACTIVE = 'active';

    /** Deactivated member with no login capability or system access */
    public const STATUS_DEACTIVATED = 'deactivated';

    /** Member with verified membership status and full login access */
    public const STATUS_VERIFIED_MEMBERSHIP = 'verified';

    /** Minor member (under 18) without verification, no login capability */
    public const STATUS_UNVERIFIED_MINOR = 'unverified minor';

    /** Minor member with verified membership but no login capability */
    public const STATUS_MINOR_MEMBERSHIP_VERIFIED = '< 18 member verified';

    /** Minor member with parent verification and login capability */
    public const STATUS_MINOR_PARENT_VERIFIED = '< 18 parent verified';

    /** Minor member with full verification and login capability */
    public const STATUS_VERIFIED_MINOR = 'verified < 18';


    /**
     * Fields accessible for mass assignment.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'modified' => true,
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
        'branch_id' => true,
        'parent_name' => true,
        'background_check_expires_on' => true,
        'password_token' => true,
        'password_token_expires_on' => true,
        'last_login' => true,
        'last_failed_login' => true,
        'failed_login_attempts' => true,
        'birth_month' => true,
        'birth_year' => true,
        'deleted_date' => true,
        'status' => true,
        'additional_info' => true,
        'mobile_card_token' => true,
        'title' => true,
        'pronouns' => true,
        'pronunciation' => true,
        'timezone' => true,
    ];

    /** @var array<string> Fields hidden from serialization */
    protected array $_hidden = [
        'password',
        'password_token',
        'password_token_expires_on',
    ];

    /**
     * Get privacy-filtered public data. Minors get minimal data.
     *
     * @return array Filtered member data safe for public consumption
     */
    public function publicData()
    {
        if ($this->age < 18) {
            $data = [];
            $data['sca_name'] = $this->sca_name;
            $data['branch'] = $this->branch;
            $data['publicLinks'] = $this->publicLinks();
            $data['publicAdditionalInfo'] = $this->publicAdditionalInfo();

            return $data;
        }
        $data = $this->toArray();
        //Always Private
        unset($data['password']);
        unset($data['password_token']);
        unset($data['password_token_expires_on']);
        unset($data['deleted_date']);
        unset($data['failed_login_attempts']);
        unset($data['last_failed_login']);
        unset($data['last_login']);
        unset($data['background_check_expires_on']);
        unset($data['mobile_card_token']);
        unset($data['additional_info']);
        unset($data['id']);
        unset($data['status']);
        unset($data['created']);
        unset($data['modified']);
        unset($data['roles']);
        unset($data['notes']);
        unset($data['pending_authorizations']);
        unset($data['pending_authorizations_to_approve']);
        unset($data['current_member_roles']);
        unset($data['previous_member_roles']);
        unset($data['upcoming_member_roles']);
        unset($data['verified_date']);
        unset($data['verified_by']);
        unset($data['membership_card_path']);
        unset($data['created_by']);
        unset($data['modified_by']);
        unset($data['deleted']);
        unset($data['parent']);
        unset($data['parent_id']);
        unset($data['membership_expires_on']);
        unset($data['birth_month']);
        unset($data['birth_year']);

        //Privacy Configurable
        //TODO Check Privacy Settings
        unset($data['membership_number']);
        //TODO Check Privacy Settings
        unset($data['first_name']);
        //TODO Check Privacy Settings
        unset($data['middle_name']);
        //TODO Check Privacy Settings
        unset($data['last_name']);
        //TODO Check Privacy Settings
        unset($data['street_address']);
        //TODO Check Privacy Settings
        unset($data['city']);
        //TODO Check Privacy Settings
        unset($data['state']);
        //TODO Check Privacy Settings
        unset($data['zip']);
        //TODO Check Privacy Settings
        unset($data['phone_number']);
        //TODO Check Privacy Settings
        unset($data['email_address']);
        //TODO Check Privacy Settings
        unset($data['pronouns']);
        //TODO Check Privacy Settings
        unset($data['branch']);
        //TODO Check Privacy Settings
        unset($data['branch_id']);
        //TODO Check Privacy Settings
        unset($data['warrantable']);

        //Always Public
        $data['publicLinks'] = $this->publicLinks();
        $data['publicAdditionalInfo'] = $this->publicAdditionalInfo();

        return $data;
    }

    /**
     * Check if member can perform action on resource.
     *
     * @param string $action The action (e.g., 'view', 'edit')
     * @param mixed $resource Entity or table name
     * @param mixed ...$optionalArgs Additional args for policies
     * @return bool True if authorized
     */
    public function can(string $action, mixed $resource, ...$optionalArgs): bool
    {
        if (is_string($resource)) {
            $resource = TableRegistry::getTableLocator()
                ->get($resource)
                ->newEmptyEntity();
        }

        return $this->authorization->can($this, $action, $resource, ...$optionalArgs);
    }

    /**
     * Check authorization, throw ForbiddenException if denied.
     *
     * @param string $action The action
     * @param mixed $resource The resource
     * @param mixed ...$optionalArgs Additional args
     * @return bool Always true (throws on failure)
     * @throws \Authorization\Exception\ForbiddenException
     */
    public function checkCan(string $action, mixed $resource, ...$optionalArgs): bool
    {
        if (is_string($resource)) {
            $resource = TableRegistry::getTableLocator()
                ->get($resource)
                ->newEmptyEntity();
        }

        return $this->authorization->checkCan($this, $action, $resource, ...$optionalArgs);
    }

    /**
     * Generate configured external links from app settings.
     *
     * @return array<string, string> Link names to URLs
     */
    public function publicLinks()
    {
        $externalLinks = StaticHelpers::getAppSettingsStartWith('Member.ExternalLink.');
        if (empty($externalLinks)) {
            return [];
        }
        $linkData = [];
        foreach ($externalLinks as $key => $link) {
            $linkLabel = str_replace('Member.ExternalLink.', '', $key);
            $linkUrl = StaticHelpers::processTemplate($link, $this->toArray(), 1, '__missing__');
            if (substr_count($linkUrl, '__missing__') == 0) {
                $linkData[$linkLabel] = $linkUrl;
            }
        }

        return $linkData;
    }

    /**
     * Get public additional info based on app settings.
     *
     * @return array<string, string> Public additional info fields
     */
    public function publicAdditionalInfo()
    {
        $additionalInfoList = StaticHelpers::getAppSettingsStartWith('Member.AdditionalInfo.');
        if (empty($additionalInfoList)) {
            return [];
        }
        $publicKeys = [];
        foreach ($additionalInfoList as $key => $value) {
            $pipePos = strpos($value, '|');
            if ($pipePos !== false) {
                $fieldSecDetails = explode('|', $value);
                if (count($fieldSecDetails) >= 3 && $fieldSecDetails[2] == 'public') {
                    $publicKeys[] = str_replace('Member.AdditionalInfo.', '', $key);
                }
            }
        }
        $publicData = [];
        foreach ($publicKeys as $key) {
            $publicData[$key] = $this->additional_info[$key] ?? '';
        }

        return $publicData;
    }

    /**
     * Check if member can access a specific URL/route.
     *
     * @param array $url Route array with controller, action, and optional parameters
     * @return bool True if member can access the URL
     */
    public function canAccessUrl(array $url): bool
    {
        try {
            // try this path to see if the url is to a controller that maps to a table
            $className = '';
            if (isset($url['model'])) {
                $className = $url['model'];
            } else {
                $className = $url['controller'];
            }
            $table = TableRegistry::getTableLocator()->get($className);
            $tableClass = $table->getEntityClass();
            if ($tableClass == "Cake\ORM\Entity") {
                // if the above fails, then the url is not to a controller that maps to a table
                return $this->authorization->checkCan($this, $url['action'], $url);
            }
            if (isset($url[0])) {
                $entity = $table->get($url[0]);
            } else {
                $entity = $table->newEmptyEntity();
            }

            return $this->authorization->checkCan($this, $url['action'], $entity);
        } catch (MissingTableClassException $ex) {
            // if the above fails, then the url is not to a controller that maps to a table
            // so we will just check if the user can access the controller via the request authorization.
        }

        return $this->authorization->checkCan($this, $url['action'], $url);
    }

    /**
     * Get detailed authorization result with reasoning.
     *
     * @param string $action The action being performed
     * @param mixed $resource The resource being operated on
     * @param mixed ...$optionalArgs Additional policy arguments
     * @return \Authorization\Policy\ResultInterface Detailed authorization result
     */
    public function canResult(string $action, mixed $resource, ...$optionalArgs): ResultInterface
    {
        if (is_string($resource)) {
            $resource = TableRegistry::getTableLocator()
                ->get($resource)
                ->newEmptyEntity();
        }

        return $this->authorization->canResult($this, $action, $resource, ...$optionalArgs);
    }

    /**
     * Authorize the current identity to perform an action.
     *
     * @param mixed $resource The resource being operated on.
     * @param string|null $action The action/operation being performed.
     * @return void
     */
    public function authorizeWithArgs(mixed $resource, ?string $action = null, ...$args): void
    {

        $result = $this->canResult($action, $resource, ...$args);
        if ($result->getStatus()) {
            return;
        }

        if (is_object($resource)) {
            $name = get_class($resource);
        } elseif (is_string($resource)) {
            $name = $resource;
        } else {
            $name = gettype($resource);
        }
        throw new ForbiddenException($result, [$action, $name]);
    }

    /**
     * Apply authorization scope conditions/restrictions.
     *
     * @param string $action The action/operation being performed.
     * @param mixed $resource The resource being operated on.
     * @param mixed $optionalArgs Multiple additional arguments which are passed to the scope
     * @return mixed The modified resource.
     */
    public function applyScope(
        string $action,
        mixed $resource,
        mixed ...$optionalArgs,
    ): mixed {
        return $this->authorization->applyScope($this, $action, $resource);
    }

    /**
     * Get the decorated identity
     *
     * If the decorated identity implements `getOriginalData()`
     * that method should be invoked to expose the original data.
     *
     * @return \ArrayAccess|array
     */
    public function getOriginalData(): ArrayAccess|array
    {
        return $this;
    }

    /**
     * Setter to be used by the middleware.
     */
    public function setAuthorization(AuthorizationServiceInterface $service): self
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
     * Get all permissions assigned to member through their roles.
     *
     * @return array<\App\Model\Entity\Permission> Permission entities
     */
    public function getPermissions(): array
    {
        $permissions = PermissionsLoader::getPermissions($this->id);

        return $permissions;
    }

    /**
     * Get permission IDs for efficient permission checking.
     *
     * @return array<int> Array of permission ID integers
     */
    public function getPermissionIDs(): array
    {
        $permissionIDs = Hash::extract(PermissionsLoader::getPermissions($this->id), '{n}.id');

        return $permissionIDs;
    }

    /**
     * Get member's authorization policies with optional branch filtering.
     *
     * @param array|null $branchIds Optional branch IDs to filter policies
     * @return array Policy configurations
     */
    public function getPolicies(?array $branchIds = null): array
    {
        if ($branchIds == null || empty($branchIds)) {
            $policies = PermissionsLoader::getPolicies($this->id);

            return $policies;
        } else {
            $policies = PermissionsLoader::getPolicies($this->id, $branchIds);

            return $policies;
        }
    }

    /**
     * Check if the member has super user privileges.
     *
     * @return bool True if member has super user privileges
     */
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

    /**
     * Get branch IDs where member has permission for a specific action.
     *
     * Returns null for global access, empty array for no access, or
     * specific branch IDs for scoped access.
     *
     * @param string $action The policy action (e.g., 'edit', 'view')
     * @param mixed $resource Entity instance, table name, or entity class
     * @return array|null Branch IDs, null for global, empty for no access
     */
    public function getBranchIdsForAction(string $action, mixed $resource): ?array
    {
        // Super users have access to all branches
        if ($this->isSuperUser()) {
            return null;
        }

        // Resolve resource to entity if string table name provided
        if (is_string($resource)) {
            $resource = TableRegistry::getTableLocator()
                ->get($resource)
                ->newEmptyEntity();
        }

        // Get the policy class name from the resource
        $policyClass = $this->resolvePolicyClass($resource);
        if ($policyClass === null) {
            return [];
        }

        // Convert action to policy method name (e.g., 'edit' -> 'canEdit')
        $policyMethod = 'can' . ucfirst($action);

        // Get member's policies
        $policies = $this->getPolicies();
        if (empty($policies)) {
            return [];
        }

        // Check if policy class exists in member's policies
        $policyClassData = $policies[$policyClass] ?? null;
        if (empty($policyClassData)) {
            return [];
        }

        // Check if policy method exists for this policy class
        $policyMethodData = $policyClassData[$policyMethod] ?? null;
        if (empty($policyMethodData)) {
            return [];
        }

        // Check scoping rule to determine branch access
        if ($policyMethodData->scoping_rule === Permission::SCOPE_GLOBAL) {
            return null; // Global access - all branches
        }

        // Return specific branch IDs
        return $policyMethodData->branch_ids ?? [];
    }

    /**
     * Resolve the policy class name from a resource.
     *
     * @param mixed $resource Entity instance, table instance, or class name
     * @return string|null Policy class name, or null if not resolvable
     */
    protected function resolvePolicyClass(mixed $resource): ?string
    {
        // If resource is a Table instance, get the entity class
        if ($resource instanceof \Cake\ORM\Table) {
            $entityClass = $resource->getEntityClass();
            if ($entityClass === "Cake\ORM\Entity") {
                // Generic entity - use table name for policy
                $tableName = $resource->getAlias();
                return $this->getPolicyClassFromTableName($tableName);
            }
            $resource = new $entityClass();
        }

        // If resource is an entity, determine its policy class
        if ($resource instanceof BaseEntity) {
            $entityClass = get_class($resource);
            $policyClass = str_replace('Model\Entity', 'Policy', $entityClass) . 'Policy';

            // Check if this is a plugin entity
            if (strpos($entityClass, 'Plugin') !== false || strpos($entityClass, '\\') === 0) {
                return $policyClass;
            }

            // Standard app entity
            return $policyClass;
        }

        return null;
    }

    /**
     * Get policy class from table name.
     *
     * @param string $tableName The table name (e.g., 'Members')
     * @return string Policy class name
     */
    protected function getPolicyClassFromTableName(string $tableName): string
    {
        // Handle plugin tables (e.g., 'Officers.Offices')
        if (strpos($tableName, '.') !== false) {
            [$plugin, $name] = explode('.', $tableName, 2);
            return "{$plugin}\\Policy\\{$name}Policy";
        }

        // Standard app table
        return "App\\Policy\\{$tableName}Policy";
    }

    /**
     * Review and update member status when minor reaches age 18.
     *
     * Transitions: Unverified Minor → Active, Verified Minor → Verified Membership
     *
     * @return void
     */
    public function ageUpReview(): void
    {
        if (
            $this->status !== self::STATUS_ACTIVE
            && $this->status !== self::STATUS_VERIFIED_MEMBERSHIP
            && $this->status !==  self::STATUS_DEACTIVATED && $this->age > 17
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

    /**
     * Update warrant eligibility status.
     *
     * @return void Updates warrantable and non_warrantable_reasons properties
     */
    public function warrantableReview(): void
    {
        $this->non_warrantable_reasons = $this->getNonWarrantableReasons();
    }

    /**
     * Get reasons preventing warrant eligibility.
     *
     * Checks: age >= 18, verified membership, not expired, legal name, address, phone.
     *
     * @return array<string> Disqualifying reasons (empty = eligible)
     */
    public function getNonWarrantableReasons(): array
    {
        $reasons = [];
        if ($this->age < 18) {
            $reasons[] = 'Member is under 18';
            $this->warrantable = false;
        }
        if ($this->status != self::STATUS_VERIFIED_MEMBERSHIP) {
            $reasons[] = 'Membership is not verified';
            $this->warrantable = false;
        } else {
            if ($this->membership_expires_on == null || $this->membership_expires_on->isPast()) {
                $reasons[] = 'Membership is expired';
                $this->warrantable = false;
            }
        }
        if ($this->first_name == null || $this->last_name == null) {
            $reasons[] = 'Legal name is not set';
            $this->warrantable = false;
        }
        if ($this->street_address == null || $this->city == null || $this->state == null || $this->zip == null) {
            $reasons[] = 'Address is not set';
            $this->warrantable = false;
        }
        if ($this->phone_number == null) {
            $reasons[] = 'Phone number is not set';
            $this->warrantable = false;
        }
        //if the reasons is empty then the member is warrantable
        if (empty($reasons)) {
            $this->warrantable = true;
        }

        return $reasons;
    }

    /**
     * Secure password setter with automatic hashing.
     *
     * @param string $value Plain text password to hash
     * @return string Hashed password (or existing if value is empty)
     */
    protected function _setPassword($value)
    {
        if (strlen($value) > 0) {
            $hasher = new DefaultPasswordHasher();

            return $hasher->hash($value);
        } else {
            return $this->password;
        }
    }

    /**
     * Generate birthdate from birth month and year.
     *
     * @return \Cake\I18n\DateTime|null Birth date or null if incomplete
     */
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

    /**
     * Generate formatted name for herald/announcement purposes.
     *
     * Format: [Title] SCA_Name [(pronunciation)] [- pronouns]
     *
     * @return string Formatted herald name
     */
    protected function _getNameForHerald()
    {
        $returnVal = $this->sca_name;
        if ($this->title != null && $this->title != '') {
            $returnVal = $this->title . ' ' . $returnVal;
        }
        if ($this->pronunciation != null && $this->pronunciation != '') {
            $returnVal = $returnVal . ' (' . $this->pronunciation . ')';
        }
        if ($this->pronouns != null && $this->pronouns != '') {
            $returnVal = $returnVal . ' - ' . $this->pronouns;
        }

        return $returnVal;
    }

    /**
     * Validate and set member status.
     *
     * @param string $value The status value to validate
     * @return string The validated status value
     * @throws \InvalidArgumentException When invalid status is provided
     */
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
                throw new InvalidArgumentException('Invalid status');
        }
    }

    /**
     * Convert membership expiration date to string format.
     *
     * @return string Formatted date string or empty string if null
     */
    protected function _getExpiresOnToString()
    {
        if ($this->membership_expires_on == null) {
            return '';
        }

        return $this->membership_expires_on->toDateString();
    }

    /**
     * Calculate member's current age in years.
     *
     * @return int|null Current age or null if birth data incomplete
     */
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

    /**
     * Return the identity as a Member entity.
     *
     * @return \App\Model\Entity\Member This instance
     */
    public function getAsMember(): Member
    {
        return $this;
    }
}
