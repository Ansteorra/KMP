<?php
declare(strict_types=1);

namespace Awards\Services;

use Awards\Model\Entity\Bestowal;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Table;
use DateTimeInterface;
use DateTimeZone;
use RuntimeException;
use Throwable;

/**
 * Applies bestowal state transitions for single and bulk operations.
 *
 * Does not sync linked recommendations — that is handled separately by
 * BestowalRecommendationSyncService via workflow command graphs.
 */
class BestowalTransitionService
{
    use LocatorAwareTrait;

    private const SINGLE_NOTE_SUBJECT = 'Bestowal Updated';
    private const BULK_NOTE_SUBJECT = 'Bestowal Bulk Updated';

    private BestowalStateLogService $stateLogService;

    /**
     * @param \Awards\Services\BestowalStateLogService|null $stateLogService Optional injected state-log service.
     */
    public function __construct(?BestowalStateLogService $stateLogService = null)
    {
        $this->stateLogService = $stateLogService ?? new BestowalStateLogService();
    }

    /**
     * Transition a single bestowal and return structured transition data.
     *
     * @param \Cake\ORM\Table $bestowalsTable Bestowals table instance.
     * @param string|int $bestowalId Bestowal ID to transition.
     * @param array<string, mixed> $data Transition input.
     * @param int $actorId Current user ID.
     * @return array<string, mixed>
     */
    public function transition(
        Table $bestowalsTable,
        int|string $bestowalId,
        array $data,
        int $actorId,
    ): array {
        $result = $this->runTransitions(
            $bestowalsTable,
            [(string)$bestowalId],
            $data,
            $actorId,
            self::SINGLE_NOTE_SUBJECT,
        );

        if ($result['success'] ?? false) {
            $result['data']['bestowalId'] = (int)$bestowalId;
            $result['data']['result'] = $result['data']['results'][0] ?? null;
        }

        return $result;
    }

    /**
     * Transition multiple bestowals and return structured transition data.
     *
     * @param \Cake\ORM\Table $bestowalsTable Bestowals table instance.
     * @param array<string, mixed> $data Transition input, including ids/bestowalIds.
     * @param int $actorId Current user ID.
     * @return array<string, mixed>
     */
    public function transitionMany(
        Table $bestowalsTable,
        array $data,
        int $actorId,
    ): array {
        $ids = $this->extractIds($data);

        return $this->runTransitions(
            $bestowalsTable,
            $ids,
            $data,
            $actorId,
            self::BULK_NOTE_SUBJECT,
        );
    }

    /**
     * Execute one or more transitions inside a transaction.
     *
     * @param \Cake\ORM\Table $bestowalsTable Bestowals table instance.
     * @param array<int, string> $ids Bestowal IDs.
     * @param array<string, mixed> $data Transition input.
     * @param int $actorId Current user ID.
     * @param string $defaultNoteSubject Default subject for created notes.
     * @return array<string, mixed>
     */
    private function runTransitions(
        Table $bestowalsTable,
        array $ids,
        array $data,
        int $actorId,
        string $defaultNoteSubject,
    ): array {
        $targetState = $this->extractTargetState($data);
        if ($targetState === '') {
            return [
                'success' => false,
                'error' => 'Target state is required',
                'data' => [
                    'processedCount' => 0,
                    'results' => [],
                ],
            ];
        }

        if ($ids === []) {
            return [
                'success' => false,
                'error' => 'At least one bestowal ID is required',
                'data' => [
                    'processedCount' => 0,
                    'results' => [],
                ],
            ];
        }

        try {
            return $bestowalsTable->getConnection()->transactional(
                function () use (
                    $bestowalsTable,
                    $ids,
                    $data,
                    $actorId,
                    $targetState,
                    $defaultNoteSubject,
                ): array {
                    $bestowals = $bestowalsTable->find()
                        ->where(['id IN' => $ids])
                        ->all()
                        ->indexBy('id')
                        ->toArray();

                    $missingIds = array_values(
                        array_diff($ids, array_map('strval', array_keys($bestowals))),
                    );
                    if ($missingIds !== []) {
                        throw new RuntimeException(
                            'Bestowals not found: ' . implode(', ', $missingIds),
                        );
                    }

                    $results = [];
                    foreach ($ids as $id) {
                        $results[] = $this->applyTransition(
                            $bestowalsTable,
                            $bestowals[(int)$id],
                            $targetState,
                            $data,
                            $actorId,
                            $defaultNoteSubject,
                        );
                    }

                    return [
                        'success' => true,
                        'data' => [
                            'processedCount' => count($results),
                            'targetState' => $targetState,
                            'bestowalIds' => array_map('intval', $ids),
                            'results' => $results,
                        ],
                    ];
                },
            );
        } catch (Throwable $e) {
            Log::error('Bestowal transition failed: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => [
                    'processedCount' => 0,
                    'targetState' => $targetState,
                    'bestowalIds' => array_map('intval', $ids),
                    'results' => [],
                ],
            ];
        }
    }

    /**
     * Apply the transition semantics to one bestowal.
     *
     * @param \Cake\ORM\Table $bestowalsTable Bestowals table instance.
     * @param \Awards\Model\Entity\Bestowal $bestowal Bestowal to update.
     * @param string $targetState Target state name.
     * @param array<string, mixed> $data Transition input.
     * @param int $actorId Current user ID.
     * @param string $defaultNoteSubject Default subject for created notes.
     * @return array<string, mixed>
     */
    private function applyTransition(
        Table $bestowalsTable,
        Bestowal $bestowal,
        string $targetState,
        array $data,
        int $actorId,
        string $defaultNoteSubject,
    ): array {
        $appliedSetRules = Bestowal::getStateRules()[$targetState]['Set'] ?? [];
        $before = [
            'state' => $bestowal->state,
            'status' => $bestowal->status,
            'state_date' => $bestowal->state_date,
            'gathering_id' => $bestowal->gathering_id,
            'gathering_scheduled_activity_id' => $bestowal->gathering_scheduled_activity_id,
            'bestowed_at' => $bestowal->bestowed_at,
            'close_reason' => $bestowal->close_reason,
        ];

        $bestowal->state = $targetState;
        $bestowal->modified_by = $actorId;

        if (
            Bestowal::supportsGatheringAssignmentForState($targetState)
            && $this->hasAnyKey($data, ['gathering_id', 'gatheringId'])
        ) {
            $gatheringId = $this->getOptionalValue($data, ['gathering_id', 'gatheringId']);
            if ($gatheringId !== null) {
                $bestowal->gathering_id = $gatheringId === '' ? null : (int)$gatheringId;
            }
        }

        if ($this->hasAnyKey($data, ['gathering_scheduled_activity_id', 'gatheringScheduledActivityId'])) {
            $activityId = $this->getOptionalValue(
                $data,
                ['gathering_scheduled_activity_id', 'gatheringScheduledActivityId'],
            );
            if ($activityId !== null) {
                (new BestowalCourtSlotService())->applyCourtSessionSelection($bestowal, $activityId);
            }
        }

        if ($this->hasAnyKey($data, ['bestowed_at', 'bestowedAt'])) {
            $bestowedAt = $this->getOptionalValue($data, ['bestowed_at', 'bestowedAt']);
            if ($bestowedAt !== null) {
                $bestowal->bestowed_at = $this->normalizeDateTime($bestowedAt);
            }
        }

        if (
            !array_key_exists('close_reason', $appliedSetRules)
            && $this->hasAnyKey($data, ['close_reason', 'closeReason'])
        ) {
            $closeReason = $this->getOptionalValue($data, ['close_reason', 'closeReason']);
            if ($closeReason !== null) {
                $bestowal->close_reason = $this->normalizeOptionalString($closeReason);
            }
        }

        if ($this->hasAnyKey($data, ['stack_rank', 'stackRank'])) {
            $stackRank = $this->getOptionalValue($data, ['stack_rank', 'stackRank']);
            if ($stackRank !== null && $stackRank !== '') {
                $bestowal->stack_rank = (int)$stackRank;
            }
        }

        // Bestowal award is independent of linked recommendations; never propagate to recs.
        if ($this->hasAnyKey($data, ['award_id', 'awardId'])) {
            $awardId = $this->getOptionalValue($data, ['award_id', 'awardId']);
            if ($awardId === null || $awardId === '') {
                throw new RuntimeException('Award to Bestow is required.');
            }
            $bestowal->award_id = (int)$awardId;
        }

        if ($this->hasAnyKey($data, ['specialty'])) {
            $specialty = $this->getOptionalValue($data, ['specialty']);
            if ($specialty !== null) {
                $bestowal->specialty = $this->normalizeOptionalString($specialty);
            }
        }

        if ($this->hasAnyKey($data, ['noble_notes', 'nobleNotes'])) {
            $nobleNotes = $this->getOptionalValue($data, ['noble_notes', 'nobleNotes']);
            if ($nobleNotes !== null) {
                $bestowal->noble_notes = $this->normalizeOptionalString($nobleNotes);
            }
        }

        if ($this->hasAnyKey($data, ['herald_notes', 'heraldNotes'])) {
            $heraldNotes = $this->getOptionalValue($data, ['herald_notes', 'heraldNotes']);
            if ($heraldNotes !== null) {
                $bestowal->herald_notes = $this->normalizeOptionalString($heraldNotes);
            }
        }

        if ($this->hasAnyKey($data, ['reason_summary', 'reasonSummary'])) {
            $reasonSummary = $this->getOptionalValue($data, ['reason_summary', 'reasonSummary']);
            if ($reasonSummary !== null) {
                $bestowal->reason_summary = $this->normalizeOptionalString($reasonSummary);
            }
        }

        $saved = $bestowalsTable->saveOrFail($bestowal);
        $this->stateLogService->logStateTransition(
            (int)$saved->id,
            (string)$before['state'],
            (string)$saved->state,
            (string)$before['status'],
            $saved->status !== null ? (string)$saved->status : null,
            $actorId,
        );

        $noteId = null;
        $noteCreated = false;

        $note = $this->normalizeOptionalString($this->getOptionalValue($data, ['note']));
        if ($note !== null) {
            $notesTable = $this->fetchTable('Notes');
            $newNote = $notesTable->newEmptyEntity();
            $newNote->entity_id = $saved->id;
            $newNote->subject = $this->resolveNoteSubject($data, $defaultNoteSubject);
            $newNote->entity_type = 'Awards.Bestowals';
            $newNote->body = $note;
            $newNote->author_id = $actorId;
            $notesTable->saveOrFail($newNote);
            $noteCreated = true;
            $noteId = (int)$newNote->id;
        }

        return [
            'bestowalId' => (int)$saved->id,
            'previousState' => (string)$before['state'],
            'newState' => (string)$saved->state,
            'previousStatus' => (string)$before['status'],
            'newStatus' => (string)$saved->status,
            'stateDate' => $this->serializeValue($saved->state_date),
            'gatheringId' => $saved->gathering_id === null ? null : (int)$saved->gathering_id,
            'gatheringScheduledActivityId' => $saved->gathering_scheduled_activity_id === null
                ? null
                : (int)$saved->gathering_scheduled_activity_id,
            'bestowedAt' => $this->serializeValue($saved->bestowed_at),
            'closeReason' => $saved->close_reason,
            'appliedSetRules' => $appliedSetRules,
            'noteCreated' => $noteCreated,
            'noteId' => $noteId,
            'changes' => $this->buildChangeSet($before, $saved, array_keys($appliedSetRules)),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractTargetState(array $data): string
    {
        $state = $this->getOptionalValue($data, ['newState', 'targetState', 'state', 'toState']);

        return is_string($state) ? trim($state) : '';
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, string>
     */
    private function extractIds(array $data): array
    {
        $ids = $this->getOptionalValue($data, ['ids', 'bestowalIds']);
        if (!is_array($ids)) {
            return [];
        }

        $normalized = [];
        foreach ($ids as $id) {
            if ($id === null || $id === '') {
                continue;
            }
            $normalized[] = (string)$id;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param array<string, mixed> $before
     * @param array<int, string> $ruleFields
     * @return array<string, array{before: mixed, after: mixed}>
     */
    private function buildChangeSet(array $before, Bestowal $bestowal, array $ruleFields): array
    {
        $trackedFields = array_unique(array_merge(
            [
                'state',
                'status',
                'state_date',
                'gathering_id',
                'gathering_scheduled_activity_id',
                'bestowed_at',
                'close_reason',
            ],
            $ruleFields,
        ));

        $changes = [];
        foreach ($trackedFields as $field) {
            $previous = $this->serializeValue($before[$field] ?? null);
            $current = $this->serializeValue($bestowal->get($field));
            if ($previous === $current) {
                continue;
            }

            $changes[$field] = [
                'before' => $previous,
                'after' => $current,
            ];
        }

        return $changes;
    }

    /**
     * Normalize a datetime input to UTC storage format.
     *
     * @param mixed $value Datetime input.
     * @return \Cake\I18n\DateTime|null
     */
    private function normalizeDateTime(mixed $value): ?DateTime
    {
        if ($value === '' || $value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return new DateTime($value->format('Y-m-d H:i:s'));
        }

        $normalized = trim((string)$value);
        if ($normalized === '') {
            return null;
        }

        return new DateTime($normalized, new DateTimeZone('UTC'));
    }

    /**
     * Convert blank strings to null.
     *
     * @param mixed $value Input value.
     * @return string|null
     */
    private function normalizeOptionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string)$value);

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveNoteSubject(array $data, string $defaultNoteSubject): string
    {
        return $this->normalizeOptionalString($this->getOptionalValue($data, ['note_subject', 'noteSubject']))
            ?? $defaultNoteSubject;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $keys
     */
    private function hasAnyKey(array $data, array $keys): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $keys
     */
    private function getOptionalValue(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                return $data[$key];
            }
        }

        return null;
    }

    /**
     * Serialize values for structured transition output.
     *
     * @param mixed $value Value to serialize.
     * @return mixed
     */
    private function serializeValue(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        return $value;
    }
}
