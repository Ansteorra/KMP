<?php

declare(strict_types=1);

namespace App\Model\Entity;

use App\KMP\KmpIdentityInterface;
use App\KMP\PermissionsLoader;
use ArrayAccess;
use Authentication\IdentityInterface as AuthenticationIdentity;
use Authorization\AuthorizationServiceInterface;
use Authorization\IdentityInterface as AuthorizationIdentity;
use Authorization\Policy\ResultInterface;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use Cake\Utility\Security;

/**
 * ServicePrincipal Entity - API Client Identity for Third-Party Integrations
 *
 * Implements authentication and authorization for API clients.
 * Works like Member but for service-to-service integrations.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $client_id
 * @property string $client_secret_hash
 * @property bool $is_active
 * @property array|null $ip_allowlist
 * @property \Cake\I18n\DateTime|null $last_used_at
 * @property int|null $created_by
 * @property int|null $modified_by
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 * @property \App\Model\Entity\ServicePrincipalRole[] $service_principal_roles
 * @property \App\Model\Entity\ServicePrincipalToken[] $service_principal_tokens
 */
class ServicePrincipal extends BaseEntity implements
    KmpIdentityInterface,
    AuthorizationIdentity,
    AuthenticationIdentity
{
    /** @var \Authorization\AuthorizationServiceInterface|null */
    protected ?AuthorizationServiceInterface $authorization = null;

    /** @var array|null Cached permissions */
    protected ?array $_permissions = null;

    /**
     * Fields accessible for mass assignment.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'name' => true,
        'description' => true,
        'is_active' => true,
        'ip_allowlist' => true,
        'last_used_at' => true,
        'modified' => true,
    ];

    /** @var array<string> Fields hidden from serialization */
    protected array $_hidden = [
        'client_secret_hash',
    ];

    /**
     * Generate a new client ID with recognizable prefix.
     *
     * @return string Client ID in format kmp_sp_XXXX...
     */
    public static function generateClientId(): string
    {
        return 'kmp_sp_' . bin2hex(Security::randomBytes(12));
    }

    /**
     * Generate a new client secret (plain text - hash before storing).
     *
     * @return string 64-byte base64-encoded secret
     */
    public static function generateClientSecret(): string
    {
        return base64_encode(Security::randomBytes(48));
    }

    /**
     * Hash a client secret for storage.
     *
     * @param string $secret Plain text secret
     * @return string Hashed secret
     */
    public static function hashSecret(string $secret): string
    {
        return password_hash($secret, PASSWORD_BCRYPT, ['cost' => 10]);
    }

    /**
     * Verify a client secret against stored hash.
     *
     * @param string $secret Plain text secret to verify
     * @return bool True if secret matches
     */
    public function verifySecret(string $secret): bool
    {
        return password_verify($secret, $this->client_secret_hash);
    }

    /**
     * Check if an IP address is allowed for this service principal.
     *
     * @param string $ipAddress IP address to check
     * @return bool True if allowed (or no restrictions set)
     */
    public function isIpAllowed(string $ipAddress): bool
    {
        if (empty($this->ip_allowlist)) {
            return true;
        }

        foreach ($this->ip_allowlist as $allowed) {
            if ($this->ipMatches($ipAddress, $allowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if IP matches an allowlist entry (supports CIDR notation).
     *
     * @param string $ip IP to check
     * @param string $allowed Allowed IP or CIDR range
     * @return bool
     */
    protected function ipMatches(string $ip, string $allowed): bool
    {
        if (strpos($allowed, '/') === false) {
            return $ip === $allowed;
        }

        [$subnet, $bits] = explode('/', $allowed);
        $bits = (int)$bits;
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }
        $mask = -1 << (32 - $bits);

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }

    /**
     * Get the decorated identity.
     *
     * @return \ArrayAccess|array
     */
    public function getOriginalData(): ArrayAccess|array
    {
        return $this;
    }

    /**
     * Set the authorization service.
     *
     * @param \Authorization\AuthorizationServiceInterface $service
     * @return self
     */
    public function setAuthorization(AuthorizationServiceInterface $service): self
    {
        $this->authorization = $service;
        return $this;
    }

    /**
     * Get the identity identifier.
     *
     * @return array|string|int|null
     */
    public function getIdentifier(): array|string|int|null
    {
        return $this->id;
    }

    /**
     * Check if service principal can perform action on resource.
     *
     * @param string $action The action
     * @param mixed $resource The resource
     * @param mixed ...$optionalArgs Additional args
     * @return bool
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
     * @return bool
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
     * Get detailed authorization result.
     *
     * @param string $action The action
     * @param mixed $resource The resource
     * @param mixed ...$optionalArgs Additional args
     * @return \Authorization\Policy\ResultInterface
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
     * Apply authorization scope.
     *
     * @param string $action The action
     * @param mixed $resource The resource
     * @param mixed ...$optionalArgs Additional args
     * @return mixed
     */
    public function applyScope(string $action, mixed $resource, mixed ...$optionalArgs): mixed
    {
        return $this->authorization->applyScope($this, $action, $resource, ...$optionalArgs);
    }

    /**
     * Get permissions for this service principal based on assigned roles.
     *
     * @return array<\App\Model\Entity\Permission>
     */
    public function getPermissions(): array
    {
        if ($this->_permissions === null) {
            $this->_permissions = PermissionsLoader::getServicePrincipalPermissions($this->id);
        }
        return $this->_permissions;
    }

    /**
     * Get permission IDs for efficient permission checking.
     *
     * @return array<int>
     */
    public function getPermissionIDs(): array
    {
        return Hash::extract($this->getPermissions(), '{n}.id');
    }

    /**
     * Get policies for this service principal.
     *
     * @param array|null $branchIds Optional branch filter
     * @return array
     */
    public function getPolicies(?array $branchIds = null): array
    {
        return PermissionsLoader::getServicePrincipalPolicies($this->id, $branchIds);
    }

    /**
     * Check if service principal has super user privileges.
     *
     * @return bool
     */
    public function isSuperUser(): bool
    {
        foreach ($this->getPermissions() as $permission) {
            if ($permission->is_super_user) {
                return true;
            }
        }
        return false;
    }

    /**
     * Return a "member" representation - for service principals, return null.
     * This signals to systems that this is not a human member.
     *
     * @return \App\Model\Entity\Member
     */
    public function getAsMember(): Member
    {
        // Service principals don't have a member equivalent
        // Return a placeholder/dummy member for interface compatibility
        $member = new Member();
        $member->id = null;
        $member->sca_name = '[Service: ' . $this->name . ']';
        return $member;
    }

    /**
     * Check if service principal is a service principal (always true).
     *
     * @return bool
     */
    public function isServicePrincipal(): bool
    {
        return true;
    }

    /**
     * Get display name for audit logs.
     *
     * @return string
     */
    protected function _getDisplayName(): string
    {
        return 'Service Principal: ' . $this->name;
    }
}
