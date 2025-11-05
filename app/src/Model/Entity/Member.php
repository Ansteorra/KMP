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
 * The Member entity serves as the cornerstone of the KMP system, representing users who participate
 * in the Kingdom Management Portal ecosystem. This entity implements multiple interface patterns to
 * provide comprehensive identity, authentication, and authorization functionality throughout the application.
 *
 * ## Core Responsibilities
 *
 * ### Identity Management
 * - **Authentication Identity**: Implements CakePHP Authentication interface for login/logout
 * - **Authorization Identity**: Implements CakePHP Authorization interface for permission checking
 * - **KMP Identity**: Custom interface for KMP-specific identity requirements
 * - **Member Profile**: Complete user profile with personal, contact, and organizational data
 *
 * ### Status Management
 * The Member entity includes a sophisticated status system that handles various member states:
 * - **Active Members**: Full system access with verified membership
 * - **Deactivated Members**: Restricted access, cannot login
 * - **Minor Members**: Age-based restrictions with parent/guardian oversight
 * - **Verified/Unverified**: Membership verification status tracking
 *
 * ### Authorization Integration
 * - **Role-Based Access Control**: Members receive permissions through assigned roles
 * - **Branch-Based Security**: Data access scoped by organizational branch membership
 * - **Dynamic Permission Loading**: Real-time permission calculation from role assignments
 * - **Policy Integration**: Seamless integration with CakePHP Authorization policies
 *
 * ### Privacy and Security
 * - **Data Protection**: Privacy controls for public/private data exposure
 * - **Password Security**: Secure password hashing with DefaultPasswordHasher
 * - **Session Management**: Login tracking and failed attempt monitoring
 * - **Minor Protection**: Enhanced privacy controls for members under 18
 *
 * ## Usage Examples
 *
 * ### Basic Member Operations
 * ```php
 * // Create a new member
 * $member = $membersTable->newEntity([
 *     'first_name' => 'John',
 *     'last_name' => 'Doe',
 *     'email_address' => 'john@example.com',
 *     'status' => Member::STATUS_ACTIVE
 * ]);
 *
 * // Check member permissions
 * if ($member->can('view', $branch)) {
 *     // Member can view branch
 * }
 *
 * // Get member's warrantable status
 * $reasons = $member->getNonWarrantableReasons();
 * if (empty($reasons)) {
 *     // Member is eligible for warrants
 * }
 * ```
 *
 * ### Authorization Checks
 * ```php
 * // Direct permission checking
 * $canEdit = $member->can('edit', $resource);
 *
 * // URL-based access checking
 * $canAccess = $member->canAccessUrl([
 *     'controller' => 'Members',
 *     'action' => 'edit',
 *     0 => $memberId
 * ]);
 *
 * // Apply authorization scope
 * $scopedQuery = $member->applyScope('index', $membersQuery);
 * ```
 *
 * ### Public Data Access
 * ```php
 * // Get privacy-filtered public data
 * $publicData = $member->publicData();
 *
 * // Get configured external links
 * $links = $member->publicLinks();
 *
 * // Get public additional information
 * $additionalInfo = $member->publicAdditionalInfo();
 * ```
 *
 * ## Status Constants
 * - `STATUS_ACTIVE`: Full access, verified adult members
 * - `STATUS_DEACTIVATED`: Restricted access, cannot login
 * - `STATUS_VERIFIED_MEMBERSHIP`: Verified membership, full access
 * - `STATUS_UNVERIFIED_MINOR`: Under 18, unverified, no login
 * - `STATUS_MINOR_MEMBERSHIP_VERIFIED`: Under 18, membership verified, no login
 * - `STATUS_MINOR_PARENT_VERIFIED`: Under 18, parent verified, can login
 * - `STATUS_VERIFIED_MINOR`: Under 18, fully verified, can login
 *
 * ## Security Considerations
 * - Passwords are automatically hashed using DefaultPasswordHasher
 * - Sensitive fields are hidden from serialization (password, tokens)
 * - Public data filtering protects minor member information
 * - Permission checking integrates with KMP authorization framework
 * - Failed login attempt tracking prevents brute force attacks
 *
 * ## Integration Points
 * - **Activities Plugin**: Member authorization management through MemberAuthorizationsTrait
 * - **Warrant System**: Warrantability checking and qualification validation
 * - **Branch System**: Organizational hierarchy and data scoping
 * - **Role System**: Permission inheritance through role assignments
 * - **Privacy System**: Configurable public/private data exposure controls
 *
 * @property int $id Primary key identifier
 * @property \Cake\I18n\DateTime $modified Last modification timestamp
 * @property string $password Hashed password for authentication
 * @property string|null $sca_name SCA (Society for Creative Anachronism) name
 * @property string $first_name Legal first name
 * @property string|null $middle_name Legal middle name
 * @property string $last_name Legal last name
 * @property string $street_address Physical street address
 * @property string $city City of residence
 * @property string $state State/province of residence
 * @property string $zip Postal/ZIP code
 * @property string $phone_number Contact phone number
 * @property string $email_address Primary email address
 * @property int|null $membership_number Organization membership number
 * @property \Cake\I18n\Date|null $membership_expires_on Membership expiration date
 * @property string|null $parent_name Parent/guardian name for minors
 * @property \Cake\I18n\Date|null $background_check_expires_on Background check expiration
 * @property bool $hidden Whether member profile is hidden from public view
 * @property string|null $password_token Password reset token
 * @property \Cake\I18n\DateTime|null $password_token_expires_on Password token expiration
 * @property \Cake\I18n\DateTime|null $last_login Last successful login timestamp
 * @property \Cake\I18n\DateTime|null $last_failed_login Last failed login attempt
 * @property int|null $failed_login_attempts Count of consecutive failed login attempts
 * @property int|null $birth_month Birth month (1-12) for age calculation
 * @property int|null $birth_year Birth year for age calculation
 * @property \Cake\I18n\DateTime|null $deleted_date Soft deletion timestamp
 * @property string $status Member status (see STATUS_* constants)
 * @property array|null $additional_info JSON field for configurable additional information
 * @property string|null $mobile_card_token Token for mobile card access
 * @property string|null $title Honorific title (Lord, Lady, etc.)
 * @property string|null $pronouns Preferred pronouns
 * @property string|null $pronunciation Name pronunciation guide
 * @property bool $warrantable Calculated field indicating warrant eligibility
 * @property array $non_warrantable_reasons Array of reasons preventing warrant eligibility
 * @property int|null $branch_id Associated branch identifier
 * @property \App\Model\Entity\Branch $branch Associated branch entity
 * @property int|null $parent_id Parent member identifier for minors
 * @property \App\Model\Entity\Member $parent Parent member entity for minors
 *
 * @property \App\Model\Entity\MemberActivity[] $Member_activities Member activity records
 * @property \App\Model\Entity\PendingAuthorization[] $pending_authorizations Authorization requests
 * @property \App\Model\Entity\PendingAuthorization[] $pending_authorizations_to_approve Authorizations to approve
 * @property \App\Model\Entity\Role[] $roles Assigned roles
 * @property \App\Model\Entity\Notes[] $notes Associated notes
 * @property \App\Model\Entity\MemberRole[] $current_member_roles Current active role assignments
 * @property \App\Model\Entity\MemberRole[] $previous_member_roles Historical role assignments
 * @property \App\Model\Entity\MemberRole[] $upcoming_member_roles Future role assignments
 * @property \App\Model\Entity\GatheringAttendance[] $gathering_attendances Gathering attendance records
 */
class Member extends BaseEntity implements
    KmpIdentityInterface,
    AuthorizationIdentity,
    AuthenticationIdentity
{
    use LazyLoadEntityTrait;
    use MemberAuthorizationsTrait;

    /**
     * Internal cache for loaded permissions to avoid repeated database queries
     *
     * @var array|null Cached permissions array
     */
    protected ?array $_permissions = null;

    /**
     * Internal cache for permission IDs for quick permission checking
     *
     * @var array|null Cached permission ID array
     */
    protected ?array $_permissionIDs = null;

    /**
     * Timestamp of last permission update for cache invalidation
     *
     * @var \Cake\I18n\DateTime|null Last permissions update time
     */
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
     * Mass assignment configuration for entity fields
     *
     * Defines which fields can be safely assigned through newEntity() or patchEntity().
     * Security-sensitive fields like 'id' and internal timestamps are excluded.
     * Password field is included but will be automatically hashed on assignment.
     *
     * Note: '*' is set to false for security - only explicitly listed fields are accessible.
     *
     * @var array<string, bool> Field accessibility mapping
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

    /**
     * Fields to hide from serialization for security and privacy
     *
     * These sensitive fields are automatically excluded from toArray(), JSON serialization,
     * and other output methods to prevent accidental exposure of sensitive data.
     *
     * @var array<string> Hidden field names
     */
    protected array $_hidden = [
        'password',
        'password_token',
        'password_token_expires_on',
    ];

    /**
     * Generate privacy-filtered public data for external consumption
     *
     * Returns a filtered version of member data suitable for public display,
     * with enhanced privacy protections for minor members. This method implements
     * the KMP privacy system with configurable field exposure controls.
     *
     * ## Privacy Rules
     * - **Minor Members (under 18)**: Only SCA name, branch, and configured public data
     * - **Adult Members**: Extensive filtering with TODO privacy setting integration
     * - **Always Private**: Authentication data, tokens, internal timestamps, system fields
     * - **Configurable**: Personal information based on member privacy preferences
     *
     * ## Data Categories
     * - **Always Private**: password, tokens, login data, system timestamps
     * - **Conditionally Private**: personal info, contact details (TODO: privacy settings)
     * - **Always Public**: SCA name, branch (for minors), configured external links
     *
     * @return array Filtered member data safe for public consumption
     *
     * @example
     * ```php
     * // Get public data for display
     * $publicData = $member->publicData();
     * 
     * // Minor member returns minimal data
     * if ($member->age < 18) {
     *     // Only: sca_name, branch, publicLinks, publicAdditionalInfo
     * }
     * 
     * // Adult member returns filtered full data
     * // TODO: Integrate with privacy settings system
     * ```
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
     * Check whether the current member can perform an action on a resource
     *
     * This method provides the primary authorization interface for the Member entity,
     * integrating with CakePHP's Authorization component to check permissions based
     * on the member's roles and the configured authorization policies.
     *
     * ## Authorization Flow
     * 1. **Resource Resolution**: String table names are converted to empty entities
     * 2. **Policy Lookup**: Authorization service locates appropriate policy class
     * 3. **Permission Check**: Policy evaluates member permissions against resource
     * 4. **Result Return**: Boolean result indicating permission grant/denial
     *
     * ## Integration Points
     * - **Policy Classes**: Delegates to configured authorization policies
     * - **Role System**: Utilizes member's assigned roles for permission evaluation
     * - **Branch Scoping**: Considers organizational hierarchy in decisions
     * - **Resource Context**: Evaluates permissions based on specific resource instances
     *
     * @param string $action The action/operation being performed (e.g., 'view', 'edit', 'delete')
     * @param mixed $resource The resource being operated on (entity, table name, or array)
     * @param mixed ...$optionalArgs Additional arguments passed to authorization policies
     * @return bool True if member can perform action, false otherwise
     *
     * @example
     * ```php
     * // Check if member can edit another member
     * if ($currentMember->can('edit', $targetMember)) {
     *     // Allow editing
     * }
     * 
     * // Check if member can access a table
     * if ($member->can('index', 'Branches')) {
     *     // Show branches list
     * }
     * 
     * // Check with additional context
     * if ($member->can('approve', $warrant, $branch)) {
     *     // Process warrant approval
     * }
     * ```
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
     * Check authorization and throw exception if denied
     *
     * Similar to can() but throws a ForbiddenException if the authorization check fails.
     * This is useful for enforcing authorization requirements with automatic error handling.
     *
     * @param string $action The action/operation being performed
     * @param mixed $resource The resource being operated on
     * @param mixed ...$optionalArgs Additional arguments passed to authorization policies
     * @return bool Always returns true (throws exception on failure)
     * @throws \Authorization\Exception\ForbiddenException When authorization is denied
     *
     * @example
     * ```php
     * // Enforce authorization (throws exception if denied)
     * $member->checkCan('edit', $sensitiveResource);
     * 
     * // This code only executes if authorization passes
     * $this->performSensitiveOperation($sensitiveResource);
     * ```
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
     * Generate configured external links for the member
     *
     * Processes configured external link templates from app settings to generate
     * personalized URLs for the member. Links are only included if all required
     * template variables can be resolved from the member's data.
     *
     * ## Configuration System
     * Links are configured via app settings with the pattern:
     * - Setting Key: `Member.ExternalLink.{LinkName}`
     * - Setting Value: URL template with `{field_name}` placeholders
     *
     * ## Template Processing
     * - Uses StaticHelpers::processTemplate() for variable substitution
     * - Missing variables are replaced with '__missing__' sentinel value
     * - Links with missing variables are excluded from results
     *
     * @return array<string, string> Associative array of link names to URLs
     *
     * @example
     * ```php
     * // App setting: Member.ExternalLink.Facebook = "https://facebook.com/{facebook_username}"
     * // Member data: facebook_username = "john.doe"
     * 
     * $links = $member->publicLinks();
     * // Returns: ['Facebook' => 'https://facebook.com/john.doe']
     * 
     * // If facebook_username is empty, link is excluded from results
     * ```
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
     * Extract public additional information based on configuration
     *
     * Processes configured additional information fields to determine which
     * should be publicly visible. Uses a three-part configuration system
     * to control field display, validation, and privacy settings.
     *
     * ## Configuration Format
     * App settings use pattern: `Member.AdditionalInfo.{FieldName} = "{label}|{type}|{privacy}"`
     * - **label**: Human-readable field label
     * - **type**: Field type (text, select, etc.)
     * - **privacy**: 'public' or 'private' visibility setting
     *
     * ## Privacy Processing
     * - Only fields marked as 'public' in configuration are included
     * - Missing or empty values are included as empty strings
     * - Private fields are completely excluded from results
     *
     * @return array<string, string> Public additional information fields
     *
     * @example
     * ```php
     * // App setting: Member.AdditionalInfo.Website = "Website|text|public"
     * // Member data: additional_info['Website'] = "https://example.com"
     * 
     * $info = $member->publicAdditionalInfo();
     * // Returns: ['Website' => 'https://example.com']
     * 
     * // Private fields are excluded regardless of data presence
     * ```
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
     * Check if the member can access a specific URL/route
     *
     * Provides URL-based authorization checking by analyzing route parameters
     * and determining the appropriate authorization context. This method handles
     * both entity-based routes (with specific record IDs) and general controller routes.
     *
     * ## Route Analysis Process
     * 1. **Table Resolution**: Attempts to map controller name to table class
     * 2. **Entity Loading**: For routes with record IDs, loads specific entity
     * 3. **Authorization Check**: Evaluates permission against entity or controller
     * 4. **Fallback Handling**: Uses general URL authorization for non-entity routes
     *
     * ## Route Patterns Supported
     * - **Entity Routes**: `/controller/action/123` - checks permission on specific entity
     * - **General Routes**: `/controller/action` - checks permission on empty entity
     * - **Custom Routes**: Non-standard routes fall back to URL-level authorization
     * - **Model Override**: Explicit 'model' parameter overrides controller mapping
     *
     * @param array $url Route array with controller, action, and optional parameters
     * @return bool True if member can access the URL, false otherwise
     *
     * @example
     * ```php
     * // Check access to view specific member
     * $canView = $member->canAccessUrl([
     *     'controller' => 'Members',
     *     'action' => 'view',
     *     0 => 123
     * ]);
     * 
     * // Check access to general members listing
     * $canList = $member->canAccessUrl([
     *     'controller' => 'Members',
     *     'action' => 'index'
     * ]);
     * 
     * // Override table mapping
     * $canAccess = $member->canAccessUrl([
     *     'controller' => 'CustomController',
     *     'action' => 'view',
     *     'model' => 'Members',
     *     0 => 123
     * ]);
     * ```
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
     * Get detailed authorization result with reasoning
     *
     * Similar to can() but returns a ResultInterface object containing detailed
     * information about the authorization decision, including reasons for denial.
     * This is useful for providing user feedback or debugging authorization issues.
     *
     * ## Result Information
     * - **Status**: Boolean pass/fail result
     * - **Reason**: Detailed explanation of authorization decision
     * - **Context**: Additional context about the authorization check
     *
     * @param string $action The action/operation being performed
     * @param mixed $resource The resource being operated on
     * @param mixed ...$optionalArgs Additional arguments passed to authorization policies
     * @return \Authorization\Policy\ResultInterface Detailed authorization result
     *
     * @example
     * ```php
     * $result = $member->canResult('edit', $restrictedEntity);
     * 
     * if (!$result->getStatus()) {
     *     $reason = $result->getReason();
     *     $this->Flash->error("Access denied: {$reason}");
     * }
     * ```
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
     * Get all permissions assigned to the member through their roles
     *
     * Retrieves the complete set of permissions available to this member by
     * analyzing their role assignments and loading the associated permissions.
     * Results are cached to avoid repeated database queries during a request.
     *
     * ## Permission Loading Process
     * 1. **Role Analysis**: Examines all active member role assignments
     * 2. **Permission Aggregation**: Collects unique permissions from all roles
     * 3. **Caching**: Stores results to improve performance on repeated calls
     * 4. **Branch Scoping**: Considers organizational hierarchy in permission grants
     *
     * ## Integration Points
     * - **PermissionsLoader**: Utilizes centralized permission loading service
     * - **Role System**: Depends on active MemberRole assignments
     * - **Caching**: Integrates with KMP caching strategy for performance
     *
     * @return array<\App\Model\Entity\Permission> Array of Permission entities
     *
     * @example
     * ```php
     * $permissions = $member->getPermissions();
     * 
     * foreach ($permissions as $permission) {
     *     echo "Permission: {$permission->name}\n";
     *     if ($permission->is_super_user) {
     *         echo "  - Super user permission\n";
     *     }
     * }
     * ```
     */
    public function getPermissions(): array
    {
        $permissions = PermissionsLoader::getPermissions($this->id);

        return $permissions;
    }

    /**
     * Get permission IDs for efficient permission checking
     *
     * Returns an array of permission IDs rather than full Permission entities,
     * which is more efficient for permission checking operations that only
     * need to verify membership in a permission set.
     *
     * ## Performance Optimization
     * - **Reduced Memory**: Only loads IDs instead of full entity objects
     * - **Fast Lookups**: Enables efficient in_array() or isset() checking
     * - **Cached Results**: Leverages PermissionsLoader caching for speed
     *
     * @return array<int> Array of permission ID integers
     *
     * @example
     * ```php
     * $permissionIds = $member->getPermissionIDs();
     * 
     * // Quick permission check
     * if (in_array($requiredPermissionId, $permissionIds)) {
     *     // Member has required permission
     * }
     * ```
     */
    public function getPermissionIDs(): array
    {
        $permissionIDs = Hash::extract(PermissionsLoader::getPermissions($this->id), '{n}.id');

        return $permissionIDs;
    }

    /**
     * Get member's authorization policies with optional branch filtering
     *
     * Retrieves the authorization policies available to this member, optionally
     * filtered by specific branch IDs. Policies define granular authorization
     * rules beyond simple permission checking.
     *
     * ## Branch Scoping
     * - **No Filter**: Returns all policies across all branches member has access to
     * - **With Filter**: Returns only policies applicable to specified branches
     * - **Security**: Prevents unauthorized access to policies outside member's scope
     *
     * ## Policy Types
     * Policies typically include rules for:
     * - Resource-specific authorization (view, edit, delete specific records)
     * - Contextual permissions (time-based, role-based, branch-based)
     * - Complex business rules (approval workflows, hierarchical access)
     *
     * @param array|null $branchIds Optional array of branch IDs to filter policies
     * @return array Array of policy configurations
     *
     * @example
     * ```php
     * // Get all policies for member
     * $allPolicies = $member->getPolicies();
     * 
     * // Get policies for specific branches
     * $branchPolicies = $member->getPolicies([1, 2, 3]);
     * 
     * // Process authorization policies
     * foreach ($branchPolicies as $policy) {
     *     // Apply policy-specific authorization logic
     * }
     * ```
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
     * Check if the member has super user privileges
     *
     * Determines whether any of the member's assigned permissions grant
     * super user status. Super users typically have unrestricted access
     * to all system functionality and can bypass normal authorization checks.
     *
     * ## Super User Detection
     * - **Permission Analysis**: Examines all member permissions
     * - **Flag Checking**: Looks for is_super_user flag on any permission
     * - **Security**: Used by authorization policies for privilege escalation
     *
     * ## Usage in Authorization
     * Super user status is commonly used in authorization policies to grant
     * unrestricted access or to enable administrative functionality.
     *
     * @return bool True if member has super user privileges, false otherwise
     *
     * @example
     * ```php
     * if ($member->isSuperUser()) {
     *     // Grant administrative access
     *     $this->enableAdminFeatures();
     * } else {
     *     // Apply normal authorization checks
     *     $this->checkStandardPermissions();
     * }
     * ```
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
     * Get all branch IDs where member has permission for a specific policy action
     *
     * This method returns an array of branch IDs where the member has permission to
     * perform a specific action on a given entity type. It's particularly useful for
     * populating dropdowns or autocomplete fields with branches that a user can
     * perform actions against.
     *
     * ## Authorization Integration
     * - **Policy Resolution**: Uses the member's policies to determine branch access
     * - **Entity-Based**: Takes an entity or table name to determine the policy class
     * - **Action-Based**: Filters by specific action (e.g., 'edit', 'view', 'delete')
     * - **Branch Scoping**: Returns only branches within member's authorized scope
     *
     * ## Return Values
     * - **null**: Member has global permission (all branches) or is super user
     * - **array**: Specific branch IDs where member has permission
     * - **empty array**: Member has no permission for this action
     *
     * ## Scoping Rules
     * The method respects permission scoping rules:
     * - **SCOPE_GLOBAL**: Returns null indicating all branches
     * - **SCOPE_BRANCH_ONLY**: Returns specific branch IDs
     * - **SCOPE_BRANCH_AND_CHILDREN**: Returns branch IDs with hierarchical access
     *
     * @param string $action The policy action/method name (e.g., 'edit', 'view', 'delete')
     * @param mixed $resource Entity instance, table name, or entity class to check permission for
     * @return array|null Array of branch IDs where permission granted, null for global access, empty array for no access
     *
     * @example
     * ```php
     * // Get branches where user can edit members
     * $editableBranches = $currentUser->getBranchIdsForAction('edit', 'Members');
     * 
     * if ($editableBranches === null) {
     *     // User has global edit permission - show all branches
     *     $branches = $branchesTable->find()->all();
     * } elseif (!empty($editableBranches)) {
     *     // User has limited edit permission - show only authorized branches
     *     $branches = $branchesTable->find()
     *         ->where(['id IN' => $editableBranches])
     *         ->all();
     * } else {
     *     // User has no edit permission - show no branches
     *     $branches = [];
     * }
     * 
     * // Use with specific entity
     * $member = $membersTable->newEmptyEntity();
     * $branchIds = $currentUser->getBranchIdsForAction('edit', $member);
     * 
     * // Populate dropdown in controller
     * $branches = $branchesTable->find('list')
     *     ->where(function ($exp) use ($branchIds) {
     *         if ($branchIds === null) {
     *             return $exp; // No filtering for global access
     *         }
     *         return $exp->in('id', $branchIds);
     *     })
     *     ->all();
     * $this->set(compact('branches'));
     * ```
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
     * Resolve the policy class name from a resource
     *
     * Helper method to determine the appropriate policy class for a given
     * resource (entity, table, or class name).
     *
     * @param mixed $resource Entity instance, table instance, or class name
     * @return string|null Fully qualified policy class name, or null if not resolvable
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
     * Get policy class from table name
     *
     * Converts a table name to its corresponding policy class name.
     *
     * @param string $tableName The table name (e.g., 'Members', 'Branches')
     * @return string Fully qualified policy class name
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
     * Review and update member status based on age progression
     *
     * Automatically updates member status when a minor member reaches age 18,
     * transitioning them to appropriate adult status levels based on their
     * current verification state. This method implements the KMP age-up workflow.
     *
     * ## Age-Up Transition Rules
     * - **Unverified Minor** → **Active**: Basic adult membership
     * - **Minor Parent Verified** → **Active**: Basic adult membership
     * - **Verified Minor** → **Verified Membership**: Full verified membership
     * - **Minor Membership Verified** → **Verified Membership**: Full verified membership
     * - **Already Adult**: No changes made
     *
     * ## System Integration
     * - **Parent Removal**: Clears parent_id when transitioning to adult status
     * - **Status Preservation**: Maintains verification level during transition
     * - **Automatic Processing**: Can be called during login or batch processing
     *
     * ## Business Rules
     * - Only processes members who are currently minors (under 18)
     * - Skips members who are already deactivated or have adult status
     * - Preserves member verification levels during transition
     *
     * @return void Modifies member status and parent_id properties directly
     *
     * @example
     * ```php
     * // Check for age-up during login process
     * $member->ageUpReview();
     * if ($member->isDirty('status')) {
     *     $this->Members->save($member);
     *     $this->Flash->success('Welcome! Your account has been upgraded to adult status.');
     * }
     * 
     * // Batch process age-ups
     * foreach ($minorMembers as $member) {
     *     $member->ageUpReview();
     *     if ($member->isDirty()) {
     *         $this->Members->save($member);
     *     }
     * }
     * ```
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
     * Review and update member's warrant eligibility status
     *
     * Evaluates the member's current qualifications for warrant eligibility
     * and updates the warrantable flag and non_warrantable_reasons accordingly.
     * This method should be called when member data changes that could affect
     * warrant eligibility.
     *
     * ## Warrant Eligibility Factors
     * - **Age Requirements**: Must be 18 or older
     * - **Membership Status**: Must have verified membership
     * - **Membership Currency**: Membership must not be expired
     * - **Required Information**: Legal name, address, phone number must be complete
     *
     * ## Integration Points
     * - **Warrant System**: Used to validate warrant request eligibility
     * - **Member Updates**: Called when profile information changes
     * - **Reporting**: Provides reasons for ineligibility for user feedback
     *
     * @return void Updates warrantable and non_warrantable_reasons properties
     *
     * @example
     * ```php
     * // Update warrant eligibility after profile changes
     * $member->street_address = $newAddress;
     * $member->warrantableReview();
     * 
     * if (!$member->warrantable) {
     *     $reasons = implode(', ', $member->non_warrantable_reasons);
     *     $this->Flash->error("Cannot request warrant: {$reasons}");
     * }
     * ```
     */
    public function warrantableReview(): void
    {
        $this->non_warrantable_reasons = $this->getNonWarrantableReasons();
    }

    /**
     * Evaluate and return reasons preventing warrant eligibility
     *
     * Performs comprehensive validation of member qualifications for warrant
     * eligibility and returns specific reasons for any disqualifying factors.
     * This method implements the complete warrant eligibility business logic.
     *
     * ## Eligibility Criteria Checked
     * 1. **Age Requirement**: Must be 18 years or older
     * 2. **Membership Status**: Must have STATUS_VERIFIED_MEMBERSHIP
     * 3. **Membership Currency**: Membership must not be expired
     * 4. **Legal Name**: First and last name must be provided
     * 5. **Complete Address**: Street, city, state, and ZIP must be provided
     * 6. **Contact Information**: Phone number must be provided
     *
     * ## Return Value Processing
     * - **Empty Array**: Member is warrant-eligible (sets warrantable = true)
     * - **Populated Array**: Member is ineligible (sets warrantable = false)
     * - **Specific Reasons**: Each disqualifying factor is listed separately
     *
     * @return array<string> Array of specific reasons preventing warrant eligibility
     *
     * @example
     * ```php
     * $reasons = $member->getNonWarrantableReasons();
     * 
     * if (empty($reasons)) {
     *     echo "Member is eligible for warrants";
     * } else {
     *     echo "Warrant eligibility issues:\n";
     *     foreach ($reasons as $reason) {
     *         echo "- {$reason}\n";
     *     }
     * }
     * 
     * // Typical reasons returned:
     * // - "Member is under 18"
     * // - "Membership is not verified"
     * // - "Membership is expired"
     * // - "Legal name is not set"
     * // - "Address is not set"
     * // - "Phone number is not set"
     * ```
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
     * Secure password setter with automatic hashing
     *
     * Automatically hashes passwords using CakePHP's DefaultPasswordHasher when
     * a non-empty password is set. This ensures passwords are never stored in
     * plain text and provides consistent hashing across the application.
     *
     * ## Security Features
     * - **Automatic Hashing**: Non-empty passwords are automatically hashed
     * - **Empty Protection**: Empty passwords are ignored (preserves existing hash)
     * - **Standard Algorithm**: Uses CakePHP's DefaultPasswordHasher (typically bcrypt)
     * - **Salt Generation**: Automatic salt generation for enhanced security
     *
     * @param string $value The plain text password to hash
     * @return string The hashed password or existing password if value is empty
     *
     * @example
     * ```php
     * // Password is automatically hashed when set
     * $member->password = 'mySecurePassword123';
     * // $member->password now contains hashed value
     * 
     * // Empty passwords are ignored
     * $member->password = '';
     * // $member->password retains previous hashed value
     * ```
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
     * Generate birthdate from birth month and year
     *
     * Creates a DateTime object representing the member's birth date using
     * the stored birth_month and birth_year values. Returns null if either
     * value is missing, as partial birth dates are not meaningful.
     *
     * ## Date Construction
     * - **Day**: Always set to 1st of the month (specific day not stored)
     * - **Month**: Uses birth_month value (1-12)
     * - **Year**: Uses birth_year value
     * - **Validation**: Returns null if either month or year is missing
     *
     * @return \Cake\I18n\DateTime|null Birth date or null if incomplete
     *
     * @example
     * ```php
     * $member->birth_month = 6;
     * $member->birth_year = 1990;
     * 
     * $birthdate = $member->birthdate;
     * // Returns DateTime for June 1, 1990
     * 
     * $member->birth_month = null;
     * $birthdate = $member->birthdate;
     * // Returns null (incomplete birth data)
     * ```
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
     * Generate formatted name for herald/announcement purposes
     *
     * Creates a comprehensive display name suitable for formal announcements,
     * herald publications, or ceremonial contexts. Combines title, SCA name,
     * pronunciation guide, and pronouns in a standardized format.
     *
     * ## Name Format Components
     * 1. **Title**: Honorific titles (Lord, Lady, Sir, etc.) - prefixed
     * 2. **SCA Name**: Primary SCA name - always included
     * 3. **Pronunciation**: Phonetic guide - in parentheses
     * 4. **Pronouns**: Preferred pronouns - after dash separator
     *
     * ## Format Pattern
     * `[Title] SCA_Name [(pronunciation)] [- pronouns]`
     *
     * @return string Formatted herald name with all available components
     *
     * @example
     * ```php
     * // Full name with all components
     * $member->title = 'Lord';
     * $member->sca_name = 'Aiden MacGregor';
     * $member->pronunciation = 'AY-den mac-GREG-or';
     * $member->pronouns = 'he/him';
     * 
     * echo $member->name_for_herald;
     * // Output: "Lord Aiden MacGregor (AY-den mac-GREG-or) - he/him"
     * 
     * // Minimal name (SCA name only)
     * $member->sca_name = 'Elena of the Woods';
     * echo $member->name_for_herald;
     * // Output: "Elena of the Woods"
     * ```
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
     * Validate and set member status
     *
     * Enforces business rules for member status by validating that only
     * predefined status constants are accepted. This prevents invalid status
     * values from being assigned and maintains data integrity.
     *
     * ## Valid Status Values
     * - `STATUS_ACTIVE`: Full access, verified adult members
     * - `STATUS_DEACTIVATED`: Restricted access, cannot login
     * - `STATUS_VERIFIED_MEMBERSHIP`: Verified membership, full access
     * - `STATUS_UNVERIFIED_MINOR`: Under 18, unverified, no login
     * - `STATUS_MINOR_MEMBERSHIP_VERIFIED`: Under 18, membership verified, no login
     * - `STATUS_MINOR_PARENT_VERIFIED`: Under 18, parent verified, can login
     * - `STATUS_VERIFIED_MINOR`: Under 18, fully verified, can login
     *
     * @param string $value The status value to validate and set
     * @return string The validated status value
     * @throws \InvalidArgumentException When an invalid status is provided
     *
     * @example
     * ```php
     * // Valid status assignment
     * $member->status = Member::STATUS_ACTIVE;
     * 
     * // Invalid status throws exception
     * try {
     *     $member->status = 'invalid_status';
     * } catch (InvalidArgumentException $e) {
     *     echo "Invalid status provided";
     * }
     * ```
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
     * Convert membership expiration date to string format
     *
     * Provides a string representation of the membership expiration date
     * for display purposes. Returns empty string for null dates to ensure
     * consistent output formatting.
     *
     * @return string Formatted date string or empty string if no expiration date
     *
     * @example
     * ```php
     * $member->membership_expires_on = new Date('2024-12-31');
     * echo $member->expires_on_to_string;
     * // Output: "2024-12-31"
     * 
     * $member->membership_expires_on = null;
     * echo $member->expires_on_to_string;
     * // Output: ""
     * ```
     */
    protected function _getExpiresOnToString()
    {
        if ($this->membership_expires_on == null) {
            return '';
        }

        return $this->membership_expires_on->toDateString();
    }

    /**
     * Calculate member's current age in years
     *
     * Computes the member's age based on birth_month and birth_year values
     * by comparing with the current date. Returns null if birth information
     * is incomplete, as partial age calculations are not meaningful.
     *
     * ## Age Calculation Logic
     * - **Birth Date**: Constructed as 1st day of birth month/year
     * - **Current Date**: Uses current system time for comparison
     * - **Precision**: Returns full years (integer) using DateInterval
     * - **Validation**: Returns null if month or year is missing
     *
     * ## Usage in Business Logic
     * - **Minor Status**: Used to determine if member is under 18
     * - **Age-Up Processing**: Triggers status changes when turning 18
     * - **Warrant Eligibility**: Must be 18+ for warrant eligibility
     * - **Privacy Controls**: Enhanced privacy for members under 18
     *
     * @return int|null Current age in years or null if birth data incomplete
     *
     * @example
     * ```php
     * $member->birth_month = 6;
     * $member->birth_year = 2000;
     * 
     * echo $member->age;
     * // Output: Current age (e.g., 24 if current year is 2024)
     * 
     * $member->birth_month = null;
     * echo $member->age;
     * // Output: null (incomplete birth data)
     * 
     * // Business logic usage
     * if ($member->age !== null && $member->age < 18) {
     *     $this->applyMinorPrivacyRules($member);
     * }
     * ```
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
     * Return the identity as a Member entity
     *
     * Provides a type-safe way to access the Member entity from contexts
     * where the identity might be wrapped or abstracted. This method satisfies
     * the KmpIdentityInterface requirement and ensures consistent access patterns.
     *
     * ## Interface Implementation
     * This method implements the KmpIdentityInterface::getAsMember() requirement,
     * allowing authorization and other systems to reliably access the underlying
     * Member entity regardless of identity wrapper layers.
     *
     * @return \App\Model\Entity\Member The current Member entity instance
     *
     * @example
     * ```php
     * // In authorization policies or services
     * $member = $identity->getAsMember();
     * 
     * // Now guaranteed to have Member-specific methods
     * if ($member->isSuperUser()) {
     *     // Grant super user access
     * }
     * 
     * $permissions = $member->getPermissions();
     * ```
     */
    public function getAsMember(): Member
    {
        return $this;
    }
}
