<?php

declare(strict_types=1);

namespace App\Services\WorkflowEngine\Providers;

use App\Model\Entity\Warrant;
use App\Model\Entity\WarrantRoster;
use App\Services\WorkflowEngine\WorkflowContextAwareTrait;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;

/**
 * Workflow condition evaluators for warrant operations.
 *
 * Each method accepts workflow context and config, returns bool.
 */
class WarrantWorkflowConditions
{
    use WorkflowContextAwareTrait;

    /**
     * Check if a member is eligible to receive warrants.
     *
     * Verifies the warrantable flag AND that membership has not expired.
     *
     * @param array $context Current workflow context
     * @param array $config Config with memberId
     * @return bool
     */
    public function isMemberWarrantable(array $context, array $config): bool
    {
        try {
            $memberId = $this->resolveValue($config['memberId'] ?? null, $context);

            if (empty($memberId)) {
                return false;
            }

            $memberTable = TableRegistry::getTableLocator()->get('Members');
            $member = $memberTable->get((int)$memberId);

            if (!$member->warrantable) {
                return false;
            }

            if (
                $member->membership_expires_on !== null
                && $member->membership_expires_on < DateTime::now()
            ) {
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Condition IsMemberWarrantable failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Check if a roster has the required number of approvals.
     *
     * @param array $context Current workflow context
     * @param array $config Config with rosterId
     * @return bool
     */
    public function hasRequiredApprovals(array $context, array $config): bool
    {
        try {
            $rosterId = $this->resolveValue($config['rosterId'] ?? null, $context);

            if (empty($rosterId)) {
                return false;
            }

            $rosterTable = TableRegistry::getTableLocator()->get('WarrantRosters');
            $roster = $rosterTable->get((int)$rosterId);

            return $roster->hasRequiredApprovals();
        } catch (\Throwable $e) {
            Log::error('Condition HasRequiredApprovals failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Check if the current date falls within a warrant period.
     *
     * @param array $context Current workflow context
     * @param array $config Config with startOn, expiresOn
     * @return bool
     */
    public function isWithinWarrantPeriod(array $context, array $config): bool
    {
        try {
            $startOnRaw = $this->resolveValue($config['startOn'] ?? null, $context);
            $expiresOnRaw = $this->resolveValue($config['expiresOn'] ?? null, $context);

            if ($startOnRaw === null) {
                return false;
            }

            $startOn = $startOnRaw instanceof DateTime ? $startOnRaw : new DateTime($startOnRaw);
            $now = DateTime::now();

            if ($now < $startOn) {
                return false;
            }

            if ($expiresOnRaw !== null) {
                $expiresOn = $expiresOnRaw instanceof DateTime ? $expiresOnRaw : new DateTime($expiresOnRaw);
                if ($now > $expiresOn) {
                    return false;
                }
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Condition IsWithinWarrantPeriod failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Check if a roster has been fully approved.
     *
     * @param array $context Current workflow context
     * @param array $config Config with rosterId
     * @return bool
     */
    public function isRosterApproved(array $context, array $config): bool
    {
        try {
            $rosterId = $this->resolveValue($config['rosterId'] ?? null, $context);

            if (empty($rosterId)) {
                return false;
            }

            $rosterTable = TableRegistry::getTableLocator()->get('WarrantRosters');
            $roster = $rosterTable->get((int)$rosterId);

            return $roster->status === WarrantRoster::STATUS_APPROVED;
        } catch (\Throwable $e) {
            Log::error('Condition IsRosterApproved failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Check if a specific warrant is currently active.
     *
     * @param array $context Current workflow context
     * @param array $config Config with warrantId
     * @return bool
     */
    public function isWarrantActive(array $context, array $config): bool
    {
        try {
            $warrantId = $this->resolveValue($config['warrantId'] ?? null, $context);

            if (empty($warrantId)) {
                return false;
            }

            $warrantTable = TableRegistry::getTableLocator()->get('Warrants');
            $warrant = $warrantTable->get((int)$warrantId);

            return $warrant->status === Warrant::CURRENT_STATUS;
        } catch (\Throwable $e) {
            Log::error('Condition IsWarrantActive failed: ' . $e->getMessage());

            return false;
        }
    }
}
