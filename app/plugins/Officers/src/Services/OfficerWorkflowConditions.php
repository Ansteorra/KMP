<?php

declare(strict_types=1);

namespace Officers\Services;

use Cake\I18n\DateTime;
use App\Services\WorkflowEngine\WorkflowContextAwareTrait;
use Cake\ORM\TableRegistry;

/**
 * Workflow condition evaluators for Officers plugin.
 */
class OfficerWorkflowConditions
{
    use WorkflowContextAwareTrait;

    /**
     * Check if an office requires a warrant.
     *
     * @param array $context Current workflow context
     * @param array $config Config with 'officeId'
     * @return bool
     */
    public function officeRequiresWarrant(array $context, array $config): bool
    {
        try {
            $officeId = $this->resolveValue($config['officeId'] ?? null, $context);

            if (empty($officeId)) {
                return false;
            }

            $officeTable = TableRegistry::getTableLocator()->get('Officers.Offices');
            $office = $officeTable->get((int)$officeId);

            return (bool)$office->requires_warrant;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Check if an office allows only one officer per branch.
     *
     * @param array $context Current workflow context
     * @param array $config Config with 'officeId'
     * @return bool
     */
    public function isOnlyOnePerBranch(array $context, array $config): bool
    {
        try {
            $officeId = $this->resolveValue($config['officeId'] ?? null, $context);

            if (empty($officeId)) {
                return false;
            }

            $officeTable = TableRegistry::getTableLocator()->get('Officers.Offices');
            $office = $officeTable->get((int)$officeId);

            return (bool)$office->only_one_per_branch;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Check if a member is warrantable (meets all warrant eligibility requirements).
     *
     * @param array $context Current workflow context
     * @param array $config Config with 'memberId'
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

            return (bool)$member->warrantable;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Check if a branch already has a current or upcoming officer overlapping a hire window.
     *
     * @param array $context Current workflow context
     * @param array $config Config with officeId, branchId, newOfficerStartDate, newOfficerEndDate
     * @return bool
     */
    public function hasConflictingOfficer(array $context, array $config): bool
    {
        try {
            $officeId = $this->resolveValue($config['officeId'] ?? null, $context);
            $branchId = $this->resolveValue($config['branchId'] ?? null, $context);
            $startDateRaw = $this->resolveValue($config['newOfficerStartDate'] ?? null, $context);

            if (empty($officeId) || empty($branchId) || empty($startDateRaw)) {
                return false;
            }

            $startDate = $startDateRaw instanceof DateTime
                ? $startDateRaw
                : new DateTime((string)$startDateRaw);
            $endDate = $this->resolveEffectiveOfficerEndDate(
                (int)$officeId,
                $startDate,
                $this->resolveValue($config['newOfficerEndDate'] ?? null, $context),
            );

            $officerTable = TableRegistry::getTableLocator()->get('Officers.Officers');
            $officers = $officerTable->find()
                ->where([
                    'office_id' => (int)$officeId,
                    'branch_id' => (int)$branchId,
                    'status IN' => ['Current', 'Upcoming'],
                ])
                ->all();

            foreach ($officers as $officer) {
                $existingStart = $officer->start_on instanceof DateTime
                    ? $officer->start_on
                    : new DateTime((string)$officer->start_on);
                $existingEnd = $officer->expires_on instanceof DateTime
                    ? $officer->expires_on
                    : ($officer->expires_on !== null ? new DateTime((string)$officer->expires_on) : null);
                if ($this->windowsOverlap($existingStart, $existingEnd, $startDate, $endDate)) {
                    return true;
                }
            }

            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function resolveEffectiveOfficerEndDate(
        int $officeId,
        DateTime $startDate,
        mixed $endDateRaw,
    ): ?DateTime {
        if ($endDateRaw instanceof DateTime) {
            return $endDateRaw;
        }
        if ($endDateRaw !== null && $endDateRaw !== '') {
            return new DateTime((string)$endDateRaw);
        }

        $office = TableRegistry::getTableLocator()->get('Officers.Offices')->get($officeId);
        if ((int)$office->term_length > 0) {
            return $startDate->modify("+{$office->term_length} months");
        }

        return null;
    }

    private function windowsOverlap(
        DateTime $existingStart,
        ?DateTime $existingEnd,
        DateTime $newStart,
        ?DateTime $newEnd,
    ): bool {
        if ($existingEnd !== null && $existingEnd < $newStart) {
            return false;
        }
        if ($newEnd !== null && $newEnd < $existingStart) {
            return false;
        }

        return true;
    }
}
