<?php

declare(strict_types=1);

namespace Activities\Services;

use App\Services\WorkflowEngine\WorkflowContextAwareTrait;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;

/**
 * Workflow condition evaluators for the Activities plugin.
 *
 * Each method accepts workflow context and config, returns bool.
 */
class ActivitiesWorkflowConditions
{
    use WorkflowContextAwareTrait;

    /**
     * Check if a member is eligible to renew an authorization for an activity.
     *
     * @param array $context Current workflow context
     * @param array $config Config with memberId, activityId
     * @return bool
     */
    public function isRenewalEligible(array $context, array $config): bool
    {
        try {
            $memberId = $this->resolveValue($config['memberId'] ?? null, $context);
            $activityId = $this->resolveValue($config['activityId'] ?? null, $context);

            if (empty($memberId) || empty($activityId)) {
                return false;
            }

            $authTable = TableRegistry::getTableLocator()->get('Activities.Authorizations');

            // Must have an active, non-expired authorization
            $hasActive = $authTable->find()
                ->where([
                    'member_id' => (int)$memberId,
                    'activity_id' => (int)$activityId,
                    'status' => 'Approved',
                    'expires_on >' => DateTime::now(),
                ])
                ->count();

            if ($hasActive === 0) {
                return false;
            }

            // Must not have a pending request already
            $hasPending = $authTable->find()
                ->where([
                    'member_id' => (int)$memberId,
                    'activity_id' => (int)$activityId,
                    'status' => 'Pending',
                ])
                ->count();

            return $hasPending === 0;
        } catch (\Throwable $e) {
            Log::error('Condition IsRenewalEligible failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if an authorization has the required number of approvals.
     *
     * Uses the authorization's approval_count field (maintained by the workflow
     * engine) rather than counting legacy approval records.
     *
     * @param array $context Current workflow context
     * @param array $config Config with authorizationId
     * @return bool
     */
    public function hasRequiredApprovals(array $context, array $config): bool
    {
        try {
            $authorizationId = $this->resolveValue($config['authorizationId'] ?? null, $context);

            if (empty($authorizationId)) {
                return false;
            }

            $authTable = TableRegistry::getTableLocator()->get('Activities.Authorizations');
            $authorization = $authTable->find()
                ->contain(['Activities'])
                ->where(['Authorizations.id' => (int)$authorizationId])
                ->first();

            if (!$authorization || !$authorization->activity) {
                return false;
            }

            $activity = $authorization->activity;
            $requiredApprovals = $authorization->is_renewal
                ? ($activity->num_required_renewers ?? 1)
                : ($activity->num_required_authorizors ?? 1);

            return ($authorization->approval_count ?? 0) >= $requiredApprovals;
        } catch (\Throwable $e) {
            Log::error('Condition HasRequiredApprovals failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a member meets the age requirement for an activity.
     *
     * @param array $context Current workflow context
     * @param array $config Config with memberId, activityId
     * @return bool
     */
    public function memberMeetsAgeRequirement(array $context, array $config): bool
    {
        try {
            $memberId = $this->resolveValue($config['memberId'] ?? null, $context);
            $activityId = $this->resolveValue($config['activityId'] ?? null, $context);

            if (empty($memberId) || empty($activityId)) {
                return false;
            }

            $memberTable = TableRegistry::getTableLocator()->get('Members');
            $member = $memberTable->get((int)$memberId);

            $activityTable = TableRegistry::getTableLocator()->get('Activities.Activities');
            $activity = $activityTable->get((int)$activityId);

            $memberAge = $member->age;

            // If age can't be determined, allow (no birth data is not a disqualifier)
            if ($memberAge === null) {
                return true;
            }

            if ($activity->minimum_age !== null && $memberAge < $activity->minimum_age) {
                return false;
            }

            if ($activity->maximum_age !== null && $memberAge > $activity->maximum_age) {
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Condition MemberMeetsAgeRequirement failed: ' . $e->getMessage());
            return false;
        }
    }
}
