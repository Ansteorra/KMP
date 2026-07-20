<?php
declare(strict_types=1);

namespace App\Services\Backup;

/**
 * Upgrades pre-branch backup payloads to the feature/workflow-engine object model.
 */
class MainToWorkflowEngineBranchBackupMigrator implements BackupPayloadMigratorInterface
{
    private const ELIGIBLE_RECOMMENDATION_STATES = [
        'Need to Schedule',
        'Scheduled',
        'Given',
        'Announced Not Given',
    ];

    private const LEGACY_EMAIL_TEMPLATE_SLUGS = [
        'App\Mailer\KMPMailer::resetPassword' => [
            'slug' => 'password-reset',
            'name' => 'Password Reset',
            'description' => 'Sent when a member requests a password reset link.',
        ],
        'App\Mailer\KMPMailer::mobileCard' => [
            'slug' => 'mobile-card-url',
            'name' => 'Mobile Card URL',
            'description' => 'Sent when a member requests their mobile card link.',
        ],
        'App\Mailer\KMPMailer::newRegistration' => [
            'slug' => 'member-registration-welcome',
            'name' => 'Member Registration Welcome',
            'description' => 'Welcome email sent to a newly registered adult member with a password-reset link.',
        ],
        'App\Mailer\KMPMailer::notifySecretaryOfNewMember' => [
            'slug' => 'member-registration-secretary',
            'name' => 'New Member Secretary Notification',
            'description' => 'Sent to the kingdom secretary when a new adult member registers.',
        ],
        'App\Mailer\KMPMailer::notifySecretaryOfNewMinorMember' => [
            'slug' => 'member-registration-secretary-minor',
            'name' => 'New Minor Member Secretary Notification',
            'description' => 'Sent to the kingdom secretary when a new minor member registers.',
        ],
        'App\Mailer\KMPMailer::notifyOfWarrant' => [
            'slug' => 'warrant-issued',
            'name' => 'Warrant Issued',
            'description' => 'Sent when a warrant roster workflow activates a warrant for a member.',
        ],
        'App\Mailer\KMPMailer::sendFromTemplate' => [
            'slug' => 'award-recommendation-submitted',
            'name' => 'Award Recommendation Submitted',
            'description' => 'Sent to the Crown when a new award recommendation is submitted.',
        ],
        'Officers\Mailer\OfficersMailer::notifyOfHire' => [
            'slug' => 'officer-hire-notification',
            'name' => 'Officer Hire Notification',
            'description' => 'Sent to a member when they are appointed to an office.',
        ],
        'Officers\Mailer\OfficersMailer::notifyOfRelease' => [
            'slug' => 'officer-release-notification',
            'name' => 'Officer Release Notification',
            'description' => 'Sent to a member when they are released from an office.',
        ],
        'Activities\Mailer\ActivitiesMailer::notifyApprover' => [
            'slug' => 'authorization-approval-request',
            'name' => 'Authorization Approval Request',
            'description' => 'Sent to the next approver when an authorization request needs review.',
        ],
        'Activities\Mailer\ActivitiesMailer::notifyRequester' => [
            'slug' => 'authorization-request-update',
            'name' => 'Authorization Request Update',
            'description' => 'Sent to the requester when an authorization request status changes.',
        ],
        'Activities\Mailer\ActivitiesMailer::notifyApproverOfRetraction' => [
            'slug' => 'authorization-request-retracted',
            'name' => 'Authorization Request Retracted',
            'description' => 'Sent to an approver when a pending authorization request is retracted.',
        ],
    ];

    /**
     * Return the stable migrator identifier for restore reporting.
     */
    public function name(): string
    {
        return 'main-to-workflow-engine-20260622';
    }

    /**
     * @param array<string, mixed> $payload Decoded backup payload.
     */
    public function shouldRun(array $payload): bool
    {
        return (
            isset($payload['tables']['awards_recommendations'])
            && is_array($payload['tables']['awards_recommendations'])
        ) || (
            isset($payload['tables']['email_templates'])
            && is_array($payload['tables']['email_templates'])
            && $this->hasLegacyEmailTemplateRows($payload['tables']['email_templates'])
        );
    }

    /**
     * @param array<string, mixed> $payload Decoded backup payload.
     * @return array{payload: array<string, mixed>, stats: array<string, int>}
     */
    public function migrate(array $payload): array
    {
        $stats = [
            'bestowals_created' => 0,
            'recommendations_linked_to_bestowals' => 0,
            'bestowal_recommendation_links_created' => 0,
            'recommendations_skipped_missing_required_data' => 0,
            'email_template_slugs_backfilled' => 0,
            'email_template_slugs_already_present' => 0,
            'email_template_slugs_generated' => 0,
        ];

        if (!$this->shouldRun($payload)) {
            return ['payload' => $payload, 'stats' => $stats];
        }

        if (isset($payload['tables']['email_templates']) && is_array($payload['tables']['email_templates'])) {
            $this->backfillEmailTemplateSlugs($payload, $stats);
        }

        if (
            !isset($payload['tables']['awards_recommendations'])
            || !is_array($payload['tables']['awards_recommendations'])
        ) {
            return ['payload' => $payload, 'stats' => $stats];
        }

        $payload['tables']['awards_bestowals'] ??= [];
        $payload['tables']['awards_bestowal_recommendations'] ??= [];

        $existingBestowalByPrimaryRecommendation = $this->existingBestowalByPrimaryRecommendation(
            $payload['tables']['awards_bestowals'],
        );
        $bestowalByParentRecommendation = $existingBestowalByPrimaryRecommendation;
        $existingLinks = $this->existingBestowalRecommendationLinks(
            $payload['tables']['awards_bestowal_recommendations'],
        );

        $nextBestowalId = $this->nextId($payload['tables']['awards_bestowals']);
        $nextLinkId = $this->nextId($payload['tables']['awards_bestowal_recommendations']);
        $now = date('Y-m-d H:i:s');

        foreach ($payload['tables']['awards_recommendations'] as $index => $recommendation) {
            if (!is_array($recommendation) || !$this->isEligibleParentRecommendation($recommendation)) {
                continue;
            }

            $recommendationId = (int)$recommendation['id'];
            $bestowalId = $bestowalByParentRecommendation[$recommendationId] ?? null;
            if ($bestowalId === null) {
                if ($this->missingRequiredBestowalData($recommendation)) {
                    $stats['recommendations_skipped_missing_required_data']++;
                    continue;
                }

                $bestowalId = $nextBestowalId++;
                $bestowal = $this->buildBestowalRow($bestowalId, $recommendation, $now);
                $payload['tables']['awards_bestowals'][] = $bestowal;
                $bestowalByParentRecommendation[$recommendationId] = $bestowalId;
                $stats['bestowals_created']++;
            }

            if ($this->setRecommendationBestowalId($payload, (int)$index, $bestowalId)) {
                $stats['recommendations_linked_to_bestowals']++;
            }
            if (
                $this->addBestowalRecommendationLink(
                    $payload,
                    $existingLinks,
                    $nextLinkId,
                    $bestowalId,
                    $recommendationId,
                    $now,
                )
            ) {
                $stats['bestowal_recommendation_links_created']++;
            }
        }

        foreach ($payload['tables']['awards_recommendations'] as $index => $recommendation) {
            if (!is_array($recommendation) || $this->isEmptyId($recommendation['recommendation_group_id'] ?? null)) {
                continue;
            }

            $parentRecommendationId = (int)$recommendation['recommendation_group_id'];
            $bestowalId = $bestowalByParentRecommendation[$parentRecommendationId] ?? null;
            if ($bestowalId === null || !isset($recommendation['id'])) {
                continue;
            }

            $recommendationId = (int)$recommendation['id'];
            if ($this->setRecommendationBestowalId($payload, (int)$index, $bestowalId)) {
                $stats['recommendations_linked_to_bestowals']++;
            }
            if (
                $this->addBestowalRecommendationLink(
                    $payload,
                    $existingLinks,
                    $nextLinkId,
                    $bestowalId,
                    $recommendationId,
                    $now,
                )
            ) {
                $stats['bestowal_recommendation_links_created']++;
            }
        }

        $this->backfillReasonSummaries($payload);

        return ['payload' => $payload, 'stats' => $stats];
    }

    /**
     * @param array<int, mixed> $rows
     */
    private function hasLegacyEmailTemplateRows(array $rows): bool
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            if (trim((string)($row['slug'] ?? '')) === '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, int> $stats
     */
    private function backfillEmailTemplateSlugs(array &$payload, array &$stats): void
    {
        $usedSlugs = [];
        foreach ($payload['tables']['email_templates'] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $slug = trim((string)($row['slug'] ?? ''));
            if ($slug !== '') {
                $usedSlugs[$slug] = true;
                $stats['email_template_slugs_already_present']++;
            }
        }

        foreach ($payload['tables']['email_templates'] as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $existingSlug = trim((string)($row['slug'] ?? ''));
            if ($existingSlug !== '') {
                continue;
            }

            $mapping = $this->emailTemplateMappingFor($row);
            $baseSlug = $mapping['slug'] ?? $this->legacyEmailTemplateSlugFor($row);
            $slug = $this->uniqueSlug($baseSlug, $usedSlugs);
            $usedSlugs[$slug] = true;

            $payload['tables']['email_templates'][$index]['slug'] = $slug;
            if (trim((string)($row['name'] ?? '')) === '' && isset($mapping['name'])) {
                $payload['tables']['email_templates'][$index]['name'] = $mapping['name'];
            }
            if (trim((string)($row['description'] ?? '')) === '' && isset($mapping['description'])) {
                $payload['tables']['email_templates'][$index]['description'] = $mapping['description'];
            }
            if (trim((string)($row['variables_schema'] ?? '')) === '') {
                $variablesSchema = $this->variablesSchemaFromAvailableVars($row['available_vars'] ?? null);
                if ($variablesSchema !== null) {
                    $payload['tables']['email_templates'][$index]['variables_schema'] = $variablesSchema;
                }
            }

            $stats['email_template_slugs_backfilled']++;
            if ($mapping === []) {
                $stats['email_template_slugs_generated']++;
            }
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array{slug: string, name: string, description: string}|array{}
     */
    private function emailTemplateMappingFor(array $row): array
    {
        $key = trim((string)($row['mailer_class'] ?? ''))
            . '::'
            . trim((string)($row['action_method'] ?? ''));

        return self::LEGACY_EMAIL_TEMPLATE_SLUGS[$key] ?? [];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function legacyEmailTemplateSlugFor(array $row): string
    {
        $templateId = (int)($row['id'] ?? 0);
        $mailerClass = trim((string)($row['mailer_class'] ?? ''));
        $actionMethod = trim((string)($row['action_method'] ?? ''));

        if ($mailerClass !== '' && $actionMethod !== '') {
            $shortMailer = preg_replace(
                '/Mailer$/',
                '',
                basename(str_replace('\\', '/', $mailerClass)),
            ) ?: 'template';

            return 'legacy-' . $this->slugify($shortMailer) . '-' . $this->slugify($actionMethod);
        }

        return 'legacy-template-' . max(1, $templateId);
    }

    /**
     * @param array<string, bool> $usedSlugs
     */
    private function uniqueSlug(string $baseSlug, array $usedSlugs): string
    {
        $slug = $baseSlug !== '' ? $baseSlug : 'legacy-template';
        $candidate = $slug;
        $suffix = 1;
        while (isset($usedSlugs[$candidate])) {
            $suffix++;
            $candidate = $slug . '-' . $suffix;
        }

        return $candidate;
    }

    /**
     * Convert legacy identifiers into URL-safe slugs.
     */
    private function slugify(string $value): string
    {
        $value = preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $value) ?? $value;
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;

        return trim($value, '-');
    }

    /**
     * Build a current variables schema from legacy available_vars JSON.
     */
    private function variablesSchemaFromAvailableVars(mixed $availableVars): ?string
    {
        if (!is_string($availableVars) || trim($availableVars) === '') {
            return null;
        }

        $decoded = json_decode($availableVars, true);
        if (!is_array($decoded)) {
            return null;
        }

        $schema = [];
        foreach ($decoded as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $name = trim((string)($entry['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $schema[$name] = [
                'type' => 'string',
                'label' => trim((string)($entry['description'] ?? '')) ?: $name,
            ];
        }

        if ($schema === []) {
            return null;
        }

        $encoded = json_encode($schema);

        return is_string($encoded) ? $encoded : null;
    }

    /**
     * @param array<string, mixed> $recommendation
     */
    private function isEligibleParentRecommendation(array $recommendation): bool
    {
        return isset($recommendation['id'])
            && empty($recommendation['deleted'])
            && $this->isEmptyId($recommendation['recommendation_group_id'] ?? null)
            && $this->isEmptyId($recommendation['bestowal_id'] ?? null)
            && in_array((string)($recommendation['state'] ?? ''), self::ELIGIBLE_RECOMMENDATION_STATES, true);
    }

    /**
     * @param array<string, mixed> $recommendation
     */
    private function missingRequiredBestowalData(array $recommendation): bool
    {
        return $this->isEmptyId($recommendation['member_id'] ?? null)
            || $this->isEmptyId($recommendation['award_id'] ?? null);
    }

    /**
     * @param array<string, mixed> $recommendation
     * @return array<string, mixed>
     */
    private function buildBestowalRow(int $bestowalId, array $recommendation, string $now): array
    {
        $state = $this->bestowalStateForRecommendation($recommendation);
        $created = (string)($recommendation['created'] ?? $now);
        $reason = trim((string)($recommendation['reason'] ?? ''));

        return [
            'id' => $bestowalId,
            'member_id' => (int)$recommendation['member_id'],
            'gathering_id' => $this->nullableInt($recommendation['gathering_id'] ?? null),
            'gathering_scheduled_activity_id' => $this->nullableInt(
                $recommendation['gathering_scheduled_activity_id'] ?? null,
            ),
            'roaming_court' => false,
            'primary_recommendation_id' => (int)$recommendation['id'],
            'award_id' => (int)$recommendation['award_id'],
            'specialty' => $recommendation['specialty'] ?? null,
            'status' => $this->bestowalStatusForState($state),
            'state' => $state,
            'state_date' => $recommendation['modified'] ?? $created,
            'stack_rank' => 0,
            'bestowed_at' => $recommendation['given'] ?? null,
            'source' => 'recommendation',
            'source_approval_run_id' => null,
            'noble_notes' => $reason !== '' ? $reason : null,
            'herald_notes' => null,
            'reason_summary' => null,
            'call_into_court' => $recommendation['call_into_court'] ?? null,
            'court_availability' => $recommendation['court_availability'] ?? null,
            'person_to_notify' => $recommendation['person_to_notify'] ?? null,
            'close_reason' => null,
            'created' => $created,
            'modified' => $now,
            'created_by' => $this->nullableInt($recommendation['created_by'] ?? null),
            'modified_by' => $this->nullableInt($recommendation['modified_by'] ?? null),
            'deleted' => null,
        ];
    }

    /**
     * @param array<string, mixed> $recommendation
     */
    private function bestowalStateForRecommendation(array $recommendation): string
    {
        $state = (string)($recommendation['state'] ?? '');
        if ($state === 'Need to Schedule' && !$this->isEmptyId($recommendation['gathering_id'] ?? null)) {
            return 'Gathering Assigned';
        }
        if ($state === 'Need to Schedule') {
            return 'Created';
        }
        if ($state === 'Scheduled') {
            return 'Court Scheduled';
        }
        if ($state === 'Given') {
            return 'Given';
        }
        if ($state === 'Announced Not Given') {
            return 'Announced Not Given';
        }

        return 'Created';
    }

    /**
     * Map a bestowal state to its high-level status bucket.
     */
    private function bestowalStatusForState(string $state): string
    {
        return match ($state) {
            'Court Scheduled' => 'Scheduling',
            'Given', 'Announced Not Given' => 'Closed',
            default => 'Planning',
        };
    }

    /**
     * @param array<string, mixed> $recommendation
     */
    private function reasonSummaryForRecommendation(array $recommendation): ?string
    {
        $reason = trim((string)($recommendation['reason'] ?? ''));
        if ($reason === '') {
            return null;
        }

        $submitter = trim((string)($recommendation['requester_sca_name'] ?? ''));
        if ($submitter === '') {
            $submitter = 'Unknown submitter';
        }

        return 'Submitted by ' . $submitter . ":\n" . $reason;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function setRecommendationBestowalId(array &$payload, int $index, int $bestowalId): bool
    {
        if (($payload['tables']['awards_recommendations'][$index]['bestowal_id'] ?? null) === $bestowalId) {
            return false;
        }

        $payload['tables']['awards_recommendations'][$index]['bestowal_id'] = $bestowalId;

        return true;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, bool> $existingLinks
     */
    private function addBestowalRecommendationLink(
        array &$payload,
        array &$existingLinks,
        int &$nextLinkId,
        int $bestowalId,
        int $recommendationId,
        string $now,
    ): bool {
        $key = "{$bestowalId}:{$recommendationId}";
        if (isset($existingLinks[$key])) {
            return false;
        }

        $payload['tables']['awards_bestowal_recommendations'][] = [
            'id' => $nextLinkId++,
            'bestowal_id' => $bestowalId,
            'recommendation_id' => $recommendationId,
            'created' => $now,
        ];
        $existingLinks[$key] = true;

        return true;
    }

    /**
     * @param array<int, mixed> $bestowals
     * @return array<int, int>
     */
    private function existingBestowalByPrimaryRecommendation(array $bestowals): array
    {
        $map = [];
        foreach ($bestowals as $bestowal) {
            if (
                !is_array($bestowal)
                || !isset($bestowal['id'])
                || $this->isEmptyId($bestowal['primary_recommendation_id'] ?? null)
            ) {
                continue;
            }
            $map[(int)$bestowal['primary_recommendation_id']] = (int)$bestowal['id'];
        }

        return $map;
    }

    /**
     * @param array<int, mixed> $links
     * @return array<string, bool>
     */
    private function existingBestowalRecommendationLinks(array $links): array
    {
        $map = [];
        foreach ($links as $link) {
            if (
                !is_array($link)
                || $this->isEmptyId($link['bestowal_id'] ?? null)
                || $this->isEmptyId($link['recommendation_id'] ?? null)
            ) {
                continue;
            }
            $map[(int)$link['bestowal_id'] . ':' . (int)$link['recommendation_id']] = true;
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function backfillReasonSummaries(array &$payload): void
    {
        $sectionsByBestowal = [];
        foreach ($payload['tables']['awards_recommendations'] as $recommendation) {
            if (!is_array($recommendation) || $this->isEmptyId($recommendation['bestowal_id'] ?? null)) {
                continue;
            }
            $summary = $this->reasonSummaryForRecommendation($recommendation);
            if ($summary === null) {
                continue;
            }
            $sectionsByBestowal[(int)$recommendation['bestowal_id']][] = $summary;
        }

        foreach ($payload['tables']['awards_bestowals'] as $index => $bestowal) {
            if (!is_array($bestowal) || !empty($bestowal['reason_summary']) || !isset($bestowal['id'])) {
                continue;
            }
            $sections = $sectionsByBestowal[(int)$bestowal['id']] ?? [];
            if ($sections !== []) {
                $payload['tables']['awards_bestowals'][$index]['reason_summary'] = implode("\n\n", $sections);
            }
        }
    }

    /**
     * @param array<int, mixed> $rows
     */
    private function nextId(array $rows): int
    {
        $maxId = 0;
        foreach ($rows as $row) {
            if (is_array($row) && isset($row['id'])) {
                $maxId = max($maxId, (int)$row['id']);
            }
        }

        return $maxId + 1;
    }

    /**
     * Check whether a legacy foreign key value is effectively empty.
     */
    private function isEmptyId(mixed $value): bool
    {
        return $value === null || $value === '' || (string)$value === '0';
    }

    /**
     * Normalize optional legacy foreign key values.
     */
    private function nullableInt(mixed $value): ?int
    {
        return $this->isEmptyId($value) ? null : (int)$value;
    }
}
