<?php

declare(strict_types=1);

namespace Activities\Policy;

use Authorization\Policy\RequestPolicyInterface;
use Cake\Http\ServerRequest;
use Authorization\Policy\ResultInterface;
use App\KMP\KmpIdentityInterface;
use App\Policy\BasePolicy;
use App\Model\Entity\BaseEntity;

/**
 * Controller-level authorization policy for Activities Reports.
 *
 * Controls access to activity reporting interfaces including authorization
 * reports and warrant rosters. Uses URL-based policy evaluation via BasePolicy.
 *
 * @package Activities\Policy
 * @see \App\Policy\BasePolicy For inherited RBAC and URL-based authorization
 * @see /docs/5.6.5-activity-security-patterns.md For security patterns
 */
class ReportsControllerPolicy extends BasePolicy
{
    /**
     * Check if user can access activity warrant roster reports.
     *
     * @param \App\KMP\KmpIdentityInterface $user The requesting user
     * @param array $urlProps URL properties for authorization context
     * @return bool True if user has permission for warrant roster access
     */
    public function canActivityWarrantsRoster(
        KmpIdentityInterface $user,
        array $urlProps,
    ): bool {
        $method = __FUNCTION__;
        return $this->_hasPolicyForUrl($user, $method, $urlProps);
    }

    /**
     * Check if user can access activity authorization reports.
     *
     * @param \App\KMP\KmpIdentityInterface $user The requesting user
     * @param array $urlProps URL properties for authorization context
     * @return bool True if user has permission for authorization reports
     */
    public function canAuthorizations(
        KmpIdentityInterface $user,
        array $urlProps,
    ): bool {
        $method = __FUNCTION__;
        return $this->_hasPolicyForUrl($user, $method, $urlProps);
    }
}
