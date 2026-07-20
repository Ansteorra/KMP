<?php
declare(strict_types=1);

namespace Awards\Services;

use Awards\Model\Entity\Recommendation;
use Awards\Model\Table\RecommendationsTable;
use Cake\I18n\DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use RuntimeException;

trait RecommendationMutationSupportTrait
{
    /**
     * @param array<string, mixed> $data
     * @return array{success: bool, data: array<string, mixed>, errorCode: ?string, message: ?string}
     */
    private function resolveMemberPublicId(RecommendationsTable $recommendationsTable, array $data): array
    {
        $memberPublicId = $data['member_public_id'] ?? null;
        if ($memberPublicId === null || $memberPublicId === '') {
            unset($data['member_public_id']);

            return [
                'success' => true,
                'data' => $data,
                'errorCode' => null,
                'message' => null,
            ];
        }

        $member = $recommendationsTable->Members
            ->find('byPublicId', [(string)$memberPublicId])
            ->first();

        if ($member === null) {
            return [
                'success' => false,
                'data' => $data,
                'errorCode' => 'member_public_id_not_found',
                'message' => 'Member with provided public_id not found.',
            ];
        }

        $data['member_id'] = (int)$member->id;
        unset($data['member_public_id']);

        return [
            'success' => true,
            'data' => $data,
            'errorCode' => null,
            'message' => null,
        ];
    }

    /**
     * Apply the legacy initial status/state pair used by recommendation submission.
     *
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation being initialized.
     * @return void
     */
    private function initializeSubmissionState(Recommendation $recommendation): void
    {
        $statuses = Recommendation::getStatuses();
        $firstStatus = array_key_first($statuses);
        $firstState = $firstStatus !== null ? ($statuses[$firstStatus][0] ?? null) : null;

        if ($firstStatus === null || $firstState === null) {
            throw new RuntimeException('Recommendation states are not configured.');
        }

        $recommendation->status = $firstStatus;
        $recommendation->state = $firstState;
        $recommendation->state_date = DateTime::now();
    }

    /**
     * Convert the placeholder specialty option into a null database value.
     *
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation being normalized.
     * @return void
     */
    private function normalizeSpecialty(Recommendation $recommendation): void
    {
        if ($recommendation->specialty === 'No specialties available') {
            $recommendation->specialty = null;
        }
    }

    /**
     * Hydrate branch and court-preference defaults from the selected member.
     *
     * @param \Awards\Model\Table\RecommendationsTable $recommendationsTable Recommendations table.
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation being normalized.
     * @return void
     */
    private function hydrateMemberDefaults(
        RecommendationsTable $recommendationsTable,
        Recommendation $recommendation,
    ): void {
        if ($recommendation->member_id === null) {
            return;
        }

        $member = $recommendationsTable->Members->get(
            $recommendation->member_id,
            select: ['branch_id', 'additional_info'],
        );

        $recommendation->branch_id = $member->branch_id;

        $additionalInfo = is_array($member->additional_info) ? $member->additional_info : [];
        if (isset($additionalInfo['CallIntoCourt'])) {
            $recommendation->call_into_court = $additionalInfo['CallIntoCourt'];
        }
        if (isset($additionalInfo['CourtAvailability'])) {
            $recommendation->court_availability = $additionalInfo['CourtAvailability'];
        }
        if (isset($additionalInfo['PersonToGiveNoticeTo'])) {
            $recommendation->person_to_notify = $additionalInfo['PersonToGiveNoticeTo'];
        }
    }

    /**
     * Apply legacy fallback defaults for court-preference fields.
     *
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation being normalized.
     * @return void
     */
    private function applyCourtPreferenceDefaults(Recommendation $recommendation): void
    {
        $recommendation->call_into_court = $recommendation->call_into_court ?? 'Not Set';
        $recommendation->court_availability = $recommendation->court_availability ?? 'Not Set';
        $recommendation->person_to_notify = $recommendation->person_to_notify ?? '';
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function buildOutputData(
        RecommendationsTable $recommendationsTable,
        Recommendation $recommendation,
        array $extra = [],
    ): array {
        $awardName = '';
        if ($recommendation->award_id !== null) {
            $award = $recommendationsTable->Awards->find()
                ->select(['id', 'name'])
                ->where(['id' => $recommendation->award_id])
                ->first();
            $awardName = $award?->name ?? '';
        }

        $output = [
            'recommendationId' => (int)$recommendation->id,
            'awardId' => $recommendation->award_id !== null ? (int)$recommendation->award_id : null,
            'memberId' => $recommendation->member_id !== null ? (int)$recommendation->member_id : null,
            'requesterId' => $recommendation->requester_id !== null ? (int)$recommendation->requester_id : null,
            'branchId' => $recommendation->branch_id !== null ? (int)$recommendation->branch_id : null,
            'status' => (string)($recommendation->status ?? ''),
            'state' => (string)($recommendation->state ?? ''),
            'memberScaName' => (string)($recommendation->member_sca_name ?? ''),
            'requesterScaName' => (string)($recommendation->requester_sca_name ?? ''),
            'awardName' => $awardName,
            'reason' => (string)($recommendation->reason ?? ''),
            'contactEmail' => (string)($recommendation->contact_email ?? ''),
            'contactNumber' => (string)($recommendation->contact_number ?? ''),
            'specialty' => $recommendation->specialty,
            'gatheringId' => $recommendation->gathering_id !== null ? (int)$recommendation->gathering_id : null,
            'gatheringIds' => $this->extractGatheringIds($recommendation),
            'given' => $this->formatGivenDate($recommendation->given),
            'closeReason' => $recommendation->close_reason,
            'notFound' => (bool)($recommendation->not_found ?? false),
        ];

        return array_merge($output, $extra);
    }

    /**
     * @return array<int>
     */
    private function extractGatheringIds(Recommendation $recommendation): array
    {
        $ids = [];
        foreach ($recommendation->gatherings ?? [] as $gathering) {
            if ($gathering->id === null) {
                continue;
            }
            $ids[] = (int)$gathering->id;
        }

        sort($ids);

        return array_values(array_unique($ids));
    }

    /**
     * Format stored "given" timestamps back to a stable UTC date-only value.
     *
     * @param \DateTimeInterface|null $given Stored given timestamp.
     * @return string|null
     */
    private function formatGivenDate(?DateTimeInterface $given): ?string
    {
        if ($given === null) {
            return null;
        }

        if ($given instanceof DateTimeImmutable) {
            return $given->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
        }

        $copy = clone $given;
        $copy->setTimezone(new DateTimeZone('UTC'));

        return $copy->format('Y-m-d');
    }

    /**
     * Normalize checkbox-style request values to booleans.
     *
     * @param mixed $value Request value to normalize.
     * @return bool
     */
    private function normalizeCheckboxValue(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'on'], true);
    }
}
