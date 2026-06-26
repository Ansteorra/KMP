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
use Throwable;

/**
 * Records ad-hoc bestowals that are not backed by award recommendations.
 */
class AdHocBestowalService
{
    use LocatorAwareTrait;

    public const EVENT_NAME = 'Awards.BestowalCreated';

    private Table $bestowalsTable;
    private Table $membersTable;
    private BestowalCourtSlotService $courtSlotService;
    private BestowalTodoMaterializationService $todoMaterializationService;

    /**
     * @param \Cake\ORM\Table|null $bestowalsTable Optional injected bestowals table.
     * @param \Cake\ORM\Table|null $membersTable Optional injected members table.
     * @param \Awards\Services\BestowalCourtSlotService|null $courtSlotService Optional injected court slot service.
     * @param \Awards\Services\BestowalTodoMaterializationService|null $todoMaterializationService Optional to-do service.
     */
    public function __construct(
        ?Table $bestowalsTable = null,
        ?Table $membersTable = null,
        ?BestowalCourtSlotService $courtSlotService = null,
        ?BestowalTodoMaterializationService $todoMaterializationService = null,
    ) {
        $this->bestowalsTable = $bestowalsTable ?? $this->fetchTable('Awards.Bestowals');
        $this->membersTable = $membersTable ?? $this->fetchTable('Members');
        $this->courtSlotService = $courtSlotService ?? new BestowalCourtSlotService();
        $this->todoMaterializationService = $todoMaterializationService ?? new BestowalTodoMaterializationService();
    }

    /**
     * Create an ad-hoc bestowal in a single transaction.
     *
     * Expected keys in $data: member_id or member_public_id, award_id, state.
     * Optional keys: gathering_id, gathering_scheduled_activity_id, bestowed_at, notes, and court fields.
     *
     * @param array<string, mixed> $data Ad-hoc input payload.
     * @param int $actorId Current user ID.
     * @return array<string, mixed>
     */
    public function record(array $data, int $actorId): array
    {
        $memberId = $this->resolveMemberId($data);
        $awardId = $this->normalizeAwardId($data['awardId'] ?? $data['award_id'] ?? null);
        $gatheringId = $this->normalizeOptionalInt($data['gatheringId'] ?? $data['gathering_id'] ?? null);
        $courtSessionId = $data['gatheringScheduledActivityId']
            ?? $data['gathering_scheduled_activity_id']
            ?? null;
        $bestowedAt = $this->normalizeDateTime($data['bestowedAt'] ?? $data['bestowed_at'] ?? null);

        if ($memberId === null || $memberId <= 0) {
            return $this->failureResult('Member is required for ad-hoc bestowal entry.');
        }
        if ($awardId === null || $awardId <= 0) {
            return $this->failureResult('Award to Bestow is required for ad-hoc bestowal entry.');
        }

        try {
            $member = $this->membersTable->get($memberId, select: [
                'id',
                'sca_name',
            ]);
        } catch (Throwable $e) {
            Log::error('Ad-hoc bestowal failed loading member: ' . $e->getMessage());

            return $this->failureResult($e->getMessage());
        }

        try {
            return $this->bestowalsTable->getConnection()->transactional(
                function () use (
                    $member,
                    $awardId,
                    $gatheringId,
                    $courtSessionId,
                    $bestowedAt,
                    $data,
                    $actorId,
                ): array {
                    $bestowal = $this->bestowalsTable->newEmptyEntity();
                    $bestowal->member_id = (int)$member->id;
                    $bestowal->member_sca_name = (string)$member->sca_name;
                    $bestowal->award_id = $awardId;
                    $bestowal->gathering_id = $gatheringId;
                    $bestowal->source = Bestowal::SOURCE_AD_HOC;
                    $bestowal->stack_rank = (int)($data['stackRank'] ?? $data['stack_rank'] ?? 0);
                    $bestowal->lifecycle_status = $bestowedAt !== null
                        ? Bestowal::LIFECYCLE_GIVEN
                        : Bestowal::LIFECYCLE_OPEN;
                    $bestowal->bestowed_at = $bestowedAt;
                    $bestowal->call_into_court = $this->normalizeOptionalString(
                        $data['callIntoCourt'] ?? $data['call_into_court'] ?? null,
                    );
                    $bestowal->court_availability = $this->normalizeOptionalString(
                        $data['courtAvailability'] ?? $data['court_availability'] ?? null,
                    );
                    $bestowal->person_to_notify = $this->normalizeOptionalString(
                        $data['personToNotify'] ?? $data['person_to_notify'] ?? null,
                    );
                    $bestowal->specialty = $this->normalizeOptionalString(
                        $data['specialty'] ?? null,
                    );
                    $bestowal->noble_notes = $this->normalizeOptionalString(
                        $data['nobleNotes'] ?? $data['noble_notes'] ?? null,
                    );
                    $bestowal->herald_notes = $this->normalizeOptionalString(
                        $data['heraldNotes'] ?? $data['herald_notes'] ?? null,
                    );
                    $bestowal->close_reason = $this->normalizeOptionalString(
                        $data['closeReason'] ?? $data['close_reason'] ?? null,
                    );
                    $this->courtSlotService->applyCourtSessionSelection($bestowal, $courtSessionId);
                    $bestowal->created_by = $actorId;
                    $bestowal->modified_by = $actorId;

                    $savedBestowal = $this->bestowalsTable->saveOrFail($bestowal);

                    if ($savedBestowal instanceof Bestowal) {
                        $this->materializeTodos($savedBestowal);
                    }

                    return [
                        'success' => true,
                        'data' => [
                            'bestowalId' => (int)$savedBestowal->id,
                            'recommendationIds' => [],
                            'eventName' => self::EVENT_NAME,
                            'eventPayload' => [
                                'bestowalId' => (int)$savedBestowal->id,
                                'recommendationIds' => [],
                                'primaryRecommendationId' => null,
                                'memberId' => (int)$member->id,
                                'memberScaName' => (string)$member->sca_name,
                                'gatheringId' => $gatheringId,
                                'lifecycleStatus' => (string)($savedBestowal->lifecycle_status
                                    ?? Bestowal::LIFECYCLE_OPEN),
                                'source' => Bestowal::SOURCE_AD_HOC,
                                'bestowedAt' => $bestowedAt?->format(DATE_ATOM),
                            ],
                        ],
                    ];
                },
            );
        } catch (Throwable $e) {
            Log::error('Ad-hoc bestowal failed: ' . $e->getMessage());

            return $this->failureResult($e->getMessage());
        }
    }

    /**
     * Materialize the bestowal's to-do checklist from its award's template.
     *
     * Failures are logged but never abort the ad-hoc bestowal: the checklist is
     * supplemental and can be re-materialized idempotently later.
     *
     * @param \Awards\Model\Entity\Bestowal $bestowal Saved bestowal entity.
     * @return void
     */
    private function materializeTodos(Bestowal $bestowal): void
    {
        try {
            $result = $this->todoMaterializationService->materializeForBestowal($bestowal);
            if (!$result->success) {
                Log::warning('Ad-hoc bestowal to-do materialization reported failure: ' . (string)$result->reason);
            }
        } catch (Throwable $e) {
            Log::error('Ad-hoc bestowal to-do materialization failed: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $data Ad-hoc input payload.
     * @return int|null
     */
    private function resolveMemberId(array $data): ?int
    {
        $memberId = $this->normalizeOptionalInt($data['memberId'] ?? $data['member_id'] ?? null);
        if ($memberId !== null && $memberId > 0) {
            return $memberId;
        }

        $publicId = trim((string)($data['memberPublicId'] ?? $data['member_public_id'] ?? ''));
        if ($publicId === '') {
            return null;
        }

        $member = $this->membersTable->find()
            ->select(['id'])
            ->where(['public_id' => $publicId])
            ->first();

        return $member !== null ? (int)$member->id : null;
    }

    /**
     * @param mixed $awardId Raw award ID input.
     * @return int|null
     */
    private function normalizeAwardId(mixed $awardId): ?int
    {
        if (is_array($awardId)) {
            $awardId = reset($awardId);
        }

        return $this->normalizeOptionalInt($awardId);
    }

    /**
     * @param mixed $value Raw integer input.
     * @return int|null
     */
    private function normalizeOptionalInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int)$value;
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
     * @return array<string, mixed>
     */
    private function failureResult(string $error): array
    {
        return [
            'success' => false,
            'error' => $error,
            'data' => [
                'bestowalId' => null,
                'recommendationIds' => [],
                'eventName' => null,
                'eventPayload' => null,
            ],
        ];
    }
}
