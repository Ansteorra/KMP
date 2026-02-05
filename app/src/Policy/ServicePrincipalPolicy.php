<?php

declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\ServicePrincipal;

/**
 * ServicePrincipal Entity Policy
 *
 * Manages access control for service principal entities.
 * Only super users can manage service principals.
 */
class ServicePrincipalPolicy extends BasePolicy
{
    /**
     * Check if user can view a service principal.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \App\Model\Entity\ServicePrincipal $servicePrincipal The entity
     * @return bool
     */
    public function canView(KmpIdentityInterface $user, $servicePrincipal, ...$optionalArgs): bool
    {
        // Super users can view all
        if ($this->_isSuperUser($user)) {
            return true;
        }

        // Service principals can view themselves
        if ($user instanceof ServicePrincipal && $user->id === $servicePrincipal->id) {
            return true;
        }

        return $this->_hasPolicy($user, __FUNCTION__, $servicePrincipal);
    }

    /**
     * Check if user can add a service principal.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \App\Model\Entity\ServicePrincipal $servicePrincipal The entity
     * @return bool
     */
    public function canAdd(KmpIdentityInterface $user, $servicePrincipal, ...$optionalArgs): bool
    {
        return $this->_hasPolicy($user, __FUNCTION__, $servicePrincipal);
    }

    /**
     * Check if user can edit a service principal.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \App\Model\Entity\ServicePrincipal $servicePrincipal The entity
     * @return bool
     */
    public function canEdit(KmpIdentityInterface $user, $servicePrincipal, ...$optionalArgs): bool
    {
        return $this->_hasPolicy($user, __FUNCTION__, $servicePrincipal);
    }

    /**
     * Check if user can delete a service principal.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \App\Model\Entity\ServicePrincipal $servicePrincipal The entity
     * @return bool
     */
    public function canDelete(KmpIdentityInterface $user, $servicePrincipal, ...$optionalArgs): bool
    {
        return $this->_hasPolicy($user, __FUNCTION__, $servicePrincipal);
    }

    /**
     * Check if user can regenerate credentials.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \App\Model\Entity\ServicePrincipal $servicePrincipal The entity
     * @return bool
     */
    public function canRegenerateCredentials(KmpIdentityInterface $user, ServicePrincipal $servicePrincipal): bool
    {
        return $this->_hasPolicy($user, 'canEdit', $servicePrincipal);
    }

    /**
     * Check if user can manage tokens.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \App\Model\Entity\ServicePrincipal $servicePrincipal The entity
     * @return bool
     */
    public function canManageTokens(KmpIdentityInterface $user, ServicePrincipal $servicePrincipal): bool
    {
        return $this->_hasPolicy($user, 'canEdit', $servicePrincipal);
    }

    /**
     * Check if user can manage roles.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \App\Model\Entity\ServicePrincipal $servicePrincipal The entity
     * @return bool
     */
    public function canManageRoles(KmpIdentityInterface $user, ServicePrincipal $servicePrincipal): bool
    {
        return $this->_hasPolicy($user, 'canEdit', $servicePrincipal);
    }
}
