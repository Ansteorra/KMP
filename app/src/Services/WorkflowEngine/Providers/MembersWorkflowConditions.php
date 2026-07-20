<?php

declare(strict_types=1);

namespace App\Services\WorkflowEngine\Providers;

use App\Model\Entity\Member;
use App\Services\WorkflowEngine\WorkflowContextAwareTrait;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Workflow condition evaluators for member lifecycle checks.
 *
 * Each method accepts workflow context and config, returns bool.
 */
class MembersWorkflowConditions
{
    use WorkflowContextAwareTrait;
    use LocatorAwareTrait;

    /**
     * Check if a member is a minor (age < 18).
     *
     * @param array $context Current workflow context
     * @param array $config Config with memberId
     * @return bool
     */
    public function isMinor(array $context, array $config): bool
    {
        try {
            $memberId = $this->resolveValue($config['memberId'] ?? null, $context);
            if (empty($memberId)) {
                return false;
            }

            $membersTable = $this->fetchTable('Members');
            $member = $membersTable->get((int)$memberId);

            $age = $member->age;

            return $age !== null && $age < 18;
        } catch (\Throwable $e) {
            Log::error('Condition IsMinor failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Check if a member is an adult (age >= 18).
     *
     * @param array $context Current workflow context
     * @param array $config Config with memberId
     * @return bool
     */
    public function isAdult(array $context, array $config): bool
    {
        try {
            $memberId = $this->resolveValue($config['memberId'] ?? null, $context);
            if (empty($memberId)) {
                return false;
            }

            $membersTable = $this->fetchTable('Members');
            $member = $membersTable->get((int)$memberId);

            $age = $member->age;

            return $age !== null && $age >= 18;
        } catch (\Throwable $e) {
            Log::error('Condition IsAdult failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Check if a member has a valid (non-expired) membership.
     *
     * @param array $context Current workflow context
     * @param array $config Config with memberId
     * @return bool
     */
    public function hasValidMembership(array $context, array $config): bool
    {
        try {
            $memberId = $this->resolveValue($config['memberId'] ?? null, $context);
            if (empty($memberId)) {
                return false;
            }

            $membersTable = $this->fetchTable('Members');
            $member = $membersTable->get((int)$memberId);

            if ($member->membership_expires_on === null) {
                return false;
            }

            return !$member->membership_expires_on->isPast();
        } catch (\Throwable $e) {
            Log::error('Condition HasValidMembership failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Check if a member is warrantable.
     *
     * @param array $context Current workflow context
     * @param array $config Config with memberId
     * @return bool
     */
    public function isWarrantable(array $context, array $config): bool
    {
        try {
            $memberId = $this->resolveValue($config['memberId'] ?? null, $context);
            if (empty($memberId)) {
                return false;
            }

            $membersTable = $this->fetchTable('Members');
            $member = $membersTable->get((int)$memberId);

            return (bool)$member->warrantable;
        } catch (\Throwable $e) {
            Log::error('Condition IsWarrantable failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Check if a member status is active (any active-capable status).
     *
     * @param array $context Current workflow context
     * @param array $config Config with memberId
     * @return bool
     */
    public function isActive(array $context, array $config): bool
    {
        try {
            $memberId = $this->resolveValue($config['memberId'] ?? null, $context);
            if (empty($memberId)) {
                return false;
            }

            $membersTable = $this->fetchTable('Members');
            $member = $membersTable->get((int)$memberId);

            $activeStatuses = [
                Member::STATUS_ACTIVE,
                Member::STATUS_VERIFIED_MEMBERSHIP,
                Member::STATUS_MINOR_PARENT_VERIFIED,
                Member::STATUS_VERIFIED_MINOR,
            ];

            return in_array($member->status, $activeStatuses, true);
        } catch (\Throwable $e) {
            Log::error('Condition IsActive failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Check if a member has an email address set.
     *
     * @param array $context Current workflow context
     * @param array $config Config with memberId
     * @return bool
     */
    public function hasEmailAddress(array $context, array $config): bool
    {
        try {
            $memberId = $this->resolveValue($config['memberId'] ?? null, $context);
            if (empty($memberId)) {
                return false;
            }

            $membersTable = $this->fetchTable('Members');
            $member = $membersTable->get((int)$memberId);

            return !empty($member->email_address);
        } catch (\Throwable $e) {
            Log::error('Condition HasEmailAddress failed: ' . $e->getMessage());

            return false;
        }
    }
}
