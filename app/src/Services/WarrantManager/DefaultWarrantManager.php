<?php
declare(strict_types=1);

namespace App\Services\WarrantManager;

use App\KMP\StaticHelpers;
use App\Model\Entity\MemberRole;
use App\Model\Entity\Warrant;
use App\Model\Entity\WarrantPeriod;
use App\Model\Entity\WarrantRoster;
use App\Model\Entity\WorkflowInstance;
use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use App\Services\ServiceResult;
use App\Services\WorkflowEngine\TriggerDispatcher;
use Cake\I18n\Date;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use DateTimeInterface;
use RuntimeException;
use Throwable;

class DefaultWarrantManager implements WarrantManagerInterface
{
    #region

    private ActiveWindowManagerInterface $activeWindowManager;
    private TriggerDispatcher $triggerDispatcher;

    /**
     * Constructor.
     *
     * @param \App\Services\ActiveWindowManager\ActiveWindowManagerInterface $activeWindowManager
     * @param \App\Services\WorkflowEngine\TriggerDispatcher $triggerDispatcher
     */
    public function __construct(
        ActiveWindowManagerInterface $activeWindowManager,
        TriggerDispatcher $triggerDispatcher,
    ) {
        $this->activeWindowManager = $activeWindowManager;
        $this->triggerDispatcher = $triggerDispatcher;
        //Datetime tomorrow
        $yesterday = new DateTime();
        $yesterday->modify('-1 day');
        $warrantCheck = StaticHelpers::getAppSetting('Warrant.LastCheck');
        if ($warrantCheck == '' || $warrantCheck < $yesterday) {
            $warrantTable = TableRegistry::getTableLocator()->get('Warrants');
            $warrants = $warrantTable->find()
                ->where(['status' => Warrant::CURRENT_STATUS, 'expires_on <' => DateTime::now()])
                ->all();
            foreach ($warrants as $warrant) {
                $warrant->status = Warrant::EXPIRED_STATUS;
                $warrantTable->save($warrant);
            }
            StaticHelpers::setAppSetting('Warrant.LastCheck', DateTime::now()->toDateString());
        }
    }

    /**
     * Request.
     *
     * @param mixed $request_name
     * @param mixed $desc
     * @param mixed $warrantRequests
     * @return \App\Services\ServiceResult
     */
    public function request($request_name, $desc, $warrantRequests, ?int $requestedBy = null): ServiceResult
    {
        $requests = [];
        if (!is_iterable($warrantRequests)) {
            return new ServiceResult(false, 'Invalid warrant requests');
        }
        foreach ($warrantRequests as $warrantRequest) {
            if (!$warrantRequest instanceof WarrantRequest) {
                return new ServiceResult(false, 'Invalid warrant request');
            }
            $requests[] = $warrantRequest;
        }
        if ($requests === []) {
            return new ServiceResult(false, 'At least one warrant request is required');
        }

        $warrantRosterTable = TableRegistry::getTableLocator()->get('WarrantRosters');
        $connection = $warrantRosterTable->getConnection();
        $connection->enableSavePoints();

        try {
            return $connection->transactional(function () use (
                $request_name,
                $desc,
                $requests,
                $requestedBy,
                $warrantRosterTable,
            ): ServiceResult {
                $members = $this->lockRequestedMembers($requests);
                $normalizedRequests = $this->normalizeRequests($requests, $members);
                $actorId = $requestedBy ?? $normalizedRequests[0]['requester_id'];
                if ($actorId <= 0) {
                    $actorId = null;
                }

                $pendingWarrants = $this->findMatchingPendingWarrants($normalizedRequests);
                $workflowIdsByRoster = $this->lockActiveRosterWorkflows(
                    $this->getRosterIds($pendingWarrants),
                );

                // Re-read after locking workflow instances so an approval that
                // won the race cannot be overwritten as a replacement.
                $pendingWarrants = $this->findMatchingPendingWarrants($normalizedRequests);
                $existingRosterId = $this->findEquivalentPendingRoster(
                    $normalizedRequests,
                    $pendingWarrants,
                    $workflowIdsByRoster,
                );
                if ($existingRosterId !== null) {
                    return new ServiceResult(
                        true,
                        WarrantManagerInterface::REQUEST_REUSED_REASON,
                        $existingRosterId,
                    );
                }

                $warrantRoster = $warrantRosterTable->newEmptyEntity();
                $warrantRoster->status = WarrantRoster::STATUS_PENDING;
                $warrantRoster->name = (string)$request_name;
                $warrantRoster->description = (string)$desc;
                $warrantRoster->approvals_required = (int)StaticHelpers::getAppSetting(
                    'Warrant.RosterApprovalsRequired',
                    '2',
                );
                $warrantRoster->created_by = $actorId;
                if (!$warrantRosterTable->save($warrantRoster)) {
                    throw new RuntimeException('Failed to create warrant approval set');
                }

                $warrantTable = TableRegistry::getTableLocator()->get('Warrants');
                foreach ($normalizedRequests as $normalizedRequest) {
                    $member = $members[$normalizedRequest['member_id']];
                    $warrant = $warrantTable->newEmptyEntity();
                    $warrant->name = $normalizedRequest['name'];
                    $warrant->entity_type = $normalizedRequest['entity_type'];
                    $warrant->entity_id = $normalizedRequest['entity_id'];
                    $warrant->member_id = $normalizedRequest['member_id'];
                    $warrant->member_role_id = $normalizedRequest['member_role_id'];
                    $warrant->start_on = $normalizedRequest['start_on'];
                    $warrant->expires_on = $normalizedRequest['expires_on'];
                    $warrant->status = Warrant::PENDING_STATUS;
                    $warrant->warrant_roster_id = $warrantRoster->id;
                    $warrant->created_by = $normalizedRequest['requester_id'];
                    if (!$warrantTable->save($warrant)) {
                        throw new RuntimeException(
                            "Failed to create pending warrant for {$member->sca_name}",
                        );
                    }
                }

                $replacedRosterIds = $this->supersedePendingWarrants(
                    $pendingWarrants,
                    $workflowIdsByRoster,
                    $actorId,
                    $warrantRoster->id,
                    'Warrant requirements changed.',
                );
                if ($replacedRosterIds !== []) {
                    $this->addNewRosterReplacementNote(
                        $warrantRoster->id,
                        $replacedRosterIds,
                        $actorId,
                    );
                }

                $dispatchResults = $this->triggerDispatcher->dispatch('Warrants.RosterCreated', [
                    'rosterId' => $warrantRoster->id,
                    'rosterName' => $warrantRoster->name,
                    'approvalsRequired' => $warrantRoster->approvals_required,
                    'requesterId' => $actorId,
                ], $actorId);
                if ($dispatchResults === []) {
                    throw new RuntimeException('Failed to start warrant roster approval workflow');
                }
                foreach ($dispatchResults as $dispatchResult) {
                    if (!$dispatchResult instanceof ServiceResult || !$dispatchResult->success) {
                        throw new RuntimeException(
                            $dispatchResult instanceof ServiceResult
                                ? ($dispatchResult->reason ?? 'Failed to start warrant roster approval workflow')
                                : 'Invalid warrant roster workflow result',
                        );
                    }
                }

                return new ServiceResult(true, '', $warrantRoster->id);
            });
        } catch (Throwable $e) {
            Log::error('Warrant request failed: ' . $e->getMessage());

            return new ServiceResult(false, $e->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function withdrawPendingRequests(
        string $entityType,
        int $entityId,
        int $memberId,
        ?int $memberRoleId,
        int $requestedBy,
        string $reason,
    ): ServiceResult {
        $reason = trim($reason);
        if ($entityType === '' || $entityId <= 0 || $memberId <= 0 || $requestedBy <= 0 || $reason === '') {
            return new ServiceResult(false, 'Valid warrant identity, actor, and reason are required');
        }

        $warrantRosterTable = TableRegistry::getTableLocator()->get('WarrantRosters');
        $connection = $warrantRosterTable->getConnection();
        $connection->enableSavePoints();

        try {
            return $connection->transactional(function () use (
                $entityType,
                $entityId,
                $memberId,
                $memberRoleId,
                $requestedBy,
                $reason,
            ): ServiceResult {
                $this->lockMemberIds([$memberId]);
                $identity = [[
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'member_id' => $memberId,
                    'member_role_id' => $memberRoleId,
                ]];
                $pendingWarrants = $this->findMatchingPendingWarrants($identity);
                $workflowIdsByRoster = $this->lockActiveRosterWorkflows(
                    $this->getRosterIds($pendingWarrants),
                );
                $pendingWarrants = $this->findMatchingPendingWarrants($identity);
                if ($pendingWarrants === []) {
                    return new ServiceResult(true, '', 0);
                }

                $this->supersedePendingWarrants(
                    $pendingWarrants,
                    $workflowIdsByRoster,
                    $requestedBy,
                    null,
                    $reason,
                );

                return new ServiceResult(true, '', count($pendingWarrants));
            });
        } catch (Throwable $e) {
            Log::error('Pending warrant withdrawal failed: ' . $e->getMessage());

            return new ServiceResult(false, $e->getMessage());
        }
    }

    /**
     * Lock warrant recipients in a stable order to serialize pending requests.
     *
     * @param array<\App\Services\WarrantManager\WarrantRequest> $requests
     * @return array<int, \App\Model\Entity\Member>
     */
    private function lockRequestedMembers(array $requests): array
    {
        $memberIds = [];
        foreach ($requests as $request) {
            $memberIds[$request->member_id] = true;
        }
        $memberIds = array_keys($memberIds);

        return $this->lockMemberIds($memberIds);
    }

    /**
     * @param array<int> $memberIds
     * @return array<int, \App\Model\Entity\Member>
     */
    private function lockMemberIds(array $memberIds): array
    {
        sort($memberIds);

        $members = [];
        $memberEntities = TableRegistry::getTableLocator()->get('Members')
            ->find()
            ->where(['Members.id IN' => $memberIds])
            ->orderByAsc('Members.id')
            ->epilog('FOR UPDATE')
            ->all();
        foreach ($memberEntities as $member) {
            $members[(int)$member->id] = $member;
        }
        if (count($members) !== count($memberIds)) {
            throw new RuntimeException('A warrant recipient was not found');
        }

        return $members;
    }

    /**
     * Resolve requested dates into concrete warrant-period windows.
     *
     * @param array<\App\Services\WarrantManager\WarrantRequest> $requests
     * @param array<int, \App\Model\Entity\Member> $members
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRequests(array $requests, array $members): array
    {
        $normalized = [];
        $seenIdentities = [];
        $defaultStart = DateTime::now();

        foreach ($requests as $request) {
            $startOn = $request->start_on ?? $defaultStart;
            $warrantPeriod = $this->getWarrantPeriod($startOn, $request->expires_on);
            if ($warrantPeriod === null) {
                throw new RuntimeException('Invalid warrant period');
            }
            if ($warrantPeriod->end_date < $warrantPeriod->start_date) {
                throw new RuntimeException('Warrant end date cannot be before its start date');
            }

            $member = $members[$request->member_id];
            if (!$member->warrantable) {
                throw new RuntimeException("{$member->sca_name} is not warrantable");
            }
            if (
                $member->membership_expires_on !== null
                && $warrantPeriod->start_date > $member->membership_expires_on
            ) {
                throw new RuntimeException(
                    "Warrant period is after membership expires for {$member->sca_name}",
                );
            }

            $item = [
                'name' => $request->name,
                'entity_type' => $request->entity_type,
                'entity_id' => $request->entity_id,
                'requester_id' => $request->requester_id,
                'member_id' => $request->member_id,
                'member_role_id' => $request->member_role_id,
                'start_on' => new DateTime($warrantPeriod->start_date->toDateString()),
                'expires_on' => new DateTime($warrantPeriod->end_date->toDateString()),
            ];
            $identityKey = $this->warrantIdentityKey($item);
            if (isset($seenIdentities[$identityKey])) {
                throw new RuntimeException('Duplicate warrant subject in request');
            }
            $seenIdentities[$identityKey] = true;
            $normalized[] = $item;
        }

        return $normalized;
    }

    /**
     * Find in-flight warrants for any requested warrant subject.
     *
     * @param array<int, array<string, mixed>> $normalizedRequests
     * @return array<\App\Model\Entity\Warrant>
     */
    private function findMatchingPendingWarrants(array $normalizedRequests): array
    {
        $identityConditions = [];
        foreach ($normalizedRequests as $request) {
            $condition = [
                'Warrants.entity_type' => $request['entity_type'],
                'Warrants.entity_id' => $request['entity_id'],
                'Warrants.member_id' => $request['member_id'],
            ];
            if ($request['member_role_id'] === null) {
                $condition['Warrants.member_role_id IS'] = null;
            } else {
                $condition['Warrants.member_role_id'] = $request['member_role_id'];
            }
            $identityConditions[] = $condition;
        }

        return TableRegistry::getTableLocator()->get('Warrants')
            ->find()
            ->innerJoinWith('WarrantRosters')
            ->where([
                'Warrants.status' => Warrant::PENDING_STATUS,
                'WarrantRosters.status' => WarrantRoster::STATUS_PENDING,
                'OR' => $identityConditions,
            ])
            ->orderByAsc('Warrants.id')
            ->all()
            ->toList();
    }

    /**
     * @param array<\App\Model\Entity\Warrant> $warrants
     * @return array<int>
     */
    private function getRosterIds(array $warrants): array
    {
        $rosterIds = [];
        foreach ($warrants as $warrant) {
            $rosterIds[(int)$warrant->warrant_roster_id] = true;
        }

        return array_keys($rosterIds);
    }

    /**
     * Lock approval workflows that can still act on matching pending warrants.
     *
     * @param array<int> $rosterIds
     * @return array<int, array<int>>
     */
    private function lockActiveRosterWorkflows(array $rosterIds): array
    {
        if ($rosterIds === []) {
            return [];
        }

        $workflowIdsByRoster = [];
        $instances = TableRegistry::getTableLocator()->get('WorkflowInstances')
            ->find()
            ->where([
                'WorkflowInstances.entity_type' => 'WarrantRosters',
                'WorkflowInstances.entity_id IN' => $rosterIds,
                'WorkflowInstances.status IN' => WorkflowInstance::ACTIVE_STATUSES,
            ])
            ->orderByAsc('WorkflowInstances.id')
            ->epilog('FOR UPDATE')
            ->all();
        foreach ($instances as $instance) {
            $workflowIdsByRoster[(int)$instance->entity_id][] = (int)$instance->id;
        }

        return $workflowIdsByRoster;
    }

    /**
     * Return a healthy roster only when the request is an exact retry.
     *
     * @param array<int, array<string, mixed>> $normalizedRequests
     * @param array<\App\Model\Entity\Warrant> $pendingWarrants
     * @param array<int, array<int>> $workflowIdsByRoster
     * @return int|null
     */
    private function findEquivalentPendingRoster(
        array $normalizedRequests,
        array $pendingWarrants,
        array $workflowIdsByRoster,
    ): ?int {
        $pendingByIdentity = [];
        foreach ($pendingWarrants as $warrant) {
            $pendingByIdentity[$this->warrantIdentityKey([
                'entity_type' => $warrant->entity_type,
                'entity_id' => $warrant->entity_id,
                'member_id' => $warrant->member_id,
                'member_role_id' => $warrant->member_role_id,
            ])][] = $warrant;
        }

        $rosterId = null;
        foreach ($normalizedRequests as $request) {
            $candidates = $pendingByIdentity[$this->warrantIdentityKey($request)] ?? [];
            if (count($candidates) !== 1 || !$this->pendingWarrantIsEquivalent($candidates[0], $request)) {
                return null;
            }
            $candidateRosterId = (int)$candidates[0]->warrant_roster_id;
            if ($rosterId !== null && $candidateRosterId !== $rosterId) {
                return null;
            }
            $rosterId = $candidateRosterId;
        }

        if ($rosterId === null || ($workflowIdsByRoster[$rosterId] ?? []) === []) {
            return null;
        }

        return $rosterId;
    }

    /**
     * @param array<string, mixed> $request
     */
    private function pendingWarrantIsEquivalent(Warrant $warrant, array $request): bool
    {
        return $warrant->name === $request['name']
            && $warrant->start_on?->toDateString() === $request['start_on']->toDateString()
            && $warrant->expires_on?->toDateString() === $request['expires_on']->toDateString();
    }

    /**
     * @param array<string, mixed> $warrant
     */
    private function warrantIdentityKey(array $warrant): string
    {
        return hash('sha256', implode("\0", [
            (string)$warrant['entity_type'],
            (string)$warrant['entity_id'],
            (string)$warrant['member_id'],
            $warrant['member_role_id'] === null ? 'null' : 'id:' . $warrant['member_role_id'],
        ]));
    }

    /**
     * Replace pending warrants and close old rosters that no longer contain work.
     *
     * @param array<\App\Model\Entity\Warrant> $pendingWarrants
     * @param array<int, array<int>> $workflowIdsByRoster
     * @param int|null $actorId Member recording the replacement
     * @param int|null $replacementRosterId Fresh roster ID, or null for withdrawal
     * @param string $reason Audit reason
     * @return array<int> Affected old roster IDs
     */
    private function supersedePendingWarrants(
        array $pendingWarrants,
        array $workflowIdsByRoster,
        ?int $actorId,
        ?int $replacementRosterId,
        string $reason,
    ): array {
        $warrantTable = TableRegistry::getTableLocator()->get('Warrants');
        $warrantRosterTable = TableRegistry::getTableLocator()->get('WarrantRosters');
        $revokedReason = $replacementRosterId === null
            ? mb_substr($reason, 0, 255)
            : "Replaced by warrant roster #{$replacementRosterId} after requirements changed";
        $replacedRosterIds = [];

        foreach ($pendingWarrants as $pendingWarrant) {
            $pendingWarrant->status = Warrant::REPLACED_STATUS;
            $pendingWarrant->revoked_reason = $revokedReason;
            $pendingWarrant->revoker_id = $actorId;
            if (!$warrantTable->save($pendingWarrant)) {
                throw new RuntimeException('Failed to replace an obsolete pending warrant');
            }
            $replacedRosterIds[(int)$pendingWarrant->warrant_roster_id] = true;
        }

        foreach (array_keys($replacedRosterIds) as $replacedRosterId) {
            $pendingCount = $warrantTable->find()
                ->where([
                    'warrant_roster_id' => $replacedRosterId,
                    'status' => Warrant::PENDING_STATUS,
                ])
                ->count();
            $oldRoster = $warrantRosterTable->get($replacedRosterId);
            if ($pendingCount === 0 && $oldRoster->status === WarrantRoster::STATUS_PENDING) {
                $oldRoster->status = WarrantRoster::STATUS_REPLACED;
                if (!$warrantRosterTable->save($oldRoster)) {
                    throw new RuntimeException('Failed to close a replaced warrant roster');
                }
                $workflowReason = $replacementRosterId === null
                    ? $reason
                    : "Pending warrants replaced by roster #{$replacementRosterId}";
                foreach ($workflowIdsByRoster[$replacedRosterId] ?? [] as $workflowId) {
                    $cancelResult = $this->triggerDispatcher->getEngine()->cancelWorkflow(
                        $workflowId,
                        $workflowReason,
                    );
                    if (!$cancelResult->success) {
                        throw new RuntimeException(
                            $cancelResult->reason ?? 'Failed to cancel replaced warrant workflow',
                        );
                    }
                }
            }

            if ($replacementRosterId === null) {
                $this->addRosterWithdrawalNote(
                    $replacedRosterId,
                    $actorId,
                    $reason,
                    $pendingCount === 0,
                );
            } else {
                $this->addRosterReplacementNote(
                    $replacedRosterId,
                    $replacementRosterId,
                    $actorId,
                    $pendingCount === 0,
                );
            }
        }

        return array_keys($replacedRosterIds);
    }

    /**
     * Record how an older roster was affected by a replacement request.
     */
    private function addRosterReplacementNote(
        int $oldRosterId,
        int $newRosterId,
        ?int $actorId,
        bool $fullyReplaced,
    ): void {
        if ($actorId === null) {
            return;
        }

        $body = $fullyReplaced
            ? "All pending warrants were replaced by warrant roster #{$newRosterId}."
            : "One or more pending warrants were replaced by warrant roster #{$newRosterId}; "
                . 'the remaining pending warrants stay in flight.';
        $this->saveRosterNote($oldRosterId, 'Pending warrant request replaced', $body, $actorId);
    }

    /**
     * Record why pending warrants were withdrawn without a replacement roster.
     */
    private function addRosterWithdrawalNote(
        int $rosterId,
        ?int $actorId,
        string $reason,
        bool $fullyReplaced,
    ): void {
        if ($actorId === null) {
            return;
        }

        $body = $fullyReplaced
            ? "All pending warrants in this roster were withdrawn. Reason: {$reason}"
            : 'One or more pending warrants were withdrawn. Remaining pending warrants stay in flight. '
                . "Reason: {$reason}";
        $this->saveRosterNote($rosterId, 'Pending warrant request withdrawn', $body, $actorId);
    }

    /**
     * @param array<int> $oldRosterIds
     */
    private function addNewRosterReplacementNote(
        int $newRosterId,
        array $oldRosterIds,
        ?int $actorId,
    ): void {
        if ($actorId === null) {
            return;
        }

        sort($oldRosterIds);
        $rosterReferences = implode(', ', array_map(
            static fn(int $rosterId): string => "#{$rosterId}",
            $oldRosterIds,
        ));
        $this->saveRosterNote(
            $newRosterId,
            'Replacement warrant request',
            "This roster replaces pending warrant requirements from roster(s) {$rosterReferences}.",
            $actorId,
        );
    }

    /**
     * Persist a public audit note against a warrant roster.
     */
    private function saveRosterNote(int $rosterId, string $subject, string $body, int $actorId): void
    {
        $notesTable = TableRegistry::getTableLocator()->get('Notes');
        $note = $notesTable->newEmptyEntity();
        $note->entity_type = 'WarrantRosters';
        $note->entity_id = $rosterId;
        $note->subject = $subject;
        $note->body = $body;
        $note->author_id = $actorId;
        $note->private = false;
        if (!$notesTable->save($note)) {
            throw new RuntimeException('Failed to record warrant roster audit note');
        }
    }

    /**
     * Activate warrants for a roster that the workflow has already approved.
     *
     * @param int $rosterId Warrant roster ID
     * @param int $approverId Member performing the approval
     * @return \App\Services\ServiceResult
     */
    public function activateApprovedRoster(
        int $rosterId,
        int $approverId,
    ): ServiceResult {
        $warrantTable = TableRegistry::getTableLocator()->get('Warrants');
        $warrantRosterTable = TableRegistry::getTableLocator()->get('WarrantRosters');

        $warrants = $warrantTable->find()
            ->contain(['Members' => function ($q) {
                return $q->select(['id', 'email_address', 'sca_name']);
            }])
            ->where([
                'warrant_roster_id' => $rosterId,
                'Warrants.status' => Warrant::PENDING_STATUS,
            ])
            ->all();

        // Idempotent: no pending warrants to activate
        if ($warrants->isEmpty()) {
            return new ServiceResult(true);
        }

        $warrantRosterTable->getConnection()->begin();
        foreach ($warrants as $warrant) {
            $warrant->status = Warrant::CURRENT_STATUS;
            $warrant->approved_date = new DateTime();
            $now = new DateTime();
            $warrantStart = $warrant->start_on;
            if ($warrant->start_on == null || $warrantStart < $now) {
                $warrant->start_on = $now;
            }
            if (!$warrantTable->save($warrant)) {
                $warrantRosterTable->getConnection()->rollback();

                return new ServiceResult(false, 'Failed to activate warrants in Roster');
            }
            //expire current warrants for the same entity_type entity_id member_id
            $warrantTable->updateAll(
                [
                    'expires_on' => $warrant->start_on,
                    'revoked_reason' => 'New Warrant Approved',
                    'revoker_id' => $approverId,
                ],
                [
                    'entity_type' => $warrant->entity_type,
                    'entity_id' => $warrant->entity_id,
                    'member_id' => $warrant->member_id,
                    'status' => Warrant::CURRENT_STATUS,
                    'expires_on >=' => $warrant->start_on,
                    'start_on <=' => $warrant->start_on,
                    'id !=' => $warrant->id,
                ],
            );
        }
        $warrantRosterTable->getConnection()->commit();

        return new ServiceResult(true);
    }

    /**
     * Sync workflow approval responses onto the roster summary fields.
     *
     * @param int $rosterId Warrant roster ID
     * @param int $approverId Member providing the approval
     * @param string|null $notes Optional approval notes
     * @param \DateTimeInterface|null $approvedOn Approval timestamp
     * @return \App\Services\ServiceResult
     */
    public function syncWorkflowApprovalToRoster(
        int $rosterId,
        int $approverId,
        ?string $notes = null,
        ?DateTimeInterface $approvedOn = null,
    ): ServiceResult {
        $warrantRosterTable = TableRegistry::getTableLocator()->get('WarrantRosters');

        // Update the denormalized counter on the roster.
        // Dedup is handled by the workflow engine's approval manager.
        $warrantRosterTable->getConnection()->execute(
            'UPDATE warrant_rosters SET approval_count = COALESCE(approval_count, 0) + 1 WHERE id = ?',
            [$rosterId],
            ['integer'],
        );

        return new ServiceResult(true);
    }

    /**
     * Decline a warrant roster that the workflow has already rejected.
     *
     * Pure domain work: declines all pending warrants on the roster, stops their
     * dependants, and transitions the roster to DECLINED. This is the decline
     * counterpart to {@see self::activateApprovedRoster()} and must NOT drive the
     * workflow engine — the engine has already routed to this action via the
     * approval-gate's rejected port before this runs.
     *
     * @param mixed $warrant_roster_id Warrant roster ID
     * @param mixed $rejecter_id Member performing the decline
     * @param mixed $reason Reason for the decline
     * @return \App\Services\ServiceResult
     */
    public function decline($warrant_roster_id, $rejecter_id, $reason): ServiceResult
    {
        $warrantRosterTable = TableRegistry::getTableLocator()->get('WarrantRosters');
        $warrantTable = TableRegistry::getTableLocator()->get('Warrants');

        $warrantRoster = $warrantRosterTable->find()
            ->where(['id' => $warrant_roster_id])
            ->first();
        if ($warrantRoster == null) {
            return new ServiceResult(false, 'Warrant Roster not found');
        }
        if ($warrantRoster->status != WarrantRoster::STATUS_PENDING) {
            return new ServiceResult(false, 'Warrant Roster is not pending');
        }

        $warrants = $warrantTable->find()
            ->where([
                'warrant_roster_id' => $warrant_roster_id,
                'status' => Warrant::PENDING_STATUS,
            ])
            ->all();

        $connection = $warrantRosterTable->getConnection();
        $connection->begin();

        foreach ($warrants as $warrant) {
            $result = $this->declineWarrant($warrantTable, $warrant, $rejecter_id, $reason);
            if (!$result->success) {
                $connection->rollback();

                return $result;
            }
        }

        $warrantRoster->status = WarrantRoster::STATUS_DECLINED;
        if (!$warrantRosterTable->save($warrantRoster)) {
            $connection->rollback();

            return new ServiceResult(false, 'Failed to decline Warrant Roster');
        }

        $noteTbl = TableRegistry::getTableLocator()->get('Notes');
        $note = $noteTbl->newEmptyEntity();
        $note->entity_type = 'WarrantRosters';
        $note->entity_id = $warrantRoster->id;
        $note->subject = 'Warrant Roster declined';
        $note->body = $reason;
        $note->author_id = $rejecter_id;
        if (!$noteTbl->save($note)) {
            $connection->rollback();

            return new ServiceResult(false, 'Failed to decline Warrant Roster');
        }

        $connection->commit();

        return new ServiceResult(true);
    }

    /**
     * Cancel.
     *
     * @param mixed $warrant_id
     * @param mixed $reason
     * @param mixed $rejecter_id
     * @param mixed $expiresOn
     * @return \App\Services\ServiceResult
     */
    public function cancel($warrant_id, $reason, $rejecter_id, $expiresOn): ServiceResult
    {
        $warrantTable = TableRegistry::getTableLocator()->get('Warrants');
        $warrant = $warrantTable->get($warrant_id);
        if ($warrant == null) {
            return new ServiceResult(true);
        }

        return $this->cancelWarrant($warrantTable, $warrant, $expiresOn, $rejecter_id, $reason);
    }

    /**
     * Cancel by entity.
     *
     * @param mixed $entityType
     * @param mixed $entityId
     * @param mixed $reason
     * @param mixed $rejecter_id
     * @param mixed $expiresOn
     * @return \App\Services\ServiceResult
     */
    public function cancelByEntity($entityType, $entityId, $reason, $rejecter_id, $expiresOn): ServiceResult
    {
        $warrantTable = TableRegistry::getTableLocator()->get('Warrants');
        $warrant = $warrantTable->find()
            ->where([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ])
            ->first();
        if ($warrant == null) {
            return new ServiceResult(true);
        }

        return $this->cancelWarrant($warrantTable, $warrant, $expiresOn, $rejecter_id, $reason);
    }

    /**
     * Decline single warrant.
     *
     * @param mixed $warrant_id
     * @param mixed $reason
     * @param mixed $rejecter_id
     * @return \App\Services\ServiceResult
     */
    public function declineSingleWarrant($warrant_id, $reason, $rejecter_id): ServiceResult
    {
        $warrantTable = TableRegistry::getTableLocator()->get('Warrants');

        $warrant = $warrantTable->get($warrant_id);
        if ($warrant == null) {
            return new ServiceResult(true);
        }
        //begin transaction
        $connection = $warrantTable->getConnection();
        $connection->begin();
        $result = $this->declineWarrant($warrantTable, $warrant, $rejecter_id, $reason);
        if (!$result->success) {
            $connection->rollback();

            return $result;
        }

        $warrantRosterTable = TableRegistry::getTableLocator()->get('WarrantRosters');
        $warrantRoster = $warrantRosterTable->find()
            ->where(['id' => $warrant->warrant_roster_id])
            ->select(['id', 'status'])
            ->first();
        if ($warrantRoster == null) {
            //rollback transaction
            $connection->rollback();

            return new ServiceResult(false, 'Failed to decline warrant');
        }
        if ($warrantRoster->status == WarrantRoster::STATUS_PENDING) {
            $pendingWarrantCount = $warrantTable->find()
                ->where([
                    'warrant_roster_id' => $warrantRoster->id,
                    'status' => Warrant::PENDING_STATUS,
                ])
                ->count();
            if ($pendingWarrantCount == 0) {
                $warrantRoster->status = WarrantRoster::STATUS_DECLINED;
                if (!$warrantRosterTable->save($warrantRoster)) {
                    //rollback transaction
                    $connection->rollback();

                    return new ServiceResult(false, 'Failed to decline warrant');
                }
                //add a note
                $noteTbl = TableRegistry::getTableLocator()->get('Notes');
                $note = $noteTbl->newEmptyEntity();
                $note->entity_type = 'WarrantRosters';
                $note->entity_id = $warrantRoster->id;
                $note->subject = 'Warrant Roster declined';
                $note->body = 'All Warrants in the roster were individually declined, and so the roster was declined.';
                $note->author_id = $rejecter_id;
                if (!$noteTbl->save($note)) {
                    //rollback transaction
                    $connection->rollback();

                    return new ServiceResult(false, 'Failed to decline warrant');
                }
            }
        }
        //commit transaction
        $connection->commit();

        return new ServiceResult(true);
    }

    /**
     * Get warrant period.
     *
     * @param \Cake\I18n\DateTime $startOn
     * @param ?\Cake\I18n\DateTime $endOn
     * @return ?\App\Model\Entity\WarrantPeriod
     */
    public function getWarrantPeriod(DateTime $startOn, ?DateTime $endOn): ?WarrantPeriod
    {
        $periodStart = new DateTime();
        $warrantPeriodTable = TableRegistry::getTableLocator()->get('WarrantPeriods');
        if ($startOn > $periodStart) {
            $periodStart = $startOn;
        }
        $warrantPeriod = $warrantPeriodTable->find()
            ->where([
                'start_date <=' => $periodStart,
                'end_date >=' => $startOn,
                'end_date >' => $periodStart,
            ])
            ->orderByDesc('start_date')
            ->first();
        if ($warrantPeriod == null) {
            return null;
        }
        if (($endOn != null) && ($warrantPeriod->end_date->toNative() > $endOn->toNative())) {
            $warrantPeriod->end_date = new Date($endOn->toDateString());
        }
        if ($warrantPeriod->start_date->toNative() < $startOn->toNative()) {
            $warrantPeriod->start_date = new Date($startOn->toDateString());
        }

        return $warrantPeriod;
    }

    /**
     * Cancel warrant.
     *
     * @param mixed $warrantTable
     * @param mixed $warrant
     * @param mixed $expiresOn
     * @param mixed $rejecter_id
     * @param mixed $reason
     * @return \App\Services\ServiceResult
     */
    protected function cancelWarrant($warrantTable, $warrant, $expiresOn, $rejecter_id, $reason): ServiceResult
    {
        if ($expiresOn < new DateTime()) {
            $warrant->status = Warrant::DEACTIVATED_STATUS;
        }
        $warrant->expires_on = $expiresOn;
        $warrant->revoked_reason = $reason;
        $warrant->revoker_id = $rejecter_id;
        if (!$warrantTable->save($warrant)) {
            return new ServiceResult(false, 'Failed to cancel warrant');
        }

        return new ServiceResult(true);
    }

    /**
     * Stop warrant dependants.
     *
     * @param mixed $warrant
     * @param mixed $rejecter_id
     * @return \App\Services\ServiceResult
     */
    protected function stopWarrantDependants($warrant, $rejecter_id): ServiceResult
    {
        if ($warrant->member_role_id != null) {
            /**
             *
             * @var \App\Services\WarrantManager\ServiceRequest $awResult
             */
            $awResult = $this->activeWindowManager->stop(
                'MemberRoles',
                $warrant->member_role_id,
                $rejecter_id,
                MemberRole::DEACTIVATED_STATUS,
                'Warrant Declined',
                new DateTime(),
            );
            if (!$awResult->success) {
                return new ServiceResult(false, $awResult->reason);
            }
        }
        if ($warrant->entity_type != 'Direct Grant') {
            /**
             *
             * @var \App\Services\WarrantManager\ServiceRequest $awResult
             */
            $awResult = $this->activeWindowManager->stop(
                $warrant->entity_type,
                $warrant->entity_id,
                $rejecter_id,
                MemberRole::DEACTIVATED_STATUS,
                'Warrant Declined',
                new DateTime(),
            );
            if (!$awResult->success) {
                return new ServiceResult(false, $awResult->reason);
            }
        }

        return new ServiceResult(true);
    }

    /**
     * Decline warrant.
     *
     * @param mixed $warrantTable
     * @param mixed $warrant
     * @param mixed $rejecter_id
     * @param mixed $reason
     * @return \App\Services\ServiceResult
     */
    protected function declineWarrant($warrantTable, $warrant, $rejecter_id, $reason): ServiceResult
    {
        $warrant->status = Warrant::DECLINED_STATUS;
        $warrant->revoked_reason = $reason;
        $warrant->revoker_id = $rejecter_id;
        if (!$warrantTable->save($warrant)) {
            return new ServiceResult(false, 'Failed to decline warrant');
        }
        $result = $this->stopWarrantDependants($warrant, $rejecter_id, $reason);
        if (!$result->success) {
            return $result;
        }

        return new ServiceResult(true);
    }
}
