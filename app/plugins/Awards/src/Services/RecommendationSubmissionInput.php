<?php
declare(strict_types=1);

namespace Awards\Services;

use InvalidArgumentException;

/** Allowlisted creation input, shared by HTTP and workflow submission boundaries. */
final class RecommendationSubmissionInput
{
    private const FIELDS = [
        'award_id', 'member_id', 'member_public_id', 'branch_id', 'requester_sca_name',
        'member_sca_name', 'contact_email', 'contact_number', 'reason', 'specialty',
        'call_into_court', 'court_availability', 'person_to_notify', 'not_found',
    ];

    /** @return array<string, mixed> */
    public static function normalize(array $input, bool $http = false): array
    {
        $data = array_intersect_key($input, array_flip(self::FIELDS));
        if ($http) {
            unset($data['member_id']);
        }
        foreach ($data as $value) {
            if ($value !== null && !is_scalar($value)) {
                throw new InvalidArgumentException('Submission fields must be scalar values.');
            }
        }
        $gatherings = $input['gatherings'] ?? [];
        if (!is_array($gatherings) || array_diff(array_keys($gatherings), ['_ids']) !== []) {
            throw new InvalidArgumentException('Gatherings must contain only existing IDs.');
        }
        $ids = $gatherings['_ids'] ?? [];
        if ($ids === '') {
            $ids = [];
        }
        if (!is_array($ids) || count($ids) > 100) {
            throw new InvalidArgumentException('Invalid gathering selection.');
        }
        $normalized = [];
        foreach ($ids as $id) {
            if ((!is_int($id) && !is_string($id)) || !ctype_digit((string)$id) || (int)$id <= 0) {
                throw new InvalidArgumentException('Invalid gathering selection.');
            }
            $normalized[] = (int)$id;
        }
        $data['gatherings'] = ['_ids' => array_values(array_unique($normalized))];

        return $data;
    }
}
