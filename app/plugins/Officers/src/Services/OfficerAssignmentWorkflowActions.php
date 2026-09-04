<?php
declare(strict_types=1);

namespace Officers\Services;

use App\KMP\StaticHelpers;
use App\KMP\TimezoneHelper;
use App\Model\Entity\Warrant;
use App\Services\WarrantManager\WarrantManagerInterface;
use App\Services\WarrantManager\WarrantRequest;
use App\Services\WorkflowEngine\WorkflowContextAwareTrait;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Officers\Model\Entity\Officer;
use RuntimeException;
use Throwable;

/**
 * Ephemeral workflow actions for editing an existing officer assignment.
 *
 * The assignment, its granted role, the required audit note, and any warrant
 * shortening or retroactive termination are deliberately handled in one
 * database transaction. Warrant-extension requests and notifications remain
 * separate workflow nodes so installations can customize those policies.
 */
class OfficerAssignmentWorkflowActions
{
    use WorkflowContextAwareTrait;

    private const ENTITY_TYPE = 'Officers.Officers';

    private const TERM_NOTE_REQUIRED_ERROR = 'A note is required when changing an officer term.';

    private const TERMINAL_ASSIGNMENT_ERROR = 'Only current or upcoming officer assignments can be updated.';

    private const UPDATE_FAILED_ERROR = 'The officer assignment could not be updated.';

    private const PENDING_WITHDRAWAL_REASON =
        'Officer term shortened; the issued warrant now covers the complete revised term.';

    /**
     * @param \App\Services\WarrantManager\WarrantManagerInterface $warrantManager Warrant lifecycle service.
     */
    public function __construct(private WarrantManagerInterface $warrantManager)
    {
    }

    /**
     * Update an officer assignment and its security-sensitive dependants.
     *
     * @param array<string, mixed> $context Workflow context.
     * @param array<string, mixed> $config Resolved action configuration.
     * @return array<string, mixed>
     */
    public function updateOfficerAssignment(array $context, array $config): array
    {
        $officerId = (int)$this->resolveValue($config['officerId'] ?? 0, $context);
        $actorId = $this->resolveActorId($context, $config);
        if ($officerId <= 0 || $actorId <= 0) {
            throw new RuntimeException('A valid officer and updating member are required.');
        }

        $startOn = $this->parseDate(
            $this->resolveValue($config['startOn'] ?? null, $context),
            'start date',
            false,
        );
        if ($startOn === null) {
            throw new RuntimeException('The officer start date is required.');
        }
        $startOn = $startOn->startOfDay();
        $expiresOn = $this->parseDate(
            $this->resolveValue($config['expiresOn'] ?? null, $context),
            'end date',
            true,
        );
        $expiresOn = $expiresOn?->endOfDay();
        if ($expiresOn !== null && $startOn > $expiresOn) {
            throw new RuntimeException('The officer term end date cannot be before its start date.');
        }

        $emailAddress = trim((string)$this->resolveValue($config['emailAddress'] ?? '', $context));
        if ($emailAddress !== '' && filter_var($emailAddress, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Enter a valid office email address.');
        }
        if (mb_strlen($emailAddress) > 255) {
            throw new RuntimeException('The office email address cannot exceed 255 characters.');
        }

        $deputyDescription = trim((string)$this->resolveValue(
            $config['deputyDescription'] ?? '',
            $context,
        ));
        if (mb_strlen($deputyDescription) > 255) {
            throw new RuntimeException('The deputy description cannot exceed 255 characters.');
        }
        $termNote = trim((string)$this->resolveValue($config['termNote'] ?? '', $context));

        $officers = TableRegistry::getTableLocator()->get('Officers.Officers');
        $connection = $officers->getConnection();
        $connection->enableSavePoints();

        try {
            /** @var array<string, mixed> $result */
            $result = $connection->transactional(function () use (
                $officers,
                $officerId,
                $actorId,
                $startOn,
                $expiresOn,
                $emailAddress,
                $deputyDescription,
                $termNote,
            ): array {
                $officer = $this->getOfficerForUpdate($officerId);
                $recordedAt = DateTime::now();
                if (!in_array($officer->status, [Officer::CURRENT_STATUS, Officer::UPCOMING_STATUS], true)) {
                    throw new RuntimeException(self::TERMINAL_ASSIGNMENT_ERROR);
                }
                $previousStart = $this->asDateTime($officer->start_on);
                $previousEnd = $this->asDateTime($officer->expires_on);

                $startChanged = $this->dateKey($previousStart) !== $this->dateKey($startOn);
                $endChanged = $this->dateKey($previousEnd) !== $this->dateKey($expiresOn);
                $termChanged = $startChanged || $endChanged;
                if ($termChanged && $termNote === '') {
                    throw new RuntimeException(self::TERM_NOTE_REQUIRED_ERROR);
                }

                $emailChanged = (string)($officer->email_address ?? '') !== $emailAddress;
                $deputyChanged = (string)($officer->deputy_description ?? '') !== $deputyDescription;
                $effectiveStart = $startChanged || $previousStart === null ? $startOn : $previousStart;
                $effectiveEnd = $endChanged ? $expiresOn : $previousEnd;
                $pastEnd = $effectiveEnd !== null
                && $effectiveEnd->toDateString() < $recordedAt->toDateString();
                $pastEndCorrection = $endChanged && $pastEnd;

                $officer->start_on = $effectiveStart;
                $officer->expires_on = $effectiveEnd;
                $officer->email_address = $emailAddress;
                $officer->deputy_description = $deputyDescription === '' ? null : $deputyDescription;
                $officer->status = $this->deriveOfficerStatus($effectiveStart, $effectiveEnd, $recordedAt);
                $officers->saveOrFail($officer);

                if ($termChanged) {
                    $roleEnd = $pastEndCorrection ? $recordedAt->subSeconds(1) : $effectiveEnd;
                    $this->syncGrantedMemberRole(
                        $officer,
                        $effectiveStart,
                        $roleEnd,
                        $startChanged,
                        $endChanged,
                    );
                }

                $warrantMessage = '';
                $shortenedWarrantCount = 0;
                $terminatedWarrantCount = 0;
                $withdrawnPendingWarrantCount = 0;
                if ($pastEndCorrection) {
                    $terminatedWarrantCount = $this->terminateCurrentAndPendingWarrants(
                        $officer,
                        $actorId,
                        $recordedAt,
                    );
                    $warrantMessage = sprintf(
                        'The linked role permission and any active warrant remained effective until this change '
                        . 'was recorded on %s and were terminated effective that date.',
                        TimezoneHelper::formatDate($recordedAt),
                    );
                } elseif ($endChanged && $effectiveEnd !== null) {
                    $shortenedWarrantCount = $this->shortenCurrentWarrants(
                        $officer,
                        $actorId,
                        $effectiveEnd,
                        $recordedAt,
                    );
                    if ($shortenedWarrantCount > 0) {
                        $withdrawResult = $this->warrantManager->withdrawPendingRequests(
                            self::ENTITY_TYPE,
                            (int)$officer->id,
                            (int)$officer->member_id,
                            $officer->granted_member_role_id === null
                                ? null
                                : (int)$officer->granted_member_role_id,
                            $actorId,
                            self::PENDING_WITHDRAWAL_REASON,
                        );
                        if (!$withdrawResult->success) {
                            throw new RuntimeException(
                                'Failed to withdraw obsolete pending warrants: '
                                . ($withdrawResult->reason ?? 'Unknown error'),
                            );
                        }
                        $withdrawnPendingWarrantCount = (int)($withdrawResult->data ?? 0);
                        $warrantMessage = sprintf(
                            '%s shortened to end with the updated officer term on %s.',
                            $shortenedWarrantCount === 1
                                ? 'The current warrant was'
                                : sprintf('%d current warrants were', $shortenedWarrantCount),
                            TimezoneHelper::formatDate($effectiveEnd),
                        );
                        if ($withdrawnPendingWarrantCount > 0) {
                            $warrantMessage .= sprintf(
                                ' %d obsolete pending warrant request(s) were withdrawn.',
                                $withdrawnPendingWarrantCount,
                            );
                        }
                    }
                }

                if ($termChanged) {
                    $this->createTermNote(
                        $officer,
                        $actorId,
                        $termNote,
                        $previousStart,
                        $previousEnd,
                        $effectiveStart,
                        $effectiveEnd,
                        $recordedAt,
                        $pastEndCorrection,
                        $shortenedWarrantCount,
                        $terminatedWarrantCount,
                        $withdrawnPendingWarrantCount,
                    );
                }

                $changeSummary = $this->buildChangeSummary(
                    $startChanged,
                    $endChanged,
                    $emailChanged,
                    $deputyChanged,
                    $previousStart,
                    $previousEnd,
                    $effectiveStart,
                    $effectiveEnd,
                );

                return [
                    'officerId' => (int)$officer->id,
                    'memberId' => (int)$officer->member_id,
                    'officeId' => (int)$officer->office_id,
                    'branchId' => (int)$officer->branch_id,
                    'changed' => $termChanged || $emailChanged || $deputyChanged,
                    'termChanged' => $termChanged,
                    'startOn' => $effectiveStart->toDateString(),
                    'expiresOn' => $effectiveEnd?->toDateString(),
                    'changeSummary' => $changeSummary,
                    'termChangeNote' => $termChanged ? $termNote : '',
                    'warrantMessage' => $warrantMessage,
                    'withdrawnPendingWarrantCount' => $withdrawnPendingWarrantCount,
                ];
            });
        } catch (Throwable $e) {
            if (
                $e instanceof RuntimeException
                && in_array(
                    $e->getMessage(),
                    [self::TERM_NOTE_REQUIRED_ERROR, self::TERMINAL_ASSIGNMENT_ERROR],
                    true,
                )
            ) {
                throw $e;
            }

            Log::error('Officer assignment update workflow action failed: ' . $e->getMessage());
            throw new RuntimeException(self::UPDATE_FAILED_ERROR);
        }

        return $result;
    }

    /**
     * Request a covering warrant without cancelling the officer's current one.
     *
     * @param array<string, mixed> $context Workflow context.
     * @param array<string, mixed> $config Resolved action configuration.
     * @return array<string, mixed>
     */
    public function requestWarrantExtension(array $context, array $config): array
    {
        $officerId = (int)$this->resolveValue($config['officerId'] ?? 0, $context);
        $actorId = $this->resolveActorId($context, $config);
        $existingMessage = trim((string)$this->resolveValue(
            $config['existingWarrantMessage'] ?? '',
            $context,
        ));
        if ($officerId <= 0 || $actorId <= 0) {
            throw new RuntimeException('A valid officer and requesting member are required.');
        }

        $officers = TableRegistry::getTableLocator()->get('Officers.Officers');
        $connection = $officers->getConnection();
        $connection->enableSavePoints();

        try {
            /** @var array<string, mixed> $result */
            $result = $connection->transactional(
                fn(): array => $this->requestWarrantExtensionUnderLock(
                    $officerId,
                    $actorId,
                    $existingMessage,
                ),
            );
        } catch (Throwable $e) {
            Log::error('Officer warrant extension workflow action failed: ' . $e->getMessage());

            return $this->warrantExtensionFailureResult();
        }

        return $result;
    }

    /**
     * Check and create an extension while holding the officer assignment lock.
     *
     * SQLite serializes writes at the database level and does not support FOR UPDATE;
     * MySQL and PostgreSQL use the explicit row lock below.
     *
     * @param int $officerId Officer assignment id.
     * @param int $actorId Trusted requesting member id.
     * @param string $existingMessage Warrant result from the update action.
     * @return array<string, mixed>
     */
    private function requestWarrantExtensionUnderLock(
        int $officerId,
        int $actorId,
        string $existingMessage,
    ): array {
        $officer = $this->getOfficerForUpdate($officerId);
        $newEnd = $this->asDateTime($officer->expires_on);
        $today = DateTime::now();

        if ($existingMessage !== '' || $newEnd === null || $newEnd->toDateString() < $today->toDateString()) {
            return [
                'requested' => false,
                'rosterId' => null,
                'warrantMessage' => $existingMessage,
                'warning' => '',
            ];
        }
        if (!$officer->office->requires_warrant) {
            return ['requested' => false, 'rosterId' => null, 'warrantMessage' => '', 'warning' => ''];
        }

        $todayStart = $today->startOfDay();
        $todayEnd = $today->endOfDay();
        $newEndDate = $newEnd->startOfDay();
        $warrants = TableRegistry::getTableLocator()->get('Warrants');
        $currentWarrant = $warrants->find()
            ->where([
                'entity_type' => self::ENTITY_TYPE,
                'entity_id' => $officerId,
                'status' => Warrant::CURRENT_STATUS,
                'start_on <=' => $todayEnd,
                'OR' => [
                    'expires_on IS' => null,
                    'expires_on >=' => $todayStart,
                ],
            ])
            ->orderByDesc('expires_on')
            ->first();
        if ($currentWarrant === null) {
            return [
                'requested' => false,
                'rosterId' => null,
                'warrantMessage' => 'No current warrant was available to extend.',
                'warning' => '',
            ];
        }

        $currentEnd = $this->asDateTime($currentWarrant->expires_on);
        $currentEndDate = $currentEnd?->startOfDay();
        if ($currentEndDate === null || $currentEndDate >= $newEndDate) {
            return [
                'requested' => false,
                'rosterId' => null,
                'warrantMessage' => 'The current warrant already covers the updated term.',
                'warning' => '',
            ];
        }
        $extensionStart = $currentEndDate->addDays(1)->startOfDay();

        $period = $this->warrantManager->getWarrantPeriod($extensionStart, null);
        $periodEnd = $period === null ? null : $this->asDateTime($period->end_date);
        if ($periodEnd === null || $periodEnd->startOfDay() < $extensionStart) {
            return [
                'requested' => false,
                'rosterId' => null,
                'warrantMessage' => 'No warrant extension was requested because no warrant period covers '
                    . 'the day after the current warrant ends.',
                'warning' => '',
            ];
        }

        $periodEndDate = $periodEnd->startOfDay();
        $coverageEnd = $periodEndDate < $newEndDate ? $periodEndDate : $newEndDate;
        $partialCoverageWarning = $coverageEnd < $newEndDate
            ? sprintf(
                'The available warrant period ends on %s before the updated officer term ends on %s. '
                . 'Additional warrant coverage will still be required.',
                TimezoneHelper::formatDate($coverageEnd),
                TimezoneHelper::formatDate($newEndDate),
            )
            : '';
        $member = TableRegistry::getTableLocator()->get('Members')->get($officer->member_id);
        $branch = TableRegistry::getTableLocator()->get('Branches')->get($officer->branch_id);
        $officeName = (string)$officer->office->name;
        if (!empty($officer->deputy_description)) {
            $officeName .= ' (' . $officer->deputy_description . ')';
        }

        $request = new WarrantRequest(
            "Warrant Extension: {$branch->name} - {$officeName}",
            self::ENTITY_TYPE,
            (int)$officer->id,
            $actorId,
            (int)$officer->member_id,
            $extensionStart,
            $coverageEnd,
            $officer->granted_member_role_id === null ? null : (int)$officer->granted_member_role_id,
        );
        $requestResult = $this->warrantManager->request(
            "{$officer->office->name} : {$member->sca_name} (Extension)",
            'Extends the existing officer warrant without cancelling the current warrant.',
            [$request],
            $actorId,
        );
        if (!$requestResult->success) {
            Log::error(
                'Officer warrant extension request failed: '
                . ($requestResult->reason ?? 'Unknown error'),
            );

            return $this->warrantExtensionFailureResult();
        }

        $requestReused = $requestResult->reason === WarrantManagerInterface::REQUEST_REUSED_REASON;
        if ($requestReused) {
            $warrantMessage = $partialCoverageWarning === ''
                ? 'A pending warrant request already covers the updated term.'
                : sprintf(
                    'A pending warrant request already covers the available extension period through %s. '
                    . 'The officer term continues through %s.',
                    TimezoneHelper::formatDate($coverageEnd),
                    TimezoneHelper::formatDate($newEndDate),
                );

            return [
                'requested' => false,
                'rosterId' => $requestResult->data,
                'warrantMessage' => $warrantMessage,
                'warning' => $partialCoverageWarning,
            ];
        }

        $warrantMessage = sprintf(
            'A warrant extension was requested through %s without cancelling the current warrant.',
            TimezoneHelper::formatDate($coverageEnd),
        );
        if ($partialCoverageWarning !== '') {
            $warrantMessage .= sprintf(
                ' The officer term continues through %s, so additional warrant coverage will still be required.',
                TimezoneHelper::formatDate($newEndDate),
            );
        }

        return [
            'requested' => true,
            'rosterId' => $requestResult->data,
            'warrantMessage' => $warrantMessage,
            'warning' => $partialCoverageWarning,
        ];
    }

    /**
     * Prepare officer-assignment update notification variables.
     *
     * @param array<string, mixed> $context Workflow context.
     * @param array<string, mixed> $config Resolved action configuration.
     * @return array<string, mixed>
     */
    public function prepareAssignmentUpdateNotificationVars(array $context, array $config): array
    {
        try {
            $officerId = (int)$this->resolveValue($config['officerId'] ?? 0, $context);
            $changeSummary = trim((string)$this->resolveValue($config['changeSummary'] ?? '', $context));
            $termChangeNote = trim((string)$this->resolveValue($config['termChangeNote'] ?? '', $context));
            $warrantMessage = trim((string)$this->resolveValue($config['warrantMessage'] ?? '', $context));

            $officer = TableRegistry::getTableLocator()->get('Officers.Officers')->get(
                $officerId,
                contain: ['Offices'],
            );
            $member = TableRegistry::getTableLocator()->get('Members')->get($officer->member_id);
            $branch = TableRegistry::getTableLocator()->get('Branches')->get($officer->branch_id);
            if (trim((string)$member->email_address) === '') {
                return [
                    'success' => false,
                    'error' => 'The officer does not have a member-account email address.',
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'to' => $member->email_address,
                    'memberScaName' => $member->sca_name,
                    'officeName' => $officer->office->name,
                    'branchName' => $branch->name,
                    'startDate' => TimezoneHelper::formatDate($officer->start_on),
                    'endDate' => $officer->expires_on === null
                        ? 'No end date'
                        : TimezoneHelper::formatDate($officer->expires_on),
                    'changeSummary' => $changeSummary,
                    'termChangeNote' => $termChangeNote,
                    'warrantMessage' => $warrantMessage,
                    'siteAdminSignature' => StaticHelpers::getAppSetting(
                        'Email.SiteAdminSignature',
                        '',
                        null,
                        true,
                    ),
                ],
            ];
        } catch (Throwable $e) {
            Log::error('Preparing officer assignment notification failed: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => 'The officer assignment notification could not be prepared.',
            ];
        }
    }

    /**
     * Synchronize the dates on the role granted by an officer assignment.
     *
     * @param \Officers\Model\Entity\Officer $officer Officer assignment.
     * @param \Cake\I18n\DateTime $startOn Role start date.
     * @param \Cake\I18n\DateTime|null $expiresOn Role end date.
     * @param bool $syncStart Whether the start date changed.
     * @param bool $syncEnd Whether the end date changed.
     * @return void
     */
    private function syncGrantedMemberRole(
        Officer $officer,
        DateTime $startOn,
        ?DateTime $expiresOn,
        bool $syncStart,
        bool $syncEnd,
    ): void {
        if ($officer->granted_member_role_id === null) {
            return;
        }

        $memberRoles = TableRegistry::getTableLocator()->get('MemberRoles');
        $memberRole = $memberRoles->get($officer->granted_member_role_id);
        if ($syncStart) {
            $memberRole->start_on = $startOn;
        }
        if ($syncEnd) {
            $memberRole->expires_on = $expiresOn;
        }
        $memberRoles->saveOrFail($memberRole);
    }

    /**
     * Load and lock an assignment before evaluating mutable source state.
     *
     * SQLite serializes writes and does not support FOR UPDATE. MySQL and
     * PostgreSQL use an explicit row lock to prevent concurrent lost updates.
     *
     * @param int $officerId Officer assignment id.
     * @return \Officers\Model\Entity\Officer
     */
    private function getOfficerForUpdate(int $officerId): Officer
    {
        $officers = TableRegistry::getTableLocator()->get('Officers.Officers');
        $query = $officers->find()
            ->contain(['Offices'])
            ->where([$officers->aliasField('id') => $officerId]);
        $driverClass = get_class($officers->getConnection()->getDriver());
        $lastSeparator = strrpos($driverClass, '\\');
        $driverName = $lastSeparator === false ? $driverClass : substr($driverClass, $lastSeparator + 1);
        if (in_array($driverName, ['Mysql', 'Postgres'], true)) {
            $query->epilog('FOR UPDATE');
        }

        /** @var \Officers\Model\Entity\Officer */
        return $query->firstOrFail();
    }

    /**
     * Terminate exact current and pending warrant records for a past-end correction.
     *
     * @param \Officers\Model\Entity\Officer $officer Officer assignment.
     * @param int $actorId Member recording the change.
     * @param \Cake\I18n\DateTime $recordedAt Change recording time.
     * @return int Number of terminated warrant records.
     */
    private function terminateCurrentAndPendingWarrants(
        Officer $officer,
        int $actorId,
        DateTime $recordedAt,
    ): int {
        $warrants = TableRegistry::getTableLocator()->get('Warrants')->find()
            ->where([
                'entity_type' => self::ENTITY_TYPE,
                'entity_id' => $officer->id,
                'status IN' => [Warrant::CURRENT_STATUS, Warrant::PENDING_STATUS],
            ])
            ->orderByAsc('id')
            ->all();

        $count = 0;
        $effectiveAt = $recordedAt->subSeconds(1);
        foreach ($warrants as $warrant) {
            $result = $this->warrantManager->cancel(
                (int)$warrant->id,
                'Officer term corrected to a past end date.',
                $actorId,
                $effectiveAt,
            );
            if (!$result->success) {
                throw new RuntimeException(
                    'Failed to terminate the officer warrant: ' . ($result->reason ?? 'Unknown error'),
                );
            }
            $count++;
        }

        return $count;
    }

    /**
     * Shorten active current warrants that outlast a revised officer term.
     *
     * Already-issued warrants may be shortened, but this update path never
     * lengthens one. A longer term is handled by the separate extension-request
     * workflow action.
     *
     * @param \Officers\Model\Entity\Officer $officer Officer assignment.
     * @param int $actorId Member recording the change.
     * @param \Cake\I18n\DateTime $expiresOn Revised officer term end.
     * @param \Cake\I18n\DateTime $recordedAt Change recording time.
     * @return int Number of shortened current warrant records.
     */
    private function shortenCurrentWarrants(
        Officer $officer,
        int $actorId,
        DateTime $expiresOn,
        DateTime $recordedAt,
    ): int {
        $warrants = TableRegistry::getTableLocator()->get('Warrants');
        $query = $warrants->find()
            ->where([
                'entity_type' => self::ENTITY_TYPE,
                'entity_id' => $officer->id,
                'status' => Warrant::CURRENT_STATUS,
                'AND' => [
                    ['OR' => [
                        'start_on IS' => null,
                        'start_on <=' => $recordedAt->endOfDay(),
                    ]],
                    ['OR' => [
                        'expires_on IS' => null,
                        'expires_on >=' => $recordedAt->startOfDay(),
                    ]],
                ],
            ])
            ->orderByAsc('id');
        $driverClass = get_class($warrants->getConnection()->getDriver());
        $lastSeparator = strrpos($driverClass, '\\');
        $driverName = $lastSeparator === false ? $driverClass : substr($driverClass, $lastSeparator + 1);
        if (in_array($driverName, ['Mysql', 'Postgres'], true)) {
            $query->epilog('FOR UPDATE');
        }

        $count = 0;
        foreach ($query->all() as $warrant) {
            $currentEnd = $this->asDateTime($warrant->expires_on);
            if ($currentEnd !== null && $currentEnd <= $expiresOn) {
                continue;
            }

            $result = $this->warrantManager->cancel(
                (int)$warrant->id,
                'Officer term shortened.',
                $actorId,
                $expiresOn,
            );
            if (!$result->success) {
                throw new RuntimeException(
                    'Failed to shorten the officer warrant: ' . ($result->reason ?? 'Unknown error'),
                );
            }
            $count++;
        }

        return $count;
    }

    /**
     * Persist the officer term audit note.
     *
     * @param \Officers\Model\Entity\Officer $officer Officer assignment.
     * @param int $actorId Note author.
     * @param string $termNote User-supplied reason.
     * @param \Cake\I18n\DateTime|null $previousStart Previous start date.
     * @param \Cake\I18n\DateTime|null $previousEnd Previous end date.
     * @param \Cake\I18n\DateTime $newStart New start date.
     * @param \Cake\I18n\DateTime|null $newEnd New end date.
     * @param \Cake\I18n\DateTime $recordedAt Change recording time.
     * @param bool $pastEnd Whether the new end is in the past.
     * @param int $shortenedWarrantCount Number of shortened current warrants.
     * @param int $terminatedWarrantCount Number of terminated warrants.
     * @param int $withdrawnPendingWarrantCount Number of obsolete pending warrants withdrawn.
     * @return void
     */
    private function createTermNote(
        Officer $officer,
        int $actorId,
        string $termNote,
        ?DateTime $previousStart,
        ?DateTime $previousEnd,
        DateTime $newStart,
        ?DateTime $newEnd,
        DateTime $recordedAt,
        bool $pastEnd,
        int $shortenedWarrantCount,
        int $terminatedWarrantCount,
        int $withdrawnPendingWarrantCount,
    ): void {
        $body = sprintf(
            "Term changed from %s through %s to %s through %s.\n\nReason: %s",
            $this->displayDate($previousStart),
            $this->displayDate($previousEnd),
            $this->displayDate($newStart),
            $this->displayDate($newEnd),
            $termNote === '' ? 'System-recorded past-term reconciliation.' : $termNote,
        );
        if ($pastEnd) {
            $body .= sprintf(
                "\n\nThe linked role permission and any active warrant were not backdated. "
                . 'They remained effective until this change was recorded on %s and were terminated '
                . 'effective that date. %d current or pending warrant record(s) were terminated.',
                TimezoneHelper::formatDateTime($recordedAt),
                $terminatedWarrantCount,
            );
        } elseif ($shortenedWarrantCount > 0 && $newEnd !== null) {
            $body .= sprintf(
                "\n\n%s shortened to end with the revised officer term on %s. "
                . 'No issued warrant was lengthened by this edit.',
                $shortenedWarrantCount === 1
                    ? 'The current warrant was'
                    : sprintf('%d current warrants were', $shortenedWarrantCount),
                TimezoneHelper::formatDate($newEnd),
            );
            if ($withdrawnPendingWarrantCount > 0) {
                $body .= sprintf(
                    ' %d obsolete pending warrant request(s) were withdrawn.',
                    $withdrawnPendingWarrantCount,
                );
            }
        }

        $notes = TableRegistry::getTableLocator()->get('Notes');
        $note = $notes->newEmptyEntity();
        $note->author_id = $actorId;
        $note->entity_type = self::ENTITY_TYPE;
        $note->entity_id = $officer->id;
        $note->subject = Officer::TERM_UPDATE_NOTE_SUBJECT;
        $note->body = $body;
        $note->private = false;
        $notes->saveOrFail($note);
    }

    /**
     * Build plain-text change details for the member notification.
     *
     * @param bool $startChanged Whether the start date changed.
     * @param bool $endChanged Whether the end date changed.
     * @param bool $emailChanged Whether the office email changed.
     * @param bool $deputyChanged Whether the deputy description changed.
     * @param \Cake\I18n\DateTime|null $previousStart Previous start date.
     * @param \Cake\I18n\DateTime|null $previousEnd Previous end date.
     * @param \Cake\I18n\DateTime $newStart New start date.
     * @param \Cake\I18n\DateTime|null $newEnd New end date.
     * @return string
     */
    private function buildChangeSummary(
        bool $startChanged,
        bool $endChanged,
        bool $emailChanged,
        bool $deputyChanged,
        ?DateTime $previousStart,
        ?DateTime $previousEnd,
        DateTime $newStart,
        ?DateTime $newEnd,
    ): string {
        $changes = [];
        if ($startChanged) {
            $changes[] = sprintf(
                'Start date changed from %s to %s.',
                $this->displayDate($previousStart),
                $this->displayDate($newStart),
            );
        }
        if ($endChanged) {
            $changes[] = sprintf(
                'End date changed from %s to %s.',
                $this->displayDate($previousEnd),
                $this->displayDate($newEnd),
            );
        }
        if ($emailChanged) {
            $changes[] = 'The office contact email was updated.';
        }
        if ($deputyChanged) {
            $changes[] = 'The deputy description was updated.';
        }

        return $changes === [] ? 'No assignment fields changed.' : implode(' ', $changes);
    }

    /**
     * Derive the assignment status using normalized term boundaries.
     *
     * @param \Cake\I18n\DateTime $startOn Term start.
     * @param \Cake\I18n\DateTime|null $expiresOn Term end.
     * @param \Cake\I18n\DateTime $now Recording time.
     * @return string
     */
    private function deriveOfficerStatus(DateTime $startOn, ?DateTime $expiresOn, DateTime $now): string
    {
        if ($expiresOn !== null && $expiresOn <= $now) {
            return Officer::EXPIRED_STATUS;
        }
        if ($startOn > $now) {
            return Officer::UPCOMING_STATUS;
        }

        return Officer::CURRENT_STATUS;
    }

    /**
     * Parse a submitted ISO date without accepting normalized invalid dates.
     *
     * @param mixed $value Submitted value.
     * @param string $label Human-readable field label.
     * @param bool $allowEmpty Whether an empty value is valid.
     * @return \Cake\I18n\DateTime|null
     */
    private function parseDate(mixed $value, string $label, bool $allowEmpty): ?DateTime
    {
        if ($value instanceof DateTime) {
            return $value;
        }
        $value = trim((string)($value ?? ''));
        if ($value === '') {
            if ($allowEmpty) {
                return null;
            }
            throw new RuntimeException("The officer {$label} is required.");
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new RuntimeException("Enter a valid officer {$label}.");
        }

        try {
            $date = new DateTime($value);
        } catch (Throwable) {
            throw new RuntimeException("Enter a valid officer {$label}.");
        }
        if ($date->toDateString() !== $value) {
            throw new RuntimeException("Enter a valid officer {$label}.");
        }

        return $date;
    }

    /**
     * Resolve the audit actor from trusted engine context, with a direct-call fallback.
     *
     * @param array<string, mixed> $context Workflow context.
     * @param array<string, mixed> $config Resolved action configuration.
     * @return int
     */
    private function resolveActorId(array $context, array $config): int
    {
        $triggeredBy = (int)($context['triggeredBy'] ?? 0);
        if ($triggeredBy > 0) {
            return $triggeredBy;
        }

        return (int)$this->resolveValue($config['actorId'] ?? 0, $context);
    }

    /**
     * Return a user-safe partial-success result for extension failures.
     *
     * @return array<string, mixed>
     */
    private function warrantExtensionFailureResult(): array
    {
        $error = 'The warrant extension could not be requested. Review the warrant separately.';

        return [
            'success' => false,
            'error' => $error,
            'data' => [
                'requested' => false,
                'rosterId' => null,
                'warrantMessage' => $error,
                'warning' => $error,
            ],
        ];
    }

    /**
     * Normalize an ORM date value to DateTime.
     *
     * @param mixed $value ORM date value.
     * @return \Cake\I18n\DateTime|null
     */
    private function asDateTime(mixed $value): ?DateTime
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof DateTime) {
            return $value;
        }

        return new DateTime((string)$value);
    }

    /**
     * @param \Cake\I18n\DateTime|null $date Date to normalize.
     * @return string|null
     */
    private function dateKey(?DateTime $date): ?string
    {
        return $date?->toDateString();
    }

    /**
     * @param \Cake\I18n\DateTime|null $date Date to format.
     * @return string
     */
    private function displayDate(?DateTime $date): string
    {
        return $date === null ? 'no end date' : TimezoneHelper::formatDate($date);
    }
}
