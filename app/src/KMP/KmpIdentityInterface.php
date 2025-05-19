<?php
declare(strict_types=1);

namespace App\KMP;

use App\Model\Entity\Member;
use Authorization\AuthorizationServiceInterface;
use Authorization\IdentityInterface as CakeIdentityInterface;

interface KmpIdentityInterface extends CakeIdentityInterface
{
    /**
     * Get the identity as a Member entity.
     *
     * @return \App\Model\Entity\Member
     */
    public function getAsMember(): Member;

    public function getIdentifier(): array|string|int|null;

    /**
     * sets the authorization service.
     *
     * @return \App\KMP\KmpIdentityInterface
     */
    public function setAuthorization(AuthorizationServiceInterface $auth): self;

    /**
     * Get permissions for the Member based on their roles
     *
     * @return array<\App\Model\Entity\Permission>
     */
    public function getPermissions(): array;

    /**
     * Get permission IDs for the Member based on their roles
     *
     * @return array<int>
     */
    public function getPermissionIDs(): array;

    /**
     * Get policies for the Member based on their roles and optionally filtered by branch IDs
     *
     * @param array<int>|null $branchIds Optional array of branch IDs to filter policies
     * @return array
     */
    public function getPolicies(?array $branchIds = null): array;

    /**
     * Check if one of the users roles grants them super user
     *
     * @return bool
     */
    public function isSuperUser(): bool;

    /**
     * Check if the user has a specific permission
     *
     * @param string $action The action/operation being performed.
     * @param mixed $resource The resource being operated on.
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function checkCan(string $action, mixed $resource, mixed ...$optionalArgs): bool;
}
