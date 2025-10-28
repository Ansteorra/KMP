<?php

declare(strict_types=1);

namespace Waivers\Policy;

use App\KMP\KmpIdentityInterface;
use App\Policy\BasePolicy;
use Authorization\Policy\ResultInterface;

/**
 * GatheringWaivers Controller Authorization Policy
 * 
 * Provides URL-based authorization control for the GatheringWaiversController.
 * This policy governs access to waiver management operations including upload,
 * viewing, and administrative actions for gathering waivers.
 * 
 * ## Authorization Architecture
 * 
 * The policy extends BasePolicy to provide controller-level authorization through:
 * - **URL-Based Authorization**: Uses _hasPolicyForUrl() for action-level checks
 * - **Permission Integration**: Integrates with KMP's RBAC permission system
 * - **Branch Scoping**: Supports branch-based access control for waivers
 * 
 * ## Usage Patterns
 * 
 * The navigation system and controller actions use this policy:
 * ```php
 * // Navigation checks authorization for menu items
 * $this->Authorization->can($user, 'needingWaivers', $urlProps);
 * 
 * // Controller checks for action access
 * $this->Authorization->authorize($this->request, 'needingWaivers');
 * ```
 * 
 * @see \App\Policy\BasePolicy For base authorization methods
 * @see \Waivers\Controller\GatheringWaiversController For controller actions
 */
class GatheringWaiversControllerPolicy extends BasePolicy
{
    /**
     * Check if user can access needingWaivers action
     *
     * Determines if the user can view the list of gatherings that need waivers.
     * This action shows gatherings where the user has permission to upload waivers
     * and required waivers are missing.
     *
     * @param \App\KMP\KmpIdentityInterface $user User identity
     * @param array $urlProps URL properties for the action
     * @return \Authorization\Policy\ResultInterface|bool Authorization result
     */
    public function canNeedingWaivers(
        KmpIdentityInterface $user,
        array $urlProps,
    ): ResultInterface|bool {
        $method = __FUNCTION__;

        return $this->_hasPolicyForUrl($user, $method, $urlProps);
    }

    /**
     * Check if user can access upload action
     *
     * @param \App\KMP\KmpIdentityInterface $user User identity
     * @param array $urlProps URL properties for the action
     * @return \Authorization\Policy\ResultInterface|bool Authorization result
     */
    public function canUpload(
        KmpIdentityInterface $user,
        array $urlProps,
    ): ResultInterface|bool {
        $method = __FUNCTION__;

        return $this->_hasPolicyForUrl($user, $method, $urlProps);
    }

    /**
     * Check if user can access changeWaiverType action
     *
     * @param \App\KMP\KmpIdentityInterface $user User identity
     * @param array $urlProps URL properties for the action
     * @return \Authorization\Policy\ResultInterface|bool Authorization result
     */
    public function canChangeWaiverType(
        KmpIdentityInterface $user,
        array $urlProps,
    ): ResultInterface|bool {
        $method = __FUNCTION__;

        return $this->_hasPolicyForUrl($user, $method, $urlProps);
    }

    /**
     * Check if user can access changeActivities action
     *
     * @param \App\KMP\KmpIdentityInterface $user User identity
     * @param array $urlProps URL properties for the action
     * @return \Authorization\Policy\ResultInterface|bool Authorization result
     */
    public function canChangeActivities(
        KmpIdentityInterface $user,
        array $urlProps,
    ): ResultInterface|bool {
        $method = __FUNCTION__;

        return $this->_hasPolicyForUrl($user, $method, $urlProps);
    }

    /**
     * Check if user can access dashboard action
     *
     * Determines if the user can view the comprehensive waiver secretary dashboard.
     * This provides access to waiver statistics, compliance overview, and administrative
     * tools for managing waivers across all accessible branches.
     *
     * @param \App\KMP\KmpIdentityInterface $user User identity
     * @param array $urlProps URL properties for the action
     * @return \Authorization\Policy\ResultInterface|bool Authorization result
     */
    public function canDashboard(
        KmpIdentityInterface $user,
        array $urlProps,
    ): ResultInterface|bool {
        $method = __FUNCTION__;

        return $this->_hasPolicyForUrl($user, $method, $urlProps);
    }
}
