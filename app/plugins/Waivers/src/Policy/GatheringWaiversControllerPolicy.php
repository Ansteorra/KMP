<?php

declare(strict_types=1);

namespace Waivers\Policy;

use App\KMP\KmpIdentityInterface;
use App\Policy\BasePolicy;
use Authorization\Policy\ResultInterface;

/**
 * GatheringWaivers Controller Authorization Policy
 *
 * Provides URL-based authorization for GatheringWaiversController actions.
 *
 * @see /docs/5.7-waivers-plugin.md
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
