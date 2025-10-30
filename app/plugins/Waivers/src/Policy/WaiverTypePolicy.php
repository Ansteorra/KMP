<?php

declare(strict_types=1);

namespace Waivers\Policy;

use App\Policy\BasePolicy;

/**
 * WaiverType Policy
 *
 * Manages authorization for waiver type operations.
 * Authorization is driven by the Roles → Permissions → Policies system.
 */
class WaiverTypePolicy extends BasePolicy
{
    /**
     * Check if the user can toggle the active status of a waiver type.
     *
     * Determines authorization for activating or deactivating waiver types,
     * which affects their availability for gathering configuration.
     *
     * **Authorization Logic:**
     * - Delegates to permission-based policy evaluation
     * - Typically requires Kingdom officer permissions
     * - Same requirements as editing a waiver type
     *
     * **Use Case:**
     * - Kingdom officers can enable/disable waiver types
     * - Deactivated types remain in database but are hidden from selection
     * - Useful for retiring outdated waiver types without deletion
     *
     * @param \App\KMP\KmpIdentityInterface $user The user attempting to toggle status
     * @param \App\Model\Entity\BaseEntity $entity The waiver type entity
     * @param mixed ...$optionalArgs Additional arguments for policy evaluation
     * @return bool True if user can toggle active status, false otherwise
     * @see \App\Policy\BasePolicy::_hasPolicy() Core permission validation
     */
    public function canToggleActive(\App\KMP\KmpIdentityInterface $user, \App\Model\Entity\BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if the user can download waiver type templates.
     *
     * Determines authorization for downloading PDF templates associated with waiver types.
     * This allows users to view and print blank waiver forms.
     *
     * **Authorization Logic:**
     * - Delegates to permission-based policy evaluation
     * - Typically allows any authenticated user to download templates
     * - Templates are blank forms, not containing sensitive data
     *
     * **Use Case:**
     * - Gathering stewards download templates for manual collection
     * - Kingdom officers review template content
     * - Members preview waivers before attending gatherings
     * - Templates can be external URLs or uploaded PDFs
     *
     * @param \App\KMP\KmpIdentityInterface $user The user attempting to download
     * @param \App\Model\Entity\BaseEntity $entity The waiver type entity
     * @param mixed ...$optionalArgs Additional arguments for policy evaluation
     * @return bool True if user can download template, false otherwise
     * @see \App\Policy\BasePolicy::_hasPolicy() Core permission validation
     */
    public function canDownloadTemplate(\App\KMP\KmpIdentityInterface $user, \App\Model\Entity\BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }
}