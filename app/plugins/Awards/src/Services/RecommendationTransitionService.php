<?php
declare(strict_types=1);

namespace Awards\Services;

use Awards\Model\Entity\Recommendation;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Table;
use DateTimeInterface;
use DateTimeZone;
use RuntimeException;
use Throwable;

/**
 * Applies recommendation state transitions for single and bulk operations.
 *
 * Uses the Recommendation entity setters/callbacks so transitions consistently
 * update status, state_date, field-set rules, gathering rules, notes, and
 * explicit workflow-owned side effects.
 */
class RecommendationTransitionService
{
    private const SINGLE_NOTE_SUBJECT = 'Recommendation Updated';
    private const BULK_NOTE_SUBJECT = 'Recommendation Bulk Updated';

    private RecommendationGroupingService $groupingService;
    private RecommendationStateLogService $stateLogService;

    public function __construct(
        ?RecommendationGroupingService $groupingService = null,
        ?RecommendationStateLogService $stateLogService = null,
    ) {
        $this->stateLogService = $stateLogService ?? new RecommendationStateLogService();
        $this->groupingService = $groupingService ?? new RecommendationGroupingService(
            stateLogService: $this->stateLogService,
        );
    }

    /**
     * Transition a single recommendation and return structured transition data.
     *
     * @param \Cake\ORM\Table $recommendationsTable Recommendations table instance.
     * @param string|int $recommendationId Recommendation ID to transition.
     * @param array<string, mixed> $data Transition input.
     * @param int $actorId Current user ID.
     * @return array<string, mixed>
     */
    public function transition(
        Table $recommendationsTable,
        int|string $recommendationId,
        array $data,
        int $actorId,
    ): array {
        $result = $this->runTransitions(
            $recommendationsTable,
            [(string)$recommendationId],
            $data,
            $actorId,
            self::SINGLE_NOTE_SUBJECT,
        );

        if ($result['success'] ?? false) {
            $result['data']['recommendationId'] = (int)$recommendationId;
            $result['data']['result'] = $result['data']['results'][0] ?? null;
        }

        return $result;
    }

    /**
     * Transition multiple recommendations and return structured transition data.
     *
     * @param \Cake\ORM\Table $recommendationsTable Recommendations table instance.
     * @param array<string, mixed> $data Transition input, including ids/recommendationIds.
     * @param int $actorId Current user ID.
     * @return array<string, mixed>
     */
    public function transitionMany(
        Table $recommendationsTable,
        array $data,
        int $actorId,
    ): array {
        $ids = $this->extractIds($data);

        return $this->runTransitions(
            $recommendationsTable,
            $ids,
            $data,
            $actorId,
            self::BULK_NOTE_SUBJECT,
        );
    }

    /**
     * Execute one or more transitions inside a transaction.
     *
     * @param \Cake\ORM\Table $recommendationsTable Recommendations table instance.
     * @param array<int, string> $ids Recommendation IDs.
     * @param array<string, mixed> $data Transition input.
     * @param int $actorId Current user ID.
     * @param string $defaultNoteSubject Default subject for created notes.
     * @return array<string, mixed>
     */
    private function runTransitions(
        Table $recommendationsTable,
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
                'error' => 'At least one recommendation ID is required',
                'data' => [
                    'processedCount' => 0,
                    'results' => [],
                ],
            ];
        }

        try {
            return $recommendationsTable->getConnection()->transactional(
                function () use (
                    $recommendationsTable,
                    $ids,
                    $data,
                    $actorId,
                    $targetState,
                    $defaultNoteSubject,
                ): array {
                    $recommendations = $recommendationsTable->find()
                        ->where(['id IN' => $ids])
                        ->all()
                        ->indexBy('id')
                        ->toArray();

                    $missingIds = array_values(
                        array_diff($ids, array_map('strval', array_keys($recommendations))),
                    );
                    if ($missingIds !== []) {
                        throw new RuntimeException(
                            'Recommendations not found: ' . implode(', ', $missingIds),
                        );
                    }

                    $results = [];
                    foreach ($ids as $id) {
                        $recommendation = $recommendations[(int)$id];
                        if ($recommendation->isLockedByBestowal()) {
                            throw new RuntimeException(
                                'Recommendation #' . $id . ' is linked to a bestowal and cannot be bulk edited.',
                            );
                        }

                        $results[] = $this->applyTransition(
                            $recommendationsTable,
                            $recommendation,
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
                            'recommendationIds' => array_map('intval', $ids),
                            'results' => $results,
                        ],
                    ];
                },
            );
        } catch (Throwable $e) {
            Log::error('Recommendation transition failed: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => [
                    'processedCount' => 0,
                    'targetState' => $targetState,
                    'recommendationIds' => array_map('intval', $ids),
                    'results' => [],
                ],
            ];
        }
    }

    /**
     * Apply the transition semantics to one recommendation.
     *
     * @param \Cake\ORM\Table $recommendationsTable Recommendations table instance.
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation to update.
     * @param string $targetState Target state name.
     * @param array<string, mixed> $data Transition input.
     * @param int $actorId Current user ID.
     * @param string $defaultNoteSubject Default subject for created notes.
     * @return array<string, mixed>
     */
    private function applyTransition(
        Table $recommendationsTable,
        Recommendation $recommendation,
        string $targetState,
        array $data,
        int $actorId,
        string $defaultNoteSubject,
    ): array {
        $appliedSetRules = Recommendation::getStateRules()[$targetState]['Set'] ?? [];
        $before = [
            'state' => $recommendation->state,
            'status' => $recommendation->status,
            'state_date' => $recommendation->state_date,
            'gathering_id' => $recommendation->gathering_id,
            'given' => $recommendation->given,
            'close_reason' => $recommendation->close_reason,
        ];

        $recommendation->state = $targetState;
        $recommendation->modified_by = $actorId;

        if (
            Recommendation::supportsGatheringAssignmentForState($targetState)
            && $this->hasAnyKey($data, ['gathering_id', 'gatheringId'])
        ) {
            $gatheringId = $this->getOptionalValue($data, ['gathering_id', 'gatheringId']);
            if ($gatheringId !== null) {
                $recommendation->gathering_id = $gatheringId === '' ? null : (int)$gatheringId;
            }
        }

        if ($this->hasAnyKey($data, ['given'])) {
            $given = $data['given'] ?? null;
            if ($given !== null) {
                $recommendation->given = $this->normalizeGiven($given);
            }
        }

        if (
            !array_key_exists('close_reason', $appliedSetRules)
            && $this->hasAnyKey($data, ['close_reason', 'closeReason'])
        ) {
            $closeReason = $this->getOptionalValue($data, ['close_reason', 'closeReason']);
            if ($closeReason !== null) {
                $recommendation->close_reason = $this->normalizeOptionalString($closeReason);
            }
        }

        $saved = $recommendationsTable->saveOrFail($recommendation);
        $this->stateLogService->logStateTransition(
            (int)$saved->id,
            (string)$before['state'],
            (string)$saved->state,
            (string)$before['status'],
            $saved->status !== null ? (string)$saved->status : null,
            $actorId,
        );

        if ((string)$before['status'] !== (string)$saved->status && $saved->recommendation_group_id === null) {
            $this->groupingService->syncLinkedChildrenState($saved, $actorId);
        }

        $noteId = null;
        $noteCreated = false;

        $note = $this->normalizeOptionalString($this->getOptionalValue($data, ['note']));
        if ($note !== null) {
            $notesTable = $recommendationsTable->Notes;
            $newNote = $notesTable->newEmptyEntity();
            $newNote->entity_id = $saved->id;
            $newNote->subject = $this->resolveNoteSubject($data, $defaultNoteSubject);
            $newNote->entity_type = 'Awards.Recommendations';
            $newNote->body = $note;
            $newNote->author_id = $actorId;
            $notesTable->saveOrFail($newNote);
            $noteCreated = true;
            $noteId = (int)$newNote->id;
        }

        return [
            'recommendationId' => (int)$saved->id,
            'previousState' => (string)$before['state'],
            'newState' => (string)$saved->state,
            'previousStatus' => (string)$before['status'],
            'newStatus' => (string)$saved->status,
            'stateDate' => $this->serializeValue($saved->state_date),
            'gatheringId' => $saved->gathering_id === null ? null : (int)$saved->gathering_id,
            'given' => $this->serializeValue($saved->given),
            'closeReason' => $saved->close_reason,
            'appliedSetRules' => $appliedSetRules,
            'noteCreated' => $noteCreated,
            'noteId' => $noteId,
            'changes' => $this->buildChangeSet($before, $saved, array_keys($appliedSetRules)),
        ];
    }

    /**
     * Extract target state from supported input keys.
     *
     * @param array<string, mixed> $data Transition input.
     * @return string
     */
    private function extractTargetState(array $data): string
    {
        $state = $this->getOptionalValue($data, ['newState', 'targetState', 'state', 'toState']);

        return is_string($state) ? trim($state) : '';
    }

    /**
     * Extract and normalize recommendation IDs from supported input keys.
     *
     * @param array<string, mixed> $data Transition input.
     * @return array<int, string>
     */
    private function extractIds(array $data): array
    {
        $ids = $this->getOptionalValue($data, ['ids', 'recommendationIds']);
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
     * Build a structured before/after diff for transition-relevant fields.
     *
     * @param array<string, mixed> $before Previous values.
     * @param \Awards\Model\Entity\Recommendation $recommendation Saved recommendation.
     * @param array<int, string> $ruleFields Fields affected by Set rules.
     * @return array<string, array{before: mixed, after: mixed}>
     */
    private function buildChangeSet(array $before, Recommendation $recommendation, array $ruleFields): array
    {
        $trackedFields = array_unique(array_merge(
            ['state', 'status', 'state_date', 'gathering_id', 'given', 'close_reason'],
            $ruleFields,
        ));

        $changes = [];
        foreach ($trackedFields as $field) {
            $previous = $this->serializeValue($before[$field] ?? null);
            $current = $this->serializeValue($recommendation->get($field));
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
     * Normalize the given date to midnight UTC for storage.
     *
     * @param mixed $given Given date input.
     * @return \Cake\I18n\DateTime|null
     */
    private function normalizeGiven(mixed $given): ?DateTime
    {
        if ($given === '' || $given === null) {
            return null;
        }

        if ($given instanceof DateTimeInterface) {
            $dateString = $given->format('Y-m-d');
        } else {
            $dateString = trim((string)$given);
        }

        if ($dateString === '') {
            return null;
        }

        return new DateTime($dateString . ' 00:00:00', new DateTimeZone('UTC'));
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
     * Resolve the note subject from supported input keys.
     *
     * @param array<string, mixed> $data Transition input.
     * @param string $defaultNoteSubject Default subject.
     * @return string
     */
    private function resolveNoteSubject(array $data, string $defaultNoteSubject): string
    {
        return $this->normalizeOptionalString($this->getOptionalValue($data, ['note_subject', 'noteSubject']))
            ?? $defaultNoteSubject;
    }

    /**
     * Check whether any of the provided keys exist in the data payload.
     *
     * @param array<string, mixed> $data Transition input.
     * @param array<int, string> $keys Candidate keys.
     * @return bool
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
     * Return the first matching value for a set of supported keys.
     *
     * @param array<string, mixed> $data Transition input.
     * @param array<int, string> $keys Candidate keys.
     * @return mixed
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
