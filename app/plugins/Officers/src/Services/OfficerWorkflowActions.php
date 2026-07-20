<?php

declare(strict_types=1);

namespace Officers\Services;

use App\KMP\StaticHelpers;
use App\KMP\TimezoneHelper;
use App\Mailer\QueuedMailerAwareTrait;
use App\Model\Entity\Warrant;
use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use App\Services\WarrantManager\WarrantManagerInterface;
use App\Services\WarrantManager\WarrantRequest;
use App\Services\WorkflowEngine\TriggerDispatcher;
use App\Services\WorkflowEngine\WorkflowContextAwareTrait;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Officers\Model\Entity\Officer;

/**
 * Workflow action implementations for officer lifecycle operations.
 *
 * Uses injected ActiveWindowManagerInterface and WarrantManagerInterface
 * rather than direct instantiation.
 */
class OfficerWorkflowActions
{
    use QueuedMailerAwareTrait;
    use WorkflowContextAwareTrait;

    private ActiveWindowManagerInterface $activeWindowManager;
    private WarrantManagerInterface $warrantManager;
    private ?OfficerManagerInterface $officerManager;
    private ?TriggerDispatcher $triggerDispatcher;

    public function __construct(
        ActiveWindowManagerInterface $activeWindowManager,
        WarrantManagerInterface $warrantManager,
        ?OfficerManagerInterface $officerManager = null,
        ?TriggerDispatcher $triggerDispatcher = null,
    ) {
        $this->activeWindowManager = $activeWindowManager;
        $this->warrantManager = $warrantManager;
        $this->officerManager = $officerManager;
        $this->triggerDispatcher = $triggerDispatcher;
    }

    /**
     * Create an officer record with reporting fields, ActiveWindow start, and role assignment.
     *
     * Unique-office conflict release is handled earlier in the workflow graph.
     *
     * @param array $context Current workflow context
     * @param array $config Action config
     * @return array Output with officerId
     */
    public function createOfficerRecord(array $context, array $config): array
    {
        $memberId = (int)$this->resolveValue($config['memberId'], $context);
        $officeId = (int)$this->resolveValue($config['officeId'], $context);
        $branchId = (int)$this->resolveValue($config['branchId'], $context);
        $approverId = (int)$this->resolveValue(
            $config['approverId'] ?? ($context['triggeredBy'] ?? 0),
            $context,
        );

        $startOnRaw = $this->resolveValue($config['startOn'] ?? null, $context);
        $startOn = $startOnRaw instanceof DateTime ? $startOnRaw : new DateTime($startOnRaw ?? 'now');

        $expiresOnRaw = $this->resolveValue($config['expiresOn'] ?? null, $context);
        $expiresOn = null;
        if ($expiresOnRaw !== null && $expiresOnRaw !== '') {
            $expiresOn = $expiresOnRaw instanceof DateTime ? $expiresOnRaw : new DateTime($expiresOnRaw);
        }

        $emailAddress = $this->resolveValue($config['emailAddress'] ?? '', $context) ?? '';
        $deputyDescription = $this->resolveValue($config['deputyDescription'] ?? null, $context);

        $officerTable = TableRegistry::getTableLocator()->get('Officers.Officers');
        $officeTable = TableRegistry::getTableLocator()->get('Officers.Offices');
        $membersTable = TableRegistry::getTableLocator()->get('Members');
        $office = $officeTable->get($officeId);
        $member = $membersTable->get($memberId);

        if ($office->requires_warrant && !$member->warrantable) {
            throw new \RuntimeException('Member is not warrantable');
        }

        if ($expiresOn === null && $office->term_length > 0) {
            $expiresOn = $startOn->modify("+{$office->term_length} months");
        }

        $status = Officer::UPCOMING_STATUS;
        if ($startOn->isToday() || $startOn->isPast()) {
            $status = Officer::CURRENT_STATUS;
        }
        if ($expiresOn !== null && $expiresOn->isPast()) {
            $status = Officer::EXPIRED_STATUS;
        }

        $officer = $officerTable->newEmptyEntity();
        $officer->member_id = $memberId;
        $officer->office_id = $officeId;
        $officer->branch_id = $branchId;
        $officer->status = $status;
        $officer->start_on = $startOn;
        $officer->expires_on = $expiresOn;
        $officer->approver_id = $approverId;
        $officer->approval_date = DateTime::now();
        $officer->email_address = $emailAddress;
        $officer->deputy_description = $deputyDescription;

        $reporting = $this->calculateReportingFields($office, $officer);
        $officer->reports_to_office_id = $reporting['reports_to_office_id'];
        $officer->reports_to_branch_id = $reporting['reports_to_branch_id'];
        $officer->deputy_to_office_id = $reporting['deputy_to_office_id'];
        $officer->deputy_to_branch_id = $reporting['deputy_to_branch_id'];

        if (!$officerTable->save($officer)) {
            throw new \RuntimeException('Failed to save officer');
        }

        $awResult = $this->activeWindowManager->start(
            'Officers.Officers',
            $officer->id,
            $approverId > 0 ? $approverId : $memberId,
            $startOn,
            $expiresOn,
            $office->term_length,
            $office->grants_role_id,
            false,
            $branchId,
        );
        if (!$awResult->success) {
            throw new \RuntimeException(
                'Failed to start officer active window: ' . ($awResult->reason ?? 'Unknown error'),
            );
        }

        return ['officerId' => $officer->id];
    }

    /**
     * Calculate reporting relationships for an officer based on office config.
     */
    private function calculateReportingFields(object $office, object $officer): array
    {
        $result = [
            'reports_to_office_id' => null,
            'reports_to_branch_id' => null,
            'deputy_to_office_id' => null,
            'deputy_to_branch_id' => null,
        ];

        if ($office->deputy_to_id != null) {
            $result['deputy_to_branch_id'] = $officer->branch_id;
            $result['deputy_to_office_id'] = $office->deputy_to_id;
            $result['reports_to_branch_id'] = $officer->branch_id;
            $result['reports_to_office_id'] = $office->deputy_to_id;
        } else {
            $result['reports_to_office_id'] = $office->reports_to_id;
            $branchTable = TableRegistry::getTableLocator()->get('Branches');
            $branch = $branchTable->get($officer->branch_id);

            if ($branch->parent_id != null) {
                $officesTable = TableRegistry::getTableLocator()->get('Officers.Offices');

                if (!$office->can_skip_report) {
                    $result['reports_to_branch_id'] = $officesTable->findCompatibleBranchForOffice(
                        $branch->parent_id,
                        $office->reports_to_id,
                    );
                } else {
                    $officerTable = TableRegistry::getTableLocator()->get('Officers.Officers');
                    $currentBranchId = $branch->parent_id;
                    $previousBranchId = $officer->branch_id;
                    $found = false;

                    while ($currentBranchId != null) {
                        $count = $officerTable->find('Current')
                            ->where(['branch_id' => $currentBranchId, 'office_id' => $office->reports_to_id])
                            ->count();
                        if ($count > 0) {
                            $result['reports_to_branch_id'] = $currentBranchId;
                            $found = true;
                            break;
                        }
                        $previousBranchId = $currentBranchId;
                        $currentBranch = $branchTable->get($currentBranchId);
                        $currentBranchId = $currentBranch->parent_id;
                    }

                    if (!$found) {
                        $result['reports_to_branch_id'] = $officesTable->findCompatibleBranchForOffice(
                            $previousBranchId,
                            $office->reports_to_id,
                        );
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Release an officer using the same lifecycle steps as the legacy manager path.
     *
     * This mirrors DefaultOfficerManager::release() without re-dispatching
     * Officers.Released from inside the workflow.
     *
     * @param array $context Current workflow context
     * @param array $config Action config with officerId, releasedById, reason, expiresOn, releaseStatus
     * @return array Output with released boolean
     */
    public function releaseOfficer(array $context, array $config): array
    {
        $officerId = (int)$this->resolveValue($config['officerId'], $context);
        $releasedById = (int)($this->resolveValue(
            $config['releasedById'] ?? ($context['triggeredBy'] ?? null),
            $context,
        ) ?? 0);
        $reason = (string)$this->resolveValue($config['reason'] ?? 'Released via workflow', $context);
        $releaseStatus = (string)$this->resolveValue(
            $config['releaseStatus'] ?? Officer::RELEASED_STATUS,
            $context,
        );

        $expiresOnRaw = $this->resolveValue($config['expiresOn'] ?? null, $context);
        $expiresOn = $expiresOnRaw instanceof DateTime
            ? $expiresOnRaw
            : new DateTime($expiresOnRaw ?? 'now');

        $officerTable = TableRegistry::getTableLocator()->get('Officers.Officers');
        $officer = $officerTable->get($officerId, contain: ['Offices']);

        $awResult = $this->activeWindowManager->stop(
            'Officers.Officers',
            $officerId,
            $releasedById,
            $releaseStatus,
            $reason,
            $expiresOn,
        );
        if (!$awResult->success) {
            throw new \RuntimeException(
                'Failed to release officer active window: ' . ($awResult->reason ?? 'Unknown error'),
            );
        }

        if ($officer->office->requires_warrant) {
            $wmResult = $this->warrantManager->cancelByEntity(
                'Officers.Officers',
                $officerId,
                $reason,
                $releasedById,
                $expiresOn,
            );
            if (!$wmResult->success) {
                throw new \RuntimeException(
                    'Failed to cancel officer warrant: ' . ($wmResult->reason ?? 'Unknown error'),
                );
            }
        }

        return ['released' => true, 'officerId' => $officerId];
    }

    /**
     * Request a warrant roster for a newly hired officer.
     * Creates the roster and dispatches Warrants.RosterCreated trigger,
     * which kicks off the warrant-roster approval workflow.
     *
     * @param array $context Current workflow context
     * @param array $config Action config with officerId
     * @return array Output with rosterId
     */
    public function requestWarrantRoster(array $context, array $config): array
    {
        $officerId = (int)$this->resolveValue($config['officerId'], $context);

        $officerTable = TableRegistry::getTableLocator()->get('Officers.Officers');
        $officer = $officerTable->get($officerId, contain: ['Offices']);

        $member = TableRegistry::getTableLocator()->get('Members')->get($officer->member_id);
        $branch = TableRegistry::getTableLocator()->get('Branches')->get($officer->branch_id);
        $office = $officer->office;

        $officeName = $office->name;
        if (!empty($officer->deputy_description)) {
            $officeName .= ' (' . $officer->deputy_description . ')';
        }

        $requestedBy = (int)($context['triggeredBy'] ?? 0);

        $warrantRequest = new WarrantRequest(
            "Hiring Warrant: {$branch->name} - {$officeName}",
            'Officers.Officers',
            $officer->id,
            $requestedBy,
            $officer->member_id,
            $officer->start_on,
            $officer->expires_on,
            $officer->granted_member_role_id,
        );

        $result = $this->warrantManager->request(
            "{$office->name} : {$member->sca_name}",
            '',
            [$warrantRequest],
            $requestedBy,
        );

        if (!$result->success) {
            throw new \RuntimeException(
                'Failed to request warrant roster: ' . ($result->reason ?? 'Unknown error'),
            );
        }

        return ['rosterId' => $result->data];
    }

    /**
     * Calculate reporting hierarchy fields for an officer.
     *
     * Standalone action wrapping the complex hierarchy traversal logic
     * so it can be invoked independently in a workflow step.
     *
     * @param array $context Current workflow context
     * @param array $config Action config with officeId, branchId, officerId
     * @return array Reporting field values
     */
    public function calculateReportingFieldsAction(array $context, array $config): array
    {
        try {
            $officeId = (int)$this->resolveValue($config['officeId'], $context);
            $branchId = (int)$this->resolveValue($config['branchId'], $context);

            $officeTable = TableRegistry::getTableLocator()->get('Officers.Offices');
            $office = $officeTable->get($officeId);

            // Build a minimal officer-like object for the calculation
            $officer = new \stdClass();
            $officer->branch_id = $branchId;
            $officer->deputy_description = $this->resolveValue($config['deputyDescription'] ?? null, $context);

            $result = $this->calculateReportingFields($office, $officer);

            return $result;
        } catch (\Throwable $e) {
            Log::error('Workflow CalculateReportingFields failed: ' . $e->getMessage());

            return [
                'reports_to_office_id' => null,
                'reports_to_branch_id' => null,
                'deputy_to_office_id' => null,
                'deputy_to_branch_id' => null,
            ];
        }
    }

    /**
     * Resolve existing officers when a one-per-branch office gets a new assignment.
     *
     * @param array $context Current workflow context
     * @param array $config Action config with officeId, branchId, newOfficerStartDate, newOfficerEndDate
     * @return array Lists of released and adjusted officer IDs
     */
    public function releaseConflictingOfficers(array $context, array $config): array
    {
        if ($this->triggerDispatcher === null) {
            throw new \RuntimeException('Release workflow dispatcher is not available');
        }

        $officeId = (int)$this->resolveValue($config['officeId'], $context);
        $branchId = (int)$this->resolveValue($config['branchId'], $context);
        $releasedById = (int)$this->resolveValue(
            $config['releasedById'] ?? ($context['triggeredBy'] ?? 0),
            $context,
        );

        $startDateRaw = $this->resolveValue($config['newOfficerStartDate'] ?? null, $context);
        $startDate = $startDateRaw instanceof DateTime
            ? $startDateRaw
            : new DateTime($startDateRaw ?? 'now');
        $endDate = $this->resolveEffectiveOfficerEndDate(
            $officeId,
            $startDate,
            $this->resolveValue($config['newOfficerEndDate'] ?? null, $context),
        );

        $officerTable = TableRegistry::getTableLocator()->get('Officers.Officers');
        $officeTable = TableRegistry::getTableLocator()->get('Officers.Offices');
        $office = $officeTable->get($officeId);

        $conflictingOfficers = $officerTable->find()
            ->contain(['Offices'])
            ->where([
                'office_id' => $officeId,
                'branch_id' => $branchId,
                'status IN' => [Officer::CURRENT_STATUS, Officer::UPCOMING_STATUS],
            ])
            ->orderBy(['Officers.start_on' => 'ASC', 'Officers.id' => 'ASC'])
            ->all();

        $releasedIds = [];
        $adjustedIds = [];
        foreach ($conflictingOfficers as $existing) {
            $resolution = $this->determineConflictResolution($existing, $startDate, $endDate);
            if ($resolution['type'] === 'none') {
                continue;
            }

            if ($resolution['type'] === 'full_release') {
                $this->dispatchReplacementRelease($existing, $releasedById, $startDate, $context);
                $releasedIds[] = $existing->id;
                continue;
            }

            $this->applyConflictDateAdjustment(
                $existing,
                $resolution['newStart'],
                $resolution['newEnd'],
                $releasedById,
                'Replaced by new officer',
                $office,
            );
            $adjustedIds[] = $existing->id;
        }

        return [
            'releasedOfficerIds' => $releasedIds,
            'adjustedOfficerIds' => $adjustedIds,
        ];
    }

    /**
     * Batch recalculate all officers when office configuration changes.
     *
     * Delegates to OfficerManagerInterface::recalculateOfficersForOffice().
     *
     * @param array $context Current workflow context
     * @param array $config Action config with officeId, updaterId
     * @return array Updated count
     */
    public function recalculateOfficersForOffice(array $context, array $config): array
    {
        try {
            $officeId = (int)$this->resolveValue($config['officeId'], $context);
            $updaterId = (int)$this->resolveValue(
                $config['updaterId'] ?? $context['triggeredBy'] ?? 0,
                $context,
            );

            if ($this->officerManager === null) {
                Log::error('Workflow RecalculateOfficersForOffice: OfficerManager not available');

                return ['updatedCount' => 0];
            }

            $result = $this->officerManager->recalculateOfficersForOffice($officeId, $updaterId);

            if (!$result->success) {
                Log::error('Workflow RecalculateOfficersForOffice: ' . $result->reason);

                return ['updatedCount' => 0];
            }

            return ['updatedCount' => $result->data['updated_count'] ?? 0];
        } catch (\Throwable $e) {
            Log::error('Workflow RecalculateOfficersForOffice failed: ' . $e->getMessage());

            return ['updatedCount' => 0];
        }
    }

    /**
     * Prepare all hire-notification email variables for use by Core.SendEmail.
     *
     * Loads officer, member, and branch records; formats dates; and resolves
     * the warrant-required notice text. Outputs vars for the
     * officer-hire-notification DB template into workflow context.
     *
     * @param array $context Current workflow context
     * @param array $config Action config with officerId
     * @return array Output with hire notification vars
     */
    public function prepareHireNotificationVars(array $context, array $config): array
    {
        try {
            $officerId = (int)$this->resolveValue($config['officerId'], $context);

            $officerTable = TableRegistry::getTableLocator()->get('Officers.Officers');
            $officer = $officerTable->get($officerId, contain: ['Offices']);

            $member = TableRegistry::getTableLocator()->get('Members')->get($officer->member_id);
            $branch = TableRegistry::getTableLocator()->get('Branches')->get($officer->branch_id);

            $requiresWarrantNotice = $officer->office->requires_warrant
                ? 'Please note that this office requires a warrant. '
                  . 'A request for that warrant has been forwarded to the Crown for approval.'
                : '';

            return [
                'success' => true,
                'data' => [
                    'to' => $member->email_address,
                    'memberScaName' => $member->sca_name,
                    'officeName' => $officer->office->name,
                    'branchName' => $branch->name,
                    'hireDate' => TimezoneHelper::formatDate($officer->start_on),
                    'endDate' => TimezoneHelper::formatDate($officer->expires_on),
                    'requiresWarrantNotice' => $requiresWarrantNotice,
                    'siteAdminSignature' => \App\KMP\StaticHelpers::getAppSetting(
                        'Email.SiteAdminSignature',
                        '',
                        null,
                        true,
                    ),
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('Workflow PrepareHireNotificationVars failed: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Prepare all release-notification email variables for use by Core.SendEmail.
     *
     * Loads officer, member, and branch records; formats the release date;
     * and resolves the reason. Outputs vars for the officer-release-notification
     * DB template into workflow context.
     *
     * @param array $context Current workflow context
     * @param array $config Action config with officerId, reason
     * @return array Output with release notification vars
     */
    public function prepareReleaseNotificationVars(array $context, array $config): array
    {
        try {
            $officerId = (int)$this->resolveValue($config['officerId'], $context);

            $officerTable = TableRegistry::getTableLocator()->get('Officers.Officers');
            $officer = $officerTable->get($officerId, contain: ['Offices']);

            $member = TableRegistry::getTableLocator()->get('Members')->get($officer->member_id);
            $branch = TableRegistry::getTableLocator()->get('Branches')->get($officer->branch_id);

            $reason = (string)$this->resolveValue($config['reason'] ?? 'Released via workflow', $context);
            $releaseDate = $officer->expires_on ?? DateTime::now();

            return [
                'success' => true,
                'data' => [
                    'to' => $member->email_address,
                    'memberScaName' => $member->sca_name,
                    'officeName' => $officer->office->name,
                    'branchName' => $branch->name,
                    'reason' => $reason,
                    'releaseDate' => TimezoneHelper::formatDate($releaseDate),
                    'siteAdminSignature' => \App\KMP\StaticHelpers::getAppSetting(
                        'Email.SiteAdminSignature',
                        '',
                        null,
                        true,
                    ),
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('Workflow PrepareReleaseNotificationVars failed: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Extract the first workflow dispatch failure from trigger results.
     *
     * @param array<int, mixed> $results Workflow dispatch results.
     * @param string $defaultMessage Fallback error message.
     * @return string|null
     */
    private function extractWorkflowDispatchFailure(array $results, string $defaultMessage): ?string
    {
        if ($results === []) {
            return $defaultMessage;
        }

        foreach ($results as $result) {
            if ($result instanceof \App\Services\ServiceResult) {
                if (!$result->success) {
                    return $result->reason ?? $defaultMessage;
                }

                $workflowResult = is_array($result->data ?? null)
                    ? ($result->data['workflowResult'] ?? null)
                    : null;
                if (
                    is_array($workflowResult)
                    && array_key_exists('success', $workflowResult)
                    && $workflowResult['success'] === false
                ) {
                    return (string)($workflowResult['error'] ?? $workflowResult['reason'] ?? $defaultMessage);
                }

                continue;
            }

            if (
                is_array($result)
                && array_key_exists('success', $result)
                && $result['success'] === false
            ) {
                return (string)($result['error'] ?? $result['reason'] ?? $defaultMessage);
            }
        }

        return null;
    }

    /**
     * @param \Officers\Model\Entity\Officer $existing
     * @param \Cake\I18n\DateTime $newStart
     * @param \Cake\I18n\DateTime|null $newEnd
     * @return array{type:string,newStart:\Cake\I18n\DateTime,newEnd:\Cake\I18n\DateTime|null}
     */
    private function determineConflictResolution(Officer $existing, DateTime $newStart, ?DateTime $newEnd): array
    {
        $existingStart = $existing->start_on instanceof DateTime
            ? $existing->start_on
            : new DateTime((string)$existing->start_on);
        $existingEnd = $existing->expires_on instanceof DateTime
            ? $existing->expires_on
            : ($existing->expires_on !== null ? new DateTime((string)$existing->expires_on) : null);

        if (!$this->windowsOverlap($existingStart, $existingEnd, $newStart, $newEnd)) {
            return [
                'type' => 'none',
                'newStart' => $existingStart,
                'newEnd' => $existingEnd,
            ];
        }

        if ($existing->status === Officer::CURRENT_STATUS) {
            if ($newStart <= $existingStart) {
                return [
                    'type' => 'full_release',
                    'newStart' => $existingStart,
                    'newEnd' => $existingEnd,
                ];
            }

            return [
                'type' => 'trim_end',
                'newStart' => $existingStart,
                'newEnd' => $newStart->subSeconds(1),
            ];
        }

        if ($newStart <= $existingStart) {
            if ($newEnd === null || $existingEnd === null || $newEnd >= $existingEnd) {
                return [
                    'type' => 'full_release',
                    'newStart' => $existingStart,
                    'newEnd' => $existingEnd,
                ];
            }

            return [
                'type' => 'push_start',
                'newStart' => $newEnd->addSeconds(1),
                'newEnd' => $existingEnd,
            ];
        }

        return [
            'type' => 'trim_end',
            'newStart' => $existingStart,
            'newEnd' => $newStart->subSeconds(1),
        ];
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

    private function dispatchReplacementRelease(
        Officer $existing,
        int $releasedById,
        DateTime $startDate,
        array $context,
    ): void {
        $dispatchContext = [
            'officerId' => $existing->id,
            'releasedById' => $releasedById,
            'reason' => 'Replaced by new officer',
            'expiresOn' => $startDate->toDateTimeString(),
            'releaseStatus' => Officer::REPLACED_STATUS,
            'officer_id' => $existing->id,
            'released_by' => $releasedById,
            'revoked_on' => $startDate->toDateTimeString(),
        ];
        if (isset($context['trigger']['kingdom_id'])) {
            $dispatchContext['kingdom_id'] = $context['trigger']['kingdom_id'];
        }

        $results = $this->triggerDispatcher->dispatch(
            'Officers.Released',
            $dispatchContext,
            $releasedById,
        );
        $workflowError = $this->extractWorkflowDispatchFailure(
            $results,
            'Officer release workflow failed during conflict replacement.',
        );
        if ($workflowError !== null) {
            throw new \RuntimeException($workflowError);
        }
    }

    private function applyConflictDateAdjustment(
        Officer $officer,
        DateTime $newStart,
        ?DateTime $newEnd,
        int $adjustedById,
        string $reason,
        object $office,
    ): void {
        $officerTable = TableRegistry::getTableLocator()->get('Officers.Officers');
        $previousStart = $officer->start_on instanceof DateTime
            ? $officer->start_on
            : new DateTime((string)$officer->start_on);
        $previousEnd = $officer->expires_on instanceof DateTime
            ? $officer->expires_on
            : ($officer->expires_on !== null ? new DateTime((string)$officer->expires_on) : null);

        $officer->start_on = $newStart;
        $officer->expires_on = $newEnd;
        $officer->status = $this->deriveActiveWindowStatus($newStart, $newEnd);
        $officer->revoker_id = $adjustedById;
        $officer->revoked_reason = $reason;
        $officerTable->saveOrFail($officer);

        $this->syncGrantedMemberRoleDates($officer, $newStart, $newEnd, $adjustedById, $reason);
        $this->syncOfficerWarrantDates($officer, $newStart, $newEnd, $adjustedById, $reason);
        $this->queueAdjustmentNotification(
            $officer,
            $office,
            $previousStart,
            $previousEnd,
            $newStart,
            $newEnd,
            $reason,
        );
    }

    private function syncGrantedMemberRoleDates(
        Officer $officer,
        DateTime $newStart,
        ?DateTime $newEnd,
        int $adjustedById,
        string $reason,
    ): void {
        if ($officer->granted_member_role_id === null) {
            return;
        }

        $memberRoleTable = TableRegistry::getTableLocator()->get('MemberRoles');
        $memberRole = $memberRoleTable->get($officer->granted_member_role_id);
        $memberRole->start_on = $newStart;
        $memberRole->expires_on = $newEnd;
        $memberRole->status = $this->deriveActiveWindowStatus($newStart, $newEnd);
        $memberRole->revoker_id = $adjustedById;
        $memberRole->revoked_reason = $reason;
        $memberRoleTable->saveOrFail($memberRole);
    }

    private function syncOfficerWarrantDates(
        Officer $officer,
        DateTime $newStart,
        ?DateTime $newEnd,
        int $adjustedById,
        string $reason,
    ): void {
        $warrantTable = TableRegistry::getTableLocator()->get('Warrants');
        $warrants = $warrantTable->find()
            ->where([
                'entity_type' => 'Officers.Officers',
                'entity_id' => $officer->id,
                'status IN' => [Warrant::CURRENT_STATUS, Warrant::PENDING_STATUS],
            ])
            ->all();

        foreach ($warrants as $warrant) {
            $warrant->start_on = $newStart;
            $warrant->expires_on = $newEnd;
            $warrant->status = $this->deriveWarrantStatus((string)$warrant->status, $newStart, $newEnd);
            $warrant->revoker_id = $adjustedById;
            $warrant->revoked_reason = $reason;
            $warrantTable->saveOrFail($warrant);
        }
    }

    private function deriveActiveWindowStatus(DateTime $startOn, ?DateTime $expiresOn): string
    {
        $now = DateTime::now();
        if ($expiresOn !== null && $expiresOn < $now) {
            return Officer::EXPIRED_STATUS;
        }
        if ($startOn > $now) {
            return Officer::UPCOMING_STATUS;
        }

        return Officer::CURRENT_STATUS;
    }

    private function deriveWarrantStatus(string $currentStatus, DateTime $startOn, ?DateTime $expiresOn): string
    {
        $now = DateTime::now();
        if ($expiresOn !== null && $expiresOn < $now) {
            return Warrant::EXPIRED_STATUS;
        }
        if ($currentStatus === Warrant::PENDING_STATUS || $startOn > $now) {
            return Warrant::PENDING_STATUS;
        }

        return Warrant::CURRENT_STATUS;
    }

    private function queueAdjustmentNotification(
        Officer $officer,
        object $office,
        DateTime $previousStart,
        ?DateTime $previousEnd,
        DateTime $newStart,
        ?DateTime $newEnd,
        string $reason,
    ): void {
        $member = TableRegistry::getTableLocator()->get('Members')->get($officer->member_id);
        $branch = TableRegistry::getTableLocator()->get('Branches')->get($officer->branch_id);

        $this->queueMail('KMP', 'sendFromTemplate', $member->email_address, [
            '_templateId' => 'officer-assignment-adjusted-notification',
            'memberScaName' => $member->sca_name,
            'officeName' => $office->name,
            'branchName' => $branch->name,
            'previousStartDate' => $this->formatOptionalDate($previousStart),
            'previousEndDate' => $this->formatOptionalDate($previousEnd),
            'newStartDate' => $this->formatOptionalDate($newStart),
            'newEndDate' => $this->formatOptionalDate($newEnd),
            'reason' => $reason,
            'siteAdminSignature' => StaticHelpers::getAppSetting('Email.SiteAdminSignature', '', null, true),
        ]);
    }

    private function formatOptionalDate(?DateTime $date): string
    {
        if ($date === null) {
            return 'No Expiration Date';
        }

        return TimezoneHelper::formatDate($date);
    }
}
