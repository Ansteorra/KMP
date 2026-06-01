<?php
declare(strict_types=1);

namespace Awards\Services;

use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\Recommendation;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Table;
use DateTimeInterface;
use DateTimeZone;
use Throwable;

/**
 * Records ad-hoc bestowals by creating recommendations and a linked bestowal together.
 */
class AdHocBestowalService
{
    use BestowalNotesSupportTrait;
    use LocatorAwareTrait;

    public const EVENT_NAME = 'Awards.BestowalCreated';

    private Table $bestowalsTable;
    private Table $recommendationsTable;
    private Table $bestowalRecommendationsTable;
    private BestowalStateLogService $bestowalStateLogService;
    private RecommendationStateLogService $recommendationStateLogService;

    /**
     * @param \Cake\ORM\Table|null $bestowalsTable Optional injected bestowals table.
     * @param \Cake\ORM\Table|null $recommendationsTable Optional injected recommendations table.
     * @param \Cake\ORM\Table|null $bestowalRecommendationsTable Optional injected join table.
     * @param \Awards\Services\BestowalStateLogService|null $bestowalStateLogService Optional injected bestowal state-log service.
     * @param \Awards\Services\RecommendationStateLogService|null $recommendationStateLogService Optional injected rec state-log service.
     */
    public function __construct(
        ?Table $bestowalsTable = null,
        ?Table $recommendationsTable = null,
        ?Table $bestowalRecommendationsTable = null,
        ?BestowalStateLogService $bestowalStateLogService = null,
        ?RecommendationStateLogService $recommendationStateLogService = null,
    ) {
        $this->bestowalsTable = $bestowalsTable ?? $this->fetchTable('Awards.Bestowals');
        $this->recommendationsTable = $recommendationsTable ?? $this->fetchTable('Awards.Recommendations');
        $this->bestowalRecommendationsTable = $bestowalRecommendationsTable
            ?? $this->fetchTable('Awards.BestowalRecommendations');
        $this->bestowalStateLogService = $bestowalStateLogService ?? new BestowalStateLogService();
        $this->recommendationStateLogService = $recommendationStateLogService
            ?? new RecommendationStateLogService();
    }

    /**
     * Create ad-hoc recommendation(s) and a linked bestowal in a single transaction.
     *
     * Expected keys in $data: memberId, awardIds, gatheringId, bestowedAt.
     * Optional keys: reason, nobleNotes, heraldNotes, callIntoCourt, courtAvailability, personToNotify.
     *
     * @param array<string, mixed> $data Ad-hoc input payload.
     * @param int $actorId Current user ID.
     * @return array<string, mixed>
     */
    public function record(array $data, int $actorId): array
    {
        $memberId = (int)($data['memberId'] ?? $data['member_id'] ?? 0);
        $awardIds = $this->normalizeAwardIds($data['awardIds'] ?? $data['award_ids'] ?? []);
        $gatheringId = (int)($data['gatheringId'] ?? $data['gathering_id'] ?? 0);
        $bestowedAt = $this->normalizeDateTime($data['bestowedAt'] ?? $data['bestowed_at'] ?? null);

        if ($memberId <= 0) {
            return $this->failureResult('Member is required for ad-hoc bestowal entry.');
        }
        if ($awardIds === []) {
            return $this->failureResult('At least one award is required for ad-hoc bestowal entry.');
        }
        if ($gatheringId <= 0) {
            return $this->failureResult('Gathering is required for ad-hoc bestowal entry.');
        }
        if ($bestowedAt === null) {
            return $this->failureResult('Bestowed datetime is required for ad-hoc bestowal entry.');
        }

        try {
            $member = $this->recommendationsTable->Members->get($memberId, select: [
                'id',
                'sca_name',
                'branch_id',
                'additional_info',
            ]);
            $actor = $this->recommendationsTable->Members->get($actorId, select: [
                'id',
                'sca_name',
                'email_address',
                'phone_number',
            ]);
        } catch (Throwable $e) {
            Log::error('Ad-hoc bestowal failed loading members: ' . $e->getMessage());

            return $this->failureResult($e->getMessage());
        }

        $reason = trim((string)($data['reason'] ?? 'Ad-hoc backfill entry'));
        if ($reason === '') {
            $reason = 'Ad-hoc backfill entry';
        }

        $callIntoCourt = $this->normalizeOptionalString(
            $data['callIntoCourt'] ?? $data['call_into_court'] ?? null,
        );
        $courtAvailability = $this->normalizeOptionalString(
            $data['courtAvailability'] ?? $data['court_availability'] ?? null,
        );
        $personToNotify = $this->normalizeOptionalString(
            $data['personToNotify'] ?? $data['person_to_notify'] ?? null,
        );

        try {
            return $this->bestowalsTable->getConnection()->transactional(
                function () use (
                    $member,
                    $actor,
                    $awardIds,
                    $gatheringId,
                    $bestowedAt,
                    $reason,
                    $callIntoCourt,
                    $courtAvailability,
                    $personToNotify,
                    $data,
                    $actorId,
                ): array {
                    $recommendations = [];
                    foreach ($awardIds as $awardId) {
                        $recommendations[] = $this->createGivenRecommendation(
                            $member,
                            $actor,
                            $awardId,
                            $gatheringId,
                            $bestowedAt,
                            $reason,
                            $callIntoCourt,
                            $courtAvailability,
                            $personToNotify,
                            $actorId,
                        );
                    }

                    $head = $recommendations[0];
                    $bestowal = $this->bestowalsTable->newEmptyEntity();
                    $bestowal->member_id = (int)$member->id;
                    $bestowal->primary_recommendation_id = (int)$head->id;
                    $bestowal->award_id = (int)$head->award_id;
                    $bestowal->gathering_id = $gatheringId;
                    $bestowal->source = Bestowal::SOURCE_AD_HOC;
                    $bestowal->state = 'Given';
                    $bestowal->bestowed_at = $bestowedAt;
                    $bestowal->call_into_court = $callIntoCourt ?? $head->call_into_court;
                    $bestowal->court_availability = $courtAvailability ?? $head->court_availability;
                    $bestowal->person_to_notify = $personToNotify ?? $head->person_to_notify;
                    $bestowal->noble_notes = $this->normalizeOptionalString(
                        $data['nobleNotes'] ?? $data['noble_notes'] ?? null,
                    ) ?? $this->buildNobleNotes($recommendations);
                    $bestowal->herald_notes = $this->normalizeOptionalString(
                        $data['heraldNotes'] ?? $data['herald_notes'] ?? null,
                    ) ?? $this->buildHeraldNotes($recommendations, (string)$member->sca_name);
                    $bestowal->created_by = $actorId;
                    $bestowal->modified_by = $actorId;

                    $savedBestowal = $this->bestowalsTable->saveOrFail($bestowal);
                    $this->bestowalStateLogService->logStateTransition(
                        (int)$savedBestowal->id,
                        'New',
                        (string)$savedBestowal->state,
                        'New',
                        $savedBestowal->status !== null ? (string)$savedBestowal->status : null,
                        $actorId,
                    );

                    $recommendationIds = [];
                    foreach ($recommendations as $recommendation) {
                        $recommendationIds[] = (int)$recommendation->id;

                        $join = $this->bestowalRecommendationsTable->newEmptyEntity();
                        $join->bestowal_id = (int)$savedBestowal->id;
                        $join->recommendation_id = (int)$recommendation->id;
                        $this->bestowalRecommendationsTable->saveOrFail($join);

                        $recommendation->bestowal_id = (int)$savedBestowal->id;
                        $recommendation->modified_by = $actorId;
                        $this->recommendationsTable->saveOrFail(
                            $recommendation,
                            ['systemSync' => true],
                        );
                    }

                    sort($recommendationIds);

                    return [
                        'success' => true,
                        'data' => [
                            'bestowalId' => (int)$savedBestowal->id,
                            'recommendationIds' => $recommendationIds,
                            'eventName' => self::EVENT_NAME,
                            'eventPayload' => [
                                'bestowalId' => (int)$savedBestowal->id,
                                'recommendationIds' => $recommendationIds,
                                'primaryRecommendationId' => (int)$head->id,
                                'memberId' => (int)$member->id,
                                'memberScaName' => (string)$member->sca_name,
                                'gatheringId' => $gatheringId,
                                'status' => (string)$savedBestowal->status,
                                'state' => (string)$savedBestowal->state,
                                'source' => Bestowal::SOURCE_AD_HOC,
                                'bestowedAt' => $bestowedAt->format(DateTimeInterface::ATOM),
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
     * @param object $member Recipient member entity.
     * @param object $actor Actor member entity.
     */
    private function createGivenRecommendation(
        object $member,
        object $actor,
        int $awardId,
        int $gatheringId,
        DateTime $bestowedAt,
        string $reason,
        ?string $callIntoCourt,
        ?string $courtAvailability,
        ?string $personToNotify,
        int $actorId,
    ): Recommendation {
        $award = $this->recommendationsTable->Awards->get($awardId, contain: ['Levels']);

        $recommendation = $this->recommendationsTable->newEmptyEntity();
        $recommendation->member_id = (int)$member->id;
        $recommendation->member_sca_name = (string)$member->sca_name;
        $recommendation->branch_id = $member->branch_id ?? null;
        $recommendation->award_id = $awardId;
        $recommendation->requester_id = (int)$actor->id;
        $recommendation->requester_sca_name = (string)$actor->sca_name;
        $recommendation->contact_email = (string)($actor->email_address ?? '');
        $recommendation->contact_number = (string)($actor->phone_number ?? '');
        $recommendation->reason = $reason;
        $recommendation->gathering_id = $gatheringId;
        $recommendation->call_into_court = $callIntoCourt ?? 'Not Set';
        $recommendation->court_availability = $courtAvailability ?? 'Not Set';
        $recommendation->person_to_notify = $personToNotify ?? '';
        $recommendation->state = 'Given';
        $recommendation->given = new DateTime(
            $bestowedAt->format('Y-m-d') . ' 00:00:00',
            new DateTimeZone('UTC'),
        );
        $recommendation->created_by = $actorId;
        $recommendation->modified_by = $actorId;

        $saved = $this->recommendationsTable->saveOrFail($recommendation);
        $this->recommendationStateLogService->logStateTransition(
            (int)$saved->id,
            'New',
            (string)$saved->state,
            'New',
            $saved->status !== null ? (string)$saved->status : null,
            $actorId,
        );

        $saved->award = $award;

        return $saved;
    }

    /**
     * @param mixed $awardIds Raw award ID input.
     * @return array<int, int>
     */
    private function normalizeAwardIds(mixed $awardIds): array
    {
        if (!is_array($awardIds)) {
            return [];
        }

        $normalized = [];
        foreach ($awardIds as $awardId) {
            if ($awardId === null || $awardId === '') {
                continue;
            }
            $normalized[] = (int)$awardId;
        }

        return array_values(array_unique(array_filter($normalized, fn(int $id): bool => $id > 0)));
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
