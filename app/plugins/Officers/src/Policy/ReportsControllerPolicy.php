<?php

namespace Officers\Policy;

use Authorization\Policy\RequestPolicyInterface;
use Cake\Http\ServerRequest;
use Authorization\Policy\ResultInterface;
use App\KMP\KmpIdentityInterface;
use App\Policy\BasePolicy;
use App\Model\Entity\BaseEntity;

/**
 * Officers Reports Controller Authorization Policy
 *
 * Provides URL-based authorization control for the Officers ReportsController.
 * Implements controller-level access control for officer reporting operations
 * including departmental roster reports and organizational analytics.
 *
 * @see /docs/5.1-officers-plugin.md
 */
class ReportsControllerPolicy extends BasePolicy
{
    /**
     * Check if user can access departmental officer roster reports.
     *
     * @param KmpIdentityInterface $user The authenticated user
     * @param array $urlProps URL properties providing departmental context
     * @return bool
     */
    public function canDepartmentOfficersRoster(
        KmpIdentityInterface $user,
        array $urlProps,
    ): bool {
        $method = __FUNCTION__;
        return $this->_hasPolicyForUrl($user, $method, $urlProps);
    }
}
